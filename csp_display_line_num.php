<?php

namespace Code_Snippets;
 
$render_callback = function () {
    $default_line_number = 2;
    $line_number = isset( $_REQUEST['line_number'] ) ? intval( $_REQUEST['line_number'] ) : 0;
    echo '<div class="wrap">';
 
    ?>
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    <p>This will display the contents of a given line number for all of your active snippets. You can use this to help
        identify which snippet is causing a particular PHP error on your website.</p>
    <p>For example, if you receive a PHP error similar to this, which mentions the <strong>snippet-ops.php</strong>
        file, it means one of your snippets is causing the error:</p>
    <p><code>Undefined variable: xyz in /wp-content/plugins/code-snippets/php/snippet-ops.php(446) : eval()'d code on line N</code></p>
    <p>Then you can enter the line number from the error message into the field below, and look through the results to
        find which snippet contains the text that matches the cause of the error (e.g. "xyz" in the above example).</p>
    <hr>
    <form method="post" action="">
        <p>
            <label for="line_number">Line number to show:</label>
            <input type="number" name="line_number" id="line_number"
                   value="<?php echo esc_attr( $line_number ? $line_number : $default_line_number ); ?>">
            &nbsp;
            <input type="submit" value="Show" class="button button-primary">
        </p>
    </form>
    <?php
 
    if ( $line_number ) {
        echo '<h2>Results</h2>';
 
        $all_snippets = get_snippets();
 
        foreach ( $all_snippets as $snippet ) {
            $lines = explode( PHP_EOL, $snippet->code );
 
            if ( ! $snippet->active || $line_number > count( $lines ) ) {
                continue;
            }
 
            printf(
                '<hr><p><a href="%s" target="_blank">%s</a></p>%s',
                esc_url( code_snippets()->get_snippet_edit_url( $snippet->id ) ),
                esc_html( $snippet->display_name ),
                PHP_EOL
            );
 
            echo '<pre><code>', esc_html( trim( $lines[ $line_number - 1 ] ) ), '</code></pre>', PHP_EOL;
        }
    }
 
    echo '</div>';
};
 
add_action( 'admin_menu', function () use ( $render_callback ) {
    add_submenu_page(
        code_snippets()->get_menu_slug(),
        'Display Snippet Line Numbers',
        'Display Line Numbers',
        code_snippets()->get_cap(),
        'snippet-line-numbers',
        $render_callback
    );
}, 99 );
