<?php
/**
 * Plugin Name: JSON Basic Authentication
 * Description: Basic Authentication handler for the JSON API, used for development and debugging purposes
 * Author: WordPress API Team
 * Author URI: https://github.com/WP-API
 * Version: 0.1
 * Plugin URI: https://github.com/WP-API/Basic-Auth
 */

function json_basic_auth_handler( $user ) {
    global $wp_json_basic_auth_error;
    $wp_json_basic_auth_error = null;

    // Don't authenticate twice
    if ( ! empty( $user ) ) {
        return $user;
    }
	//account for issue where some servers remove the PHP auth headers
	//so instead look for auth info in a custom environment variable set by rewrite rules
	//probably in .htaccess
    if (
        !isset($_SERVER['PHP_AUTH_USER']) 
        && (
            isset($_SERVER['HTTP_AUTHORIZATION']) 
            || isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])
        )
        ) {
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['HTTP_AUTHORIZATION'];
        } else {
            $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if( ! empty( $header ) ) {
              list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', base64_decode(substr($header, 6)));
        }
    }

    // Check that we're trying to authenticate
    if ( !isset( $_SERVER['PHP_AUTH_USER'] ) ) {
        return $user;
    }
    $username = $_SERVER['PHP_AUTH_USER'];
    $password = $_SERVER['PHP_AUTH_PW'];

    /**
     * In multi-site, wp_authenticate_spam_check filter is run on authentication. This filter calls
     * get_currentuserinfo which in turn calls the determine_current_user filter. This leads to infinite
     * recursion and a stack overflow unless the current function is removed from the determine_current_user
     * filter during authentication.
     */
    remove_filter( 'determine_current_user', 'json_basic_auth_handler', 20 );
    remove_filter( 'authenticate', 'wp_authenticate_spam_check', 99 );

    $user = wp_authenticate( $username, $password );

    add_filter( 'determine_current_user', 'json_basic_auth_handler', 20 );
    add_filter( 'authenticate', 'wp_authenticate_spam_check', 99 );

    if ( is_wp_error( $user ) ) {
        $wp_json_basic_auth_error = $user;
        return null;
    }

    $wp_json_basic_auth_error = true;
    //if we found a user, remove regular cookie filters because
    //they're just going to overwrite what we've found
    if( $user->ID ){
        remove_filter( 'determine_current_user', 'wp_validate_auth_cookie' );
        remove_filter( 'determine_current_user', 'wp_validate_logged_in_cookie', 20 );
    }
    return $user->ID;
}
add_filter( 'determine_current_user', 'json_basic_auth_handler', 5 );

function json_basic_auth_error( $error ) {
	// Passthrough other errors
	if ( ! empty( $error ) ) {
		return $error;
	}

	global $wp_json_basic_auth_error;

	return $wp_json_basic_auth_error;
}
add_filter( 'json_authentication_errors', 'json_basic_auth_error' );
add_filter( 'rest_authentication_errors', 'json_basic_auth_error' );

function json_basic_auth_add_checkbox_to_permalinks_page() {
	//don't bother registering a field. the permalinks page doesnt save them anyway. gotta do it manually on init
	add_settings_field( 'htaccess_allow_basic_auth', __( 'Allow Basic Auth', 'basic_auth' ), 'json_basic_auth_render_checkbox', 'permalink', 'optional' );
}
add_action( 'admin_init', 'json_basic_auth_add_checkbox_to_permalinks_page' );
add_action( 'admin_init', 'json_basic_auth_handle_settings_submit', 30 );
function json_basic_auth_render_checkbox( $args ) {
	?>
<input type="checkbox" id="htaccess_allow_basic_auth" name="htaccess_allow_basic_auth" value="1" <?php checked(1, get_option('htaccess_allow_basic_auth') );?> />
<label for="htaccess_allow_basic_auth"><?php _e( 'If you utilize the WP API and PHP FCGI or CGI, you may need to enable this to allow Basic Auth authentication', 'basic_auth');?></label>
	<?php
}
/**
 * Save our setting from the permalinks page (because WP wasn't saving it auotmatically,
 * even when using register_field()
 */
function json_basic_auth_handle_settings_submit() {
	$screen = get_current_screen();
	if( $screen instanceof WP_Screen
		&& $screen->base === 'options-permalink'
		&& current_user_can( 'manage_options' ) 
	) {
		$new_val = isset( $_POST[ 'htaccess_allow_basic_auth' ] ) ? intval( $_POST[ 'htaccess_allow_basic_auth' ] ) : false;
		update_option( 'htaccess_allow_basic_auth', $new_val );
	}
}


/**
 * Change the htaccess file's contents so they allow HTTP authorization through
 * @param string $htaccess_file_content
 * @return string
 */
function json_basic_auth_fix_htaccess_content( $htaccess_file_content ) {
	if( get_option( 'htaccess_allow_basic_auth' ) ) {
		$htaccess_file_content = str_replace(
			'RewriteRule ^index\.php$ - [L]', 
			'RewriteRule ^index\.php$ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization},L]', 
			$htaccess_file_content
		);
	}
	return $htaccess_file_content;
}
add_filter( 'mod_rewrite_rules', 'json_basic_auth_fix_htaccess_content' );