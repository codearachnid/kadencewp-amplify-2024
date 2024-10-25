<?php

add_action( 'gform_validation', function( $validation_result ) {

    $form = $validation_result['form'];
 
    // Return without changes if form id is not "login form"
    if ( 11 != $form['id'] ) {
        return $validation_result;
    }
 
	$username = rgpost( 'input_1' );
	$password = rgpost( 'input_3' );
	$user = wp_authenticate($username, $password);

    if( !is_wp_error($user) ){
		wp_clear_auth_cookie();
		wp_set_current_user ( $user->ID );
		wp_set_auth_cookie  ( $user->ID );
	} else {
		$validation_result['is_valid'] = false;
        $validation_result['form']['validation_message'] = '<div class="validation_error">Your username/password combination failed. Please try again.</div>';
	}
	
    return $validation_result;
} );
