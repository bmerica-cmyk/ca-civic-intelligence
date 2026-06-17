<?php
/**
 * Plugin Name: California Civic Intelligence
 * Plugin URI:  https://californiacivic.com
 * Description: Statewide civic-intelligence and opinion platform with AI-assisted public-data briefs, editorial workflow, and sponsored distribution.
 * Version:     0.1.0
 * Author:      Bryan Merica / IDM Communications
 * Author URI:  https://californiacivic.com
 * License:     GPL-2.0+
 * Text Domain: ca-civic-intel
 *
 * SECURITY RULES (non-negotiable):
 *  - No AI-generated content may auto-publish. Status must be draft or pending.
 *  - Money cannot buy placement. Paid distribution only after editorial acceptance.
 *  - HMAC-SHA256 required on all ingestion REST endpoints.
 *  - Never expose HMAC secret or OpenAI key in REST responses.
 *  - All SQL via $wpdb->prepare().
 *  - All output escaped; all input sanitized.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'CA_CIVIC_VERSION',    '0.1.0' );
define( 'CA_CIVIC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CA_CIVIC_PLUGIN_URL', plugin_dir_url(  __FILE__ ) );
define( 'CA_CIVIC_SLUG',       'ca-civic-intelligence' );

// Auto-loader
spl_autoload_register( function( $class ) {
    $prefix = 'CA_Civic_Intel\\';
    $base   = CA_CIVIC_PLUGIN_DIR . 'includes/';
    if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) return;
    $relative = substr( $class, strlen( $prefix ) );
    $file = $base . 'class-' . strtolower( str_replace( array( '\\', '_' ), array( '/', '-' ), $relative ) ) . '.php';
    if ( file_exists( $file ) ) require $file;
} );

register_activation_hook(   __FILE__, array( 'CA_Civic_Intel\\Activator',   'activate'   ) );
register_deactivation_hook( __FILE__, array( 'CA_Civic_Intel\\Deactivator', 'deactivate' ) );

add_action( 'init',          array( 'CA_Civic_Intel\\Post_Types', 'register'          ) );
add_action( 'init',          array( 'CA_Civic_Intel\\Taxonomies', 'register'          ) );
add_action( 'rest_api_init', array( 'CA_Civic_Intel\\Rest_Api',   'register_routes'   ) );
add_action( 'admin_menu',    array( 'CA_Civic_Intel\\Admin',      'add_menus'         ) );
add_action( 'init',          array( 'CA_Civic_Intel\\Submission',  'register_shortcode' ) );
