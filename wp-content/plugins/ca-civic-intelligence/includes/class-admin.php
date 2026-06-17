<?php
namespace CA_Civic_Intel;
defined( 'ABSPATH' ) || exit;

class Admin {
    public static function init() {
        add_action( 'admin_menu',            [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_notices',         [ __CLASS__, 'brief_review_notice' ] );
        add_action( 'add_meta_boxes',        [ __CLASS__, 'add_meta_boxes' ] );
        add_action( 'save_post',             [ __CLASS__, 'save_meta' ] );
        add_filter( 'manage_ca_ai_brief_posts_columns',       [ __CLASS__, 'ai_brief_columns' ] );
        add_action( 'manage_ca_ai_brief_posts_custom_column', [ __CLASS__, 'ai_brief_column_content' ], 10, 2 );
        add_filter( 'manage_ca_submission_posts_columns',       [ __CLASS__, 'submission_columns' ] );
        add_action( 'manage_ca_submission_posts_custom_column', [ __CLASS__, 'submission_column_content' ], 10, 2 );
    }

    public static function add_menu() {
        add_menu_page(
            'CA Civic Intelligence', 'CA Civic', 'edit_posts',
            'ca-civic-dashboard', [ __CLASS__, 'render_dashboard' ],
            'dashicons-flag', 3
        );
        add_submenu_page( 'ca-civic-dashboard', 'Settings', 'Settings', 'manage_options', 'ca-civic-settings', [ __CLASS__, 'render_settings' ] );
    }

    public static function render_dashboard() {
        if ( ! current_user_can( 'edit_posts' ) ) wp_die( 'Access denied.' );
        global $wpdb;
        $pending_briefs = wp_count_posts('ca_ai_brief')->draft ?? 0;
        $pending_subs   = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ca_submissions WHERE status='pending'" );
        ?>
        <div class="wrap">
            <h1>California Civic Intelligence</h1>
            <div style="display:flex;gap:20px;flex-wrap:wrap;margin-top:20px;">
                <div class="card" style="min-width:200px;">
                    <h2 style="font-size:14px;">AI Briefs Pending Review</h2>
                    <p style="font-size:36px;font-weight:bold;margin:0;"><?php echo esc_html($pending_briefs); ?></p>
                    <a href="<?php echo admin_url('edit.php?post_type=ca_ai_brief&post_status=draft'); ?>">Review Briefs</a>
                </div>
                <div class="card" style="min-width:200px;">
                    <h2 style="font-size:14px;">Opinion Submissions Pending</h2>
                    <p style="font-size:36px;font-weight:bold;margin:0;"><?php echo esc_html($pending_subs); ?></p>
                    <a href="<?php echo admin_url('edit.php?post_type=ca_submission'); ?>">Review Submissions</a>
                </div>
            </div>
            <hr>
            <p style="color:#666;font-size:12px;"><strong>AI Governance:</strong> No AI-assisted content may be published without editor review and approval.</p>
        </div>
        <?php
    }

    public static function render_settings() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Access denied.' );
        if ( isset($_POST['ca_civic_save_settings']) ) {
            check_admin_referer('ca_civic_settings');
            update_option('ca_civic_editorial_email', sanitize_email($_POST['ca_civic_editorial_email'] ?? ''));
            update_option('ca_civic_openai_model',    sanitize_text_field($_POST['ca_civic_openai_model'] ?? 'gpt-4o'));
            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }
        ?>
        <div class="wrap"><h1>CA Civic Intelligence Settings</h1>
        <form method="post"><?php wp_nonce_field('ca_civic_settings'); ?>
        <table class="form-table">
            <tr><th>Editorial Email</th><td><input type="email" name="ca_civic_editorial_email" value="<?php echo esc_attr(get_option('ca_civic_editorial_email')); ?>" class="regular-text"></td></tr>
            <tr><th>OpenAI Model</th><td>
                <select name="ca_civic_openai_model">
                    <?php foreach(['gpt-4o','gpt-4o-mini','gpt-4-turbo'] as $m): ?>
                    <option value="<?php echo esc_attr($m); ?>" <?php selected(get_option('ca_civic_openai_model'), $m); ?>><?php echo esc_html($m); ?></option>
                    <?php endforeach; ?>
                </select>
            </td></tr>
            <tr><th>Auto-Publish AI</th><td><strong style="color:red;">DISABLED — requires editor approval always.</strong></td></tr>
        </table>
        <p class="submit"><input type="submit" name="ca_civic_save_settings" class="button-primary" value="Save Settings"></p>
        </form></div>
        <?php
    }

    public static function add_meta_boxes() {
        add_meta_box( 'ca_ai_brief_meta', 'AI Brief Details', [ __CLASS__, 'render_ai_brief_meta' ], 'ca_ai_brief', 'side', 'high' );
        add_meta_box( 'ca_opinion_disclosure', 'Author Disclosure', [ __CLASS__, 'render_opinion_disclosure' ], 'ca_opinion', 'side', 'high' );
    }

    public static function render_ai_brief_meta( $post ) {
        wp_nonce_field( 'ca_ai_brief_meta', 'ca_ai_brief_meta_nonce' );
        $reviewed   = get_post_meta($post->ID, '_ca_ai_reviewed', true);
        $source_url = get_post_meta($post->ID, '_ca_source_url', true);
        $source_slug= get_post_meta($post->ID, '_ca_source_slug', true);
        ?>
        <p><label><input type="checkbox" name="ca_ai_reviewed" value="1" <?php checked($reviewed, '1'); ?>>
        <strong>Editor has reviewed this AI brief</strong></label></p>
        <p style="color:#666;font-size:11px;">Check before publishing.</p>
        <?php if ($source_url): ?>
        <p><strong>Source:</strong><br><a href="<?php echo esc_url($source_url); ?>" target="_blank"><?php echo esc_html($source_slug ?: $source_url); ?></a></p>
        <?php endif; ?>
        <?php
    }

    public static function render_opinion_disclosure( $post ) {
        wp_nonce_field( 'ca_opinion_disclosure', 'ca_opinion_disclosure_nonce' );
        $org    = get_post_meta($post->ID, '_ca_author_org', true);
        $role   = get_post_meta($post->ID, '_ca_author_title', true);
        $client = get_post_meta($post->ID, '_ca_client_disclosed', true);
        ?>
        <p><label>Organization:<br><input type="text" name="ca_author_org" value="<?php echo esc_attr($org); ?>" class="widefat"></label></p>
        <p><label>Title/Role:<br><input type="text" name="ca_author_title" value="<?php echo esc_attr($role); ?>" class="widefat"></label></p>
        <p><label>Client Disclosure:<br><textarea name="ca_client_disclosed" class="widefat" rows="3"><?php echo esc_textarea($client); ?></textarea></label></p>
        <?php
    }

    public static function save_meta( $post_id ) {
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        if ( isset($_POST['ca_ai_brief_meta_nonce']) && wp_verify_nonce($_POST['ca_ai_brief_meta_nonce'],'ca_ai_brief_meta') ) {
            $reviewed = isset($_POST['ca_ai_reviewed']) ? '1' : '0';
            update_post_meta( $post_id, '_ca_ai_reviewed', $reviewed );
            if ( get_post_type($post_id) === 'ca_ai_brief' && $reviewed !== '1' && get_post_status($post_id) === 'publish' ) {
                wp_update_post(['ID' => $post_id, 'post_status' => 'draft']);
                add_filter('redirect_post_location', function($loc) {
                    return add_query_arg('ca_civic_error', 'not_reviewed', $loc);
                });
            }
        }

        if ( isset($_POST['ca_opinion_disclosure_nonce']) && wp_verify_nonce($_POST['ca_opinion_disclosure_nonce'],'ca_opinion_disclosure') ) {
            update_post_meta( $post_id, '_ca_author_org',       sanitize_text_field($_POST['ca_author_org'] ?? '') );
            update_post_meta( $post_id, '_ca_author_title',     sanitize_text_field($_POST['ca_author_title'] ?? '') );
            update_post_meta( $post_id, '_ca_client_disclosed', sanitize_textarea_field($_POST['ca_client_disclosed'] ?? '') );
        }
    }

    public static function brief_review_notice() {
        if ( ! empty($_GET['ca_civic_error']) && $_GET['ca_civic_error'] === 'not_reviewed' ) {
            echo '<div class="notice notice-error"><p><strong>CA Civic:</strong> This AI brief cannot be published until you check the "Editor has reviewed" checkbox.</p></div>';
        }
    }

    public static function ai_brief_columns( $cols ) {
        $new = [];
        foreach ( $cols as $k => $v ) {
            $new[$k] = $v;
            if ( $k === 'title' ) {
                $new['ca_ai_reviewed'] = 'Reviewed';
                $new['ca_source_slug'] = 'Source';
            }
        }
        return $new;
    }

    public static function ai_brief_column_content( $col, $post_id ) {
        if ( $col === 'ca_ai_reviewed' ) echo get_post_meta($post_id,'_ca_ai_reviewed',true) === '1' ? '<span style="color:green;">&#10003;</span>' : '<span style="color:red;">&#9888;</span>';
        if ( $col === 'ca_source_slug' ) echo esc_html( get_post_meta($post_id,'_ca_source_slug',true) );
    }

    public static function submission_columns( $cols ) {
        $cols['submitter_name'] = 'Submitter';
        $cols['submitter_org']  = 'Organization';
        $cols['sub_status']     = 'Status';
        return $cols;
    }

    public static function submission_column_content( $col, $post_id ) {
        global $wpdb;
        $sub = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$wpdb->prefix}ca_submissions WHERE post_id=%d", $post_id) );
        if ( ! $sub ) return;
        if ( $col === 'submitter_name' ) echo esc_html( $sub->submitter_name );
        if ( $col === 'submitter_org'  ) echo esc_html( $sub->submitter_org );
        if ( $col === 'sub_status'     ) echo esc_html( $sub->status );
    }
}
