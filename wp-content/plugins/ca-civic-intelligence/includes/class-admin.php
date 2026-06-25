<?php
/**
 * Admin class — menus, meta boxes, and dashboard.
 *
 * @package CA_Civic_Intel
 */

namespace CA_Civic_Intel;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin class.
 * Registers admin menus, meta boxes, and settings pages.
 */
class Admin {

    public static function add_menus() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        add_menu_page(
            'CA Civic Intelligence',
            'CA Civic',
            'manage_options',
            'ca-civic-intel',
            array( __CLASS__, 'dashboard_page' ),
            'dashicons-flag',
            30
        );

        add_submenu_page( 'ca-civic-intel', 'Submissions',   'Submissions',   'edit_posts',     'edit.php?post_type=ca_submission',  '' );
        add_submenu_page( 'ca-civic-intel', 'Promotions',    'Promotions',    'edit_posts',     'edit.php?post_type=ca_promotion',   '' );
        add_submenu_page( 'ca-civic-intel', 'Settings',      'Settings',      'manage_options', 'ca-civic-settings',      array( __CLASS__, 'settings_page' ) );
        add_submenu_page( 'ca-civic-intel', 'AI Governance', 'AI Governance', 'manage_options', 'ca-civic-ai-governance', array( __CLASS__, 'ai_governance_page' ) );
    }

    /**
     * Main dashboard page — delegates to Dashboard class for rich metrics.
     */
    public static function dashboard_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Access denied' );
        }

        // Use the richer Dashboard class if available
        if ( class_exists( __NAMESPACE__ . '\\Dashboard' ) ) {
            Dashboard::dashboard_page();
            return;
        }

        // Fallback: basic platform status
        $auto_publish = get_option( 'ca_civic_auto_publish_ai', '0' );
        $safety_status = ( $auto_publish === '0' ) ? '<span style="color:green">DISABLED (Safe)</span>' : '<span style="color:red">ENABLED (WARNING)</span>';
        echo '<div class="wrap">';
        echo '<h1>California Civic Intelligence</h1>';
        echo '<h2>Platform Status</h2>';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>Setting</th><th>Value</th></tr></thead><tbody>';
        echo '<tr><td>AI Auto-Publish</td><td>' . $safety_status . '</td></tr>';
        echo '<tr><td>Plugin Version</td><td>0.2.0</td></tr>';
        echo '</tbody></table>';
        echo '</div>';
    }

    public static function add_meta_boxes() {
        $post_types = array( 'ca_opinion', 'ca_ai_brief', 'ca_explainer', 'ca_bill', 'ca_reg_docket', 'ca_agency', 'ca_submission', 'ca_promotion', 'ca_public_event' );
        foreach ( $post_types as $pt ) {
            add_meta_box( 'ca_civic_meta', 'CA Civic Fields', array( __CLASS__, 'render_meta_box' ), $pt, 'side' );
        }
    }

    public static function render_meta_box( $post ) {
        wp_nonce_field( 'ca_civic_meta_nonce', 'ca_civic_nonce' );
        $reviewed   = get_post_meta( $post->ID, '_ca_ai_reviewed', true );
        $source_url = get_post_meta( $post->ID, '_ca_source_url', true );
        $source_label = get_post_meta( $post->ID, '_ca_source_label', true );
        echo '<p><label><input type="checkbox" name="_ca_ai_reviewed" value="1" ' . checked( $reviewed, '1', false ) . '> AI Reviewed</label></p>';
        echo '<p><label>Source URL<br><input type="url" name="_ca_source_url" value="' . esc_attr( $source_url ) . '" style="width:100%"></label></p>';
        echo '<p><label>Source Label<br><input type="text" name="_ca_source_label" value="' . esc_attr( $source_label ) . '" style="width:100%"></label></p>';
    }

    public static function save_meta_boxes( $post_id, $post ) {
        if ( ! isset( $_POST['ca_civic_nonce'] ) || ! wp_verify_nonce( $_POST['ca_civic_nonce'], 'ca_civic_meta_nonce' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $reviewed   = isset( $_POST['_ca_ai_reviewed'] ) ? '1' : '0';
        $source_url = isset( $_POST['_ca_source_url'] ) ? esc_url_raw( $_POST['_ca_source_url'] ) : '';
        $source_label = isset( $_POST['_ca_source_label'] ) ? sanitize_text_field( $_POST['_ca_source_label'] ) : '';

        update_post_meta( $post_id, '_ca_ai_reviewed', $reviewed );
        update_post_meta( $post_id, '_ca_source_url', $source_url );
        update_post_meta( $post_id, '_ca_source_label', $source_label );
    }

    public static function settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        echo '<div class="wrap"><h1>CA Civic Settings</h1><p>Configure API keys and operational settings in <code>wp-config.php</code>.</p></div>';
    }

    public static function ai_governance_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $auto_publish = get_option( 'ca_civic_auto_publish_ai', '0' );
        echo '<div class="wrap"><h1>AI Governance</h1>';
        echo '<p><strong>Auto-Publish AI Content:</strong> ' . ( $auto_publish === '0' ? '<strong style="color:green">DISABLED</strong> (safe — all AI content goes to drafts)' : '<strong style="color:red">ENABLED</strong>' ) . '</p>';
        echo '<p>The <code>ca_civic_auto_publish_ai</code> option is permanently set to <code>0</code> and cannot be changed via the UI. This ensures human editorial review for all AI-generated content.</p>';
        echo '</div>';
    }
}
