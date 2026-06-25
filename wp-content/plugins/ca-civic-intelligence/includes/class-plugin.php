<?php
/**
 * The core plugin orchestrator.
 *
 * @package CA_Civic_Intel
 */

namespace CA_Civic_Intel;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Plugin main orchestrator.
 * Instantiates and wires all sub-components.
 */
class Plugin {

    /** @var Loader */
    protected $loader;

    /** @var string */
    protected $version = '0.2.0';

    public function __construct() {
        $this->loader = new Loader();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    private function define_admin_hooks() {
        $admin = new Admin();
        $this->loader->add_action( 'admin_menu', $admin, 'add_menus' );
        $this->loader->add_action( 'add_meta_boxes', $admin, 'add_meta_boxes' );
        $this->loader->add_action( 'save_post', $admin, 'save_meta_boxes', 10, 2 );
    }

    private function define_public_hooks() {
        $post_types = new Post_Types();
        $this->loader->add_action( 'init', $post_types, 'register' );

        $taxonomies = new Taxonomies();
        $this->loader->add_action( 'init', $taxonomies, 'register' );

        $rest_api = new Rest_Api();
        $this->loader->add_action( 'rest_api_init', $rest_api, 'register_routes' );

        $submission = new Submission();
        $this->loader->add_shortcode( 'ca_civic_submit', $submission, 'render_form' );
        $this->loader->add_action( 'wp_ajax_ca_civic_submit', $submission, 'handle_ajax' );
        $this->loader->add_action( 'wp_ajax_nopriv_ca_civic_submit', $submission, 'handle_ajax' );

        if ( class_exists( __NAMESPACE__ . '\\Photo_Api' ) ) {
            $photo_api = new Photo_Api();
            $this->loader->add_action( 'transition_post_status', $photo_api, 'on_publish', 10, 3 );
        }
    }

    public function run() {
        $this->loader->run();
    }

    public function get_version() {
        return $this->version;
    }
}
