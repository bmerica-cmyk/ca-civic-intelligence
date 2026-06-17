<?php
/**
 * Plugin Name: CA Civic Intelligence
 * Plugin URI:  https://californiacivic.com
 * Description: California statewide civic-intelligence and opinion platform. Registers custom post types, taxonomies, REST ingestion endpoints, editorial workflow, and sponsored-distribution framework.
 * Version:     0.1.0
 * Author:      California Civic Intelligence
 * License:     GPL-3.0-or-later
 * Text Domain: ca-civic-intel
 * Namespace:   CA_Civic_Intel
 *
 * SECURITY RULES (non-negotiable):
 *   - No AI-generated content may auto-publish. Status must be draft or pending.
 *   - Money cannot buy placement. Paid distribution only after editorial acceptance.
 *   - HMAC-SHA256 required on all ingestion REST endpoints.
 *   - Never expose HMAC secret or OpenAI key in REST responses.
 *   - All SQL via $wpdb->prepare().
 *   - All output escaped; all input sanitized.
 */

defined( 'ABSPATH' ) || exit;

define( 'CA_CIVIC_VERSION', '0.1.0' );
define( 'CA_CIVIC_DIR',     plugin_dir_path( __FILE__ ) );
define( 'CA_CIVIC_URL',     plugin_dir_url( __FILE__ ) );

/* ------------------------------------------------------------------
   * Auto-loader
   * ------------------------------------------------------------------ */
spl_autoload_register( function ( $class ) {
      if ( strpos( $class, 'CA_Civic_Intel\\' ) !== 0 ) {
                return;
      }
      $rel  = str_replace( [ 'CA_Civic_Intel\\', '\\' ], [ '', '/' ], $class );
      $file = CA_CIVIC_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', $rel ) ) . '.php';
      if ( file_exists( $file ) ) {
                require_once $file;
      }
} );

/* ------------------------------------------------------------------
   * Activation / deactivation hooks
   * ------------------------------------------------------------------ */
register_activation_hook( __FILE__,   [ 'CA_Civic_Intel\\Activator',   'activate'   ] );
register_deactivation_hook( __FILE__, [ 'CA_Civic_Intel\\Activator',   'deactivate' ] );

/* ------------------------------------------------------------------
   * Bootstrap
   * ------------------------------------------------------------------ */
add_action( 'plugins_loaded', function () {
      new CA_Civic_Intel\Post_Types();
      new CA_Civic_Intel\Taxonomies();
      new CA_Civic_Intel\REST_API();
      new CA_Civic_Intel\Editorial_Queue();
      new CA_Civic_Intel\Submission_Form();
      new CA_Civic_Intel\Sponsored_Distribution();
} );
