<?php
/**
 * Register all actions and filters for the plugin.
 *
 * @package CA_Civic_Intel
 */

namespace CA_Civic_Intel;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Loader: collects hooks and runs them all at once.
 */
class Loader {

    protected $actions = [];
    protected $filters = [];
    protected $shortcodes = [];

    public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
    }

    public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
    }

    public function add_shortcode( $tag, $component, $callback ) {
        $this->shortcodes[] = compact( 'tag', 'component', 'callback' );
    }

    public function run() {
        foreach ( $this->filters as $hook ) {
            add_filter( $hook['hook'], [ $hook['component'], $hook['callback'] ], $hook['priority'], $hook['accepted_args'] );
        }
        foreach ( $this->actions as $hook ) {
            add_action( $hook['hook'], [ $hook['component'], $hook['callback'] ], $hook['priority'], $hook['accepted_args'] );
        }
        foreach ( $this->shortcodes as $sc ) {
            add_shortcode( $sc['tag'], [ $sc['component'], $sc['callback'] ] );
        }
    }
}
