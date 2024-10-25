<?php

/**
 * Custom Gravity Forms field validation for "Forgot Password" form.
 *
 * This function checks if the entered email exists in the system before allowing form submission.
 * It runs a validation for the email address field and if the user doesn't exist, 
 * the validation fails, and a custom error message is displayed.
 *
 * @param array $result Validation result and status.
 * @param mixed $value Field value submitted by the user.
 * @param array $form The form object.
 * @param array $field The field object being validated.
 * @return array Modified validation result.
 */
add_filter('gform_field_validation', 'gform_forgot_password_field_validation', 10, 4);
function gform_forgot_password_field_validation($result, $value, $form, $field) {
	
	$form_id = rgar( $form, 'id' );
	$field_id = rgar( $field, 'id' );
	$allowed_form_field_ids = apply_filters( 'gform_forgot_password_field_validation_field_ids', [] );
	
	if( !in_array( $form_id . '_' . $field_id , $allowed_form_field_ids ) ){
		return $result;
	}
	
	$email = is_array( $value ) ? $value[0] : $value;
    $user = get_user_by( 'email', $email );
	if( empty($user) && $result['is_valid'] ) {
        $result['is_valid'] = false;
        $result['message'] = apply_filters( 'gform_forgot_password_field_validation_message', 'That email address does not exist in our system.' );
    }
	
    return $result;

}

/**
 * Customizes the "Forgot Password" notification email after form submission.
 *
 * This function dynamically generates and inserts the password reset link 
 * into the email notification sent to the user after successfully submitting 
 * a "Forgot Password" form.
 *
 * @param array $notification The current notification settings.
 * @param array $form The form object.
 * @param array $entry The entry object containing submitted form data.
 * @return array Modified notification settings with custom message.
 */
add_filter('gform_notification', 'gform_forgot_password_notification', 10, 3);
function gform_forgot_password_notification($notification, $form, $entry) {
	
	$form_id = rgar( $form, 'id' );	
	$allowed_form_ids = apply_filters( 'gform_forgot_password_notification_form_ids', [] );
	
	if( !in_array( $form_id , $allowed_form_ids ) ){
		return $notification;
	}

    // Send the forgot password email
    $user = get_user_by('email', rgar($entry, '1'));
    
    if($user->ID) {
        $displayName = $user->display_name;
        $reset_link = add_query_arg([
            'key' => get_password_reset_key($user),
            'action' => 'rp',
            'login' => urlencode($user->user_login)
        ], apply_filters( 'gform_forgot_password_notification_site_url', site_url('wp-login.php') ) );

        $notification['message'] = str_replace('{full_name}', $displayName, $notification['message']);
        $notification['message'] = str_replace('{password_link}', $reset_link, $notification['message']);
    }

    return $notification;

}

/**
 * Validates the "Reset Password" form before submission.
 *
 * This function checks the validity of the reset password key and user login 
 * before allowing the user to submit a new password. If the key or login is 
 * invalid, it returns an error and prevents form submission.
 *
 * @param array $validation_result The current form validation result.
 * @return array Updated validation result with possible error messages.
 */
add_filter( 'gform_validation', 'gform_reset_password_validation');
function gform_reset_password_validation( $validation_result ) {
    $form = $validation_result['form'];
	
	$form_id = rgar( $form, 'id' );	
	$allowed_form_ids = apply_filters( 'gform_reset_password_validation_form_ids', [] );
	$field_ids = apply_filters( 'gform_reset_password_fields_ids', [
		'user_key' => null,
		'user_login' => null,
		'user_password' => null,
	]);
	
	if( !in_array( $form_id , $allowed_form_ids ) ){
		return $validation_result;
	}
	
	$user_key = rgpost( 'input_' . $field_ids['user_key'] );
	$user_login = rgpost( 'input_' . $field_ids['user_login'] );

	if( is_wp_error( check_password_reset_key( $user_key, $user_login ) ) ){
		$validation_result['is_valid'] = false;
		foreach( $form['fields'] as &$field ) {
			// notify the user on the password field
            if ( $field->id == $field_ids['user_password'] ) {
                $field->failed_validation = true;
                $field->validation_message = apply_filters( 'gform_reset_password_validation_message', 'You do not have permission to reset the password. Please try requesting a new <a href="/login">forgot password</a> link again.' );
                break;
            }
        }
	}

    $validation_result['form'] = $form;
    return $validation_result;
  
}

/**
 * Resets the user's password after successful form submission.
 *
 * This function handles the actual password reset operation, updating the 
 * user's password in the database and logging the reset event.
 *
 * @param array $entry The entry object containing submitted form data.
 * @param array $form The form object.
 */
add_action( 'gform_after_submission', 'gform_reset_password_after_submission', 10, 2 );
function gform_reset_password_after_submission( $entry, $form ) {
	
	$form_id = rgar( $form, 'id' );	
	$allowed_form_ids = apply_filters( 'gform_reset_password_after_submission_form_ids', [] );
	$field_ids = apply_filters( 'gform_reset_password_fields_ids', [
		'user_key' => null,
		'user_login' => null,
		'user_password' => null,
	]);
	
	if( !in_array( $form_id , $allowed_form_ids ) ){
		return $result;
	}
 
	$user_key = rgar( $entry, $field_ids['user_key'] );
	$user_login = rgar( $entry, $field_ids['user_login'] );
	$password = rgar( $entry, $field_ids['user_password'] );
	$user_id = email_exists( $user_login );
	
	if ( !$user_id || is_wp_error( check_password_reset_key( $user_key, $user_login ) ) ) {
		return false;
	}
	
	wp_set_password( $password, $user_id );
	delete_user_meta( $user_id, 'password_reset_key' );
	
	$should_auto_login = apply_filters( 'gform_reset_password_after_submission_should_auto_login', '__return_false' );
	if( $should_auto_login ){
		// Set the authentication cookie and log the user in
        wp_clear_auth_cookie();
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id );

		// Optionally redirect after login
		$redirect_url = apply_filters( 'gform_reset_password_after_submission_auto_login_redirect_url', '' ); // Change the redirect URL if needed
		if( !empty($redirect_url) ){
			wp_safe_redirect( $redirect_url );
			exit();  // Stop further execution after redirect			
		}
	}

}

/** 
 * sample filter defintions to configure these hooks
 * 

add_filter('gform_forgot_password_field_validation_field_ids', function( $value ){
	return [ '1_1' ];
});

add_filter('gform_forgot_password_notification_form_ids', function( $value ){
	return [ 1 ];
});

add_filter('gform_reset_password_validation_form_ids', function( $value ){
	return [ 1 ];
});

add_filter('gform_reset_password_after_submission_form_ids', function( $value ){
	return [ 1 ];
});

// define the field ids from the `reset password` form
add_filter('gform_reset_password_fields_ids', function( $value ){
	return [
		'user_key' => 1,
		'user_login' => 2,
		'user_password' => 3,
	];
});

 **/


/**
 * installable json form
 * 

{
  "0": {
    "title": "Forgot Password",
    "description": "",
    "labelPlacement": "top_label",
    "descriptionPlacement": "below",
    "button": {
      "type": "text",
      "text": "Reset my Password",
      "imageUrl": "",
      "conditionalLogic": null
    },
    "fields": [
      {
        "type": "email",
        "id": 1,
        "formId": 27,
        "label": "Email",
        "adminLabel": "",
        "isRequired": true,
        "size": "large",
        "errorMessage": "",
        "visibility": "visible",
        "inputs": [
          {
            "id": "1",
            "label": "Enter Email",
            "name": "",
            "autocompleteAttribute": "email",
            "defaultValue": "",
            "placeholder": "e.g. you@company.com"
          },
          {
            "id": "1.2",
            "label": "Confirm Email",
            "name": "",
            "autocompleteAttribute": "email",
            "defaultValue": "",
            "placeholder": "e.g. you@company.com"
          }
        ],
        "autocompleteAttribute": "email",
        "description": "",
        "allowsPrepopulate": false,
        "inputMask": false,
        "inputMaskValue": "",
        "inputMaskIsCustom": false,
        "maxLength": "",
        "inputType": "",
        "labelPlacement": "",
        "descriptionPlacement": "",
        "subLabelPlacement": "",
        "placeholder": "",
        "cssClass": "",
        "inputName": "",
        "noDuplicates": false,
        "defaultValue": "",
        "enableAutocomplete": false,
        "choices": "",
        "conditionalLogic": "",
        "productField": "",
        "layoutGridColumnSpan": "",
        "emailConfirmEnabled": true,
        "enableEnhancedUI": 0,
        "layoutGroupId": "c8f43a5d",
        "multipleFiles": false,
        "maxFiles": "",
        "calculationFormula": "",
        "calculationRounding": "",
        "enableCalculation": "",
        "disableQuantity": false,
        "displayAllCategories": false,
        "useRichTextEditor": false,
        "gppa-choices-filter-groups": [],
        "gppa-choices-templates": [],
        "gppa-values-filter-groups": [],
        "gppa-values-templates": [],
        "fields": "",
        "displayOnly": ""
      }
    ],
    "version": "2.8.12",
    "id": 27,
    "markupVersion": 2,
    "nextFieldId": 2,
    "useCurrentUserAsAuthor": true,
    "postContentTemplateEnabled": false,
    "postTitleTemplateEnabled": false,
    "postTitleTemplate": "",
    "postContentTemplate": "",
    "lastPageButton": null,
    "pagination": null,
    "firstPageCssClass": null,
    "subLabelPlacement": "below",
    "requiredIndicator": "asterisk",
    "customRequiredIndicator": "(Required)",
    "cssClass": "",
    "buttonType": "text",
    "buttonText": "Reset my Password",
    "buttonImageURL": "",
    "form_button_conditional_logic_object": "",
    "form_button_conditional_logic": "0",
    "saveButtonText": "Save and Continue Later",
    "limitEntries": false,
    "limitEntriesCount": "",
    "limitEntriesPeriod": "",
    "limitEntriesMessage": "",
    "scheduleForm": false,
    "scheduleStart": "",
    "scheduleEnd": "",
    "schedulePendingMessage": "",
    "scheduleMessage": "",
    "requireLogin": false,
    "requireLoginMessage": "",
    "enableHoneypot": true,
    "validationSummary": false,
    "saveEnabled": "",
    "enableAnimation": true,
    "save": {
      "enabled": false,
      "button": {
        "type": "link",
        "text": "Save and Continue Later"
      }
    },
    "scheduleStartHour": "",
    "scheduleStartMinute": "",
    "scheduleStartAmpm": "",
    "scheduleEndHour": "",
    "scheduleEndMinute": "",
    "scheduleEndAmpm": "",
    "deprecated": "",
    "gwreloadform_enable": "0",
    "gwreloadform_refresh_time": "",
    "gwreloadform_preserve_previous_values": "0",
    "legacy": "",
    "customJS": "",
    "feeds": {
      "gravityformsadvancedpostcreation": []
    },
    "honeypotAction": "spam",
    "confirmations": [
      {
        "id": "613231f2d48cc",
        "name": "Default Confirmation",
        "isDefault": true,
        "type": "message",
        "message": "Thank you. An email will be sent shortly. Please check your email (including Junk/Spam) for the reset link.",
        "url": "",
        "pageId": "",
        "queryString": "",
        "event": "",
        "disableAutoformat": false,
        "page": "",
        "conditionalLogic": []
      }
    ],
    "notifications": [
      {
        "id": "6308c4ad6f7c6",
        "name": "Password Reset",
        "service": "wordpress",
        "event": "form_submission",
        "toType": "field",
        "toEmail": "",
        "toField": "1",
        "routing": null,
        "fromName": "",
        "from": "{admin_email}",
        "replyTo": "{admin_email}",
        "bcc": "",
        "subject": "Your password reset instructions",
        "message": "Hi {full_name},\r\n\r\nYou (or someone else) has requested a password reset for your account. If this was a mistake, you can ignore this email.\r\n\r\nTo reset your password, visit the following address: {password_link}",
        "disableAutoformat": false,
        "notification_conditional_logic_object": "",
        "notification_conditional_logic": "0",
        "conditionalLogic": null,
        "to": "1",
        "cc": "",
        "enableAttachments": false
      }
    ]
  },
  "1": {
    "fields": [
      {
        "type": "hidden",
        "id": 1,
        "formId": 29,
        "label": "User Key",
        "adminLabel": "",
        "isRequired": false,
        "size": "large",
        "errorMessage": "",
        "visibility": "visible",
        "inputs": null,
        "description": "",
        "allowsPrepopulate": true,
        "inputMask": false,
        "inputMaskValue": "",
        "inputMaskIsCustom": false,
        "maxLength": "",
        "labelPlacement": "",
        "descriptionPlacement": "",
        "subLabelPlacement": "",
        "placeholder": "",
        "cssClass": "",
        "inputName": "key",
        "noDuplicates": false,
        "defaultValue": "",
        "enableAutocomplete": false,
        "autocompleteAttribute": "",
        "choices": "",
        "conditionalLogic": "",
        "productField": "",
        "layoutGridColumnSpan": "",
        "gpaaEnable": "",
        "enableEnhancedUI": 0,
        "layoutGroupId": "961b482d",
        "multipleFiles": false,
        "maxFiles": "",
        "calculationFormula": "",
        "calculationRounding": "",
        "enableCalculation": "",
        "disableQuantity": false,
        "displayAllCategories": false,
        "useRichTextEditor": false,
        "imageChoices_enableImages": false,
        "imageChoices_useLightboxCaption": true,
        "imageChoices_theme": "form_setting",
        "imageChoices_featureColorCustom": "",
        "imageChoices_featureColor": "form_setting",
        "imageChoices_align": "form_setting",
        "imageChoices_imageStyle": "form_setting",
        "imageChoices_height": "",
        "imageChoices_heightMedium": "",
        "imageChoices_heightSmall": "",
        "imageChoices_columnsWidth": "",
        "imageChoices_columnsWidthMedium": "",
        "imageChoices_columnsWidthSmall": "",
        "imageChoices_columns": "form_setting",
        "imageChoices_columnsMedium": "form_setting",
        "imageChoices_columnsSmall": "form_setting",
        "gfgeo_dynamic_field_usage": "",
        "errors": [],
        "fields": "",
        "displayOnly": "",
        "personalDataExport": false,
        "personalDataErase": false
      },
      {
        "type": "hidden",
        "id": 4,
        "formId": 29,
        "label": "User Login",
        "adminLabel": "",
        "isRequired": false,
        "size": "large",
        "errorMessage": "",
        "visibility": "visible",
        "inputs": null,
        "description": "",
        "allowsPrepopulate": true,
        "inputMask": false,
        "inputMaskValue": "",
        "inputMaskIsCustom": false,
        "maxLength": "",
        "labelPlacement": "",
        "descriptionPlacement": "",
        "subLabelPlacement": "",
        "placeholder": "",
        "cssClass": "",
        "inputName": "login",
        "noDuplicates": false,
        "defaultValue": "",
        "enableAutocomplete": false,
        "autocompleteAttribute": "",
        "choices": "",
        "conditionalLogic": "",
        "productField": "",
        "layoutGridColumnSpan": 12,
        "gpaaEnable": "",
        "enableEnhancedUI": 0,
        "layoutGroupId": "9e61568e",
        "multipleFiles": false,
        "maxFiles": "",
        "calculationFormula": "",
        "calculationRounding": "",
        "enableCalculation": "",
        "disableQuantity": false,
        "displayAllCategories": false,
        "useRichTextEditor": false,
        "imageChoices_enableImages": false,
        "imageChoices_useLightboxCaption": true,
        "imageChoices_theme": "form_setting",
        "imageChoices_featureColorCustom": "",
        "imageChoices_featureColor": "form_setting",
        "imageChoices_align": "form_setting",
        "imageChoices_imageStyle": "form_setting",
        "imageChoices_height": "",
        "imageChoices_heightMedium": "",
        "imageChoices_heightSmall": "",
        "imageChoices_columnsWidth": "",
        "imageChoices_columnsWidthMedium": "",
        "imageChoices_columnsWidthSmall": "",
        "imageChoices_columns": "form_setting",
        "imageChoices_columnsMedium": "form_setting",
        "imageChoices_columnsSmall": "form_setting",
        "gfgeo_dynamic_field_usage": "",
        "errors": [],
        "fields": "",
        "displayOnly": "",
        "personalDataExport": false,
        "personalDataErase": false
      },
      {
        "type": "password",
        "id": 3,
        "formId": 29,
        "label": "New Password",
        "adminLabel": "",
        "isRequired": true,
        "size": "large",
        "errorMessage": "",
        "visibility": "visible",
        "inputs": [
          {
            "id": "3",
            "label": "Enter Password",
            "name": ""
          },
          {
            "id": "3.2",
            "label": "Confirm Password",
            "name": ""
          }
        ],
        "displayOnly": true,
        "description": "",
        "allowsPrepopulate": false,
        "inputMask": false,
        "inputMaskValue": "",
        "inputMaskIsCustom": false,
        "maxLength": "",
        "labelPlacement": "",
        "descriptionPlacement": "",
        "subLabelPlacement": "",
        "placeholder": "",
        "cssClass": "",
        "inputName": "",
        "noDuplicates": false,
        "defaultValue": "",
        "enableAutocomplete": false,
        "autocompleteAttribute": "",
        "choices": "",
        "conditionalLogic": "",
        "productField": "",
        "layoutGridColumnSpan": "",
        "passwordStrengthEnabled": true,
        "passwordVisibilityEnabled": true,
        "gpaaEnable": "",
        "enableEnhancedUI": 0,
        "layoutGroupId": "7bba15c8",
        "multipleFiles": false,
        "maxFiles": "",
        "calculationFormula": "",
        "calculationRounding": "",
        "enableCalculation": "",
        "disableQuantity": false,
        "displayAllCategories": false,
        "useRichTextEditor": false,
        "imageChoices_enableImages": false,
        "imageChoices_useLightboxCaption": true,
        "imageChoices_theme": "form_setting",
        "imageChoices_featureColorCustom": "",
        "imageChoices_featureColor": "form_setting",
        "imageChoices_align": "form_setting",
        "imageChoices_imageStyle": "form_setting",
        "imageChoices_height": "",
        "imageChoices_heightMedium": "",
        "imageChoices_heightSmall": "",
        "imageChoices_columnsWidth": "",
        "imageChoices_columnsWidthMedium": "",
        "imageChoices_columnsWidthSmall": "",
        "imageChoices_columns": "form_setting",
        "imageChoices_columnsMedium": "form_setting",
        "imageChoices_columnsSmall": "form_setting",
        "errors": [],
        "minPasswordStrength": "good",
        "fields": "",
        "personalDataExport": false,
        "personalDataErase": false
      }
    ],
    "button": {
      "type": "text",
      "text": "Reset Now",
      "imageUrl": "",
      "width": "auto",
      "location": "bottom",
      "layoutGridColumnSpan": 12,
      "id": "submit"
    },
    "title": "Reset Password",
    "description": "",
    "version": "2.8.17",
    "id": 29,
    "markupVersion": 2,
    "nextFieldId": 5,
    "useCurrentUserAsAuthor": true,
    "postContentTemplateEnabled": false,
    "postTitleTemplateEnabled": false,
    "postTitleTemplate": "",
    "postContentTemplate": "",
    "lastPageButton": null,
    "pagination": null,
    "firstPageCssClass": null,
    "gfpdf_form_settings": [],
    "is_active": "1",
    "date_created": "2024-08-31 01:35:47",
    "is_trash": "0",
    "personalData": {
      "preventIP": true,
      "retention": {
        "policy": "delete",
        "retain_entries_days": "14"
      },
      "exportingAndErasing": {
        "enabled": false,
        "identificationField": "",
        "columns": {
          "ip": {
            "export": false,
            "erase": false
          },
          "source_url": {
            "export": false,
            "erase": false
          },
          "user_agent": {
            "export": false,
            "erase": false
          }
        }
      }
    },
    "confirmations": [
      {
        "id": "66d27373871a2",
        "name": "Default Confirmation",
        "isDefault": true,
        "type": "message",
        "message": "Your password has been successfully reset. <a href=\"/login\">Login here.</a>",
        "url": "",
        "pageId": "",
        "queryString": "",
        "event": "",
        "disableAutoformat": false,
        "page": "",
        "conditionalLogic": []
      }
    ],
    "notifications": [
      {
        "id": "66d2737386442",
        "isActive": false,
        "to": "{admin_email}",
        "name": "Admin Notification",
        "event": "form_submission",
        "toType": "email",
        "subject": "New submission from {form_title}",
        "message": "{all_fields}"
      }
    ],
    "feeds": {}
  },
  "version": "2.8.17"
}

 **/
