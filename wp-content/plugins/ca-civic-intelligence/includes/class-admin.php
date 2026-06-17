<?php
namespace CA_Civic_Intel;
if ( ! defined( 'ABSPATH' ) ) exit;

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

        add_submenu_page( 'ca-civic-intel', 'Submissions',    'Submissions',    'edit_posts',     'edit.php?post_type=ca_submission', '' );
        add_submenu_page( 'ca-civic-intel', 'Promotions',     'Promotions',     'edit_posts',     'edit.php?post_type=ca_promotion',  '' );
        add_submenu_page( 'ca-civic-intel', 'Settings',       'Settings',       'manage_options', 'ca-civic-settings',       array( __CLASS__, 'settings_page' ) );
        add_submenu_page( 'ca-civic-intel', 'AI Governance',  'AI Governance',  'manage_options', 'ca-civic-ai-governance',  array( __CLASS__, 'ai_governance_page' ) );
    }

    public static function dashboard_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $auto_publish = get_option( 'ca_civic_auto_publish_ai', '0' );
        echo '<div class="wrap">';
        echo '<h1>California Civic Intelligence</h1>';
        echo '<h2>Platform Status</h2>';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>Setting</th><th>Value</th></tr></thead><tbody>';
        echo '<tr><td>AI Auto-Publish</td><td>' . ( $auto_publish === '1' ? '<span style="color:red">ENABLED (WARNING)</span>' : '<span style="color:green">DISABLED (Safe)</span>' ) . '</td></tr>';
        echo '<tr><td>Plugin Version</td><td>' . esc_html( CA_CIVIC_VERSION ) . '</td></tr>';
        echo '</tbody></table></div>';
    }

    public static function settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        if ( isset( $_POST['ca_civic_save_settings'] ) && check_admin_referer( 'ca_civic_settings_nonce' ) ) {
            update_option( 'ca_civic_auto_publish_ai', '0' );
            echo '<div class="updated"><p>Settings saved. AI auto-publish remains disabled per platform policy.</p></div>';
        }
        echo '<div class="wrap"><h1>CA Civic Intelligence Settings</h1>';
        echo '<form method="post">';
        wp_nonce_field( 'ca_civic_settings_nonce' );
        echo '<table class="form-table"><tr><th>AI Auto-Publish</th><td><strong>Permanently disabled</strong> — AI content always requires editor review. This cannot be changed.</td></tr></table>';
        echo '<p class="submit"><input type="submit" name="ca_civic_save_settings" class="button-primary" value="Save Settings"></p>';
        echo '</form></div>';
    }

    public static function ai_governance_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        global $wpdb;
        $logs = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'ca_civic_ai_log ORDER BY created_at DESC LIMIT %d', 50 ) );
        echo '<div class="wrap"><h1>AI Governance Log</h1>';
        echo '<p>All AI-generated content is logged here. No AI brief can be published without editor review.</p>';
        if ( empty( $logs ) ) {
            echo '<p><em>No AI log entries yet.</em></p>';
        } else {
            echo '<table class="widefat fixed striped"><thead><tr><th>ID</th><th>Brief Post ID</th><th>Model</th><th>Prompt Version</th><th>Created</th></tr></thead><tbody>';
            foreach ( $logs as $log ) {
                echo '<tr><td>' . intval( $log->id ) . '</td><td><a href="' . esc_url( get_edit_post_link( $log->brief_post_id ) ) . '">' . intval( $log->brief_post_id ) . '</a></td><td>' . esc_html( $log->model ) . '</td><td>' . esc_html( $log->prompt_version ) . '</td><td>' . esc_html( $log->created_at ) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
    }
}
