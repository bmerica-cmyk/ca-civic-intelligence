<?php
namespace CA_Civic_Intel;
defined( 'ABSPATH' ) || exit;

class Submission {
    public static function init() {
        add_shortcode( 'ca_submission_form', [ __CLASS__, 'render_form' ] );
        add_action( 'wp_ajax_nopriv_ca_submit_opinion', [ __CLASS__, 'process_submission' ] );
        add_action( 'wp_ajax_ca_submit_opinion',        [ __CLASS__, 'process_submission' ] );
        add_action( 'wp_enqueue_scripts',               [ __CLASS__, 'enqueue_scripts' ] );
    }

    public static function enqueue_scripts() {
        global $post;
        if ( $post && has_shortcode( $post->post_content, 'ca_submission_form' ) ) {
            wp_enqueue_script( 'ca-civic-submission', CA_CIVIC_PLUGIN_URL . 'assets/js/submission.js', ['jquery'], CA_CIVIC_VERSION, true );
            wp_localize_script( 'ca-civic-submission', 'caCivicAjax', [
                'url'   => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ca_submit_opinion'),
            ] );
        }
    }

    public static function render_form( $atts ) {
        ob_start();
        ?>
        <div class="ca-civic-submission-form">
        <form id="ca-opinion-form" method="post">
            <?php wp_nonce_field('ca_submit_opinion','ca_submit_nonce'); ?>
            <input type="hidden" name="action" value="ca_submit_opinion">
            <h3>Submit a Commentary or Analysis</h3>
            <p style="color:#555;font-size:14px;">We accept commentary from California advocates, officials, attorneys, academics, and others with relevant expertise. All submissions are editorially reviewed. Submission does not guarantee publication.</p>
            <p><label>Your Name *<br><input type="text" name="submitter_name" required style="max-width:400px;width:100%;"></label></p>
            <p><label>Email Address *<br><input type="email" name="submitter_email" required style="max-width:400px;width:100%;"></label></p>
            <p><label>Organization / Affiliation *<br><input type="text" name="submitter_org" required style="max-width:400px;width:100%;"></label></p>
            <p><label>Your Title / Role<br><input type="text" name="submitter_title" style="max-width:400px;width:100%;"></label></p>
            <p><label>Client or Funding Disclosure (required if applicable)<br><textarea name="client_disclosed" rows="3" style="max-width:600px;width:100%;"></textarea></label></p>
            <hr>
            <p><label>Headline / Title *<br><input type="text" name="submission_title" required style="max-width:600px;width:100%;"></label></p>
            <p><label>Your Commentary *<br><textarea name="submission_content" required rows="15" style="max-width:700px;width:100%;"></textarea></label></p>
            <p style="font-size:12px;color:#555;">By submitting, you confirm this is original work and consent to editing for length, style, and clarity.</p>
            <p><button type="submit" class="wp-element-button">Submit for Editorial Review</button></p>
            <div id="ca-submission-result"></div>
        </form></div>
        <?php
        return ob_get_clean();
    }

    public static function process_submission() {
        check_ajax_referer( 'ca_submit_opinion', 'ca_submit_nonce' );

        $name     = sanitize_text_field( $_POST['submitter_name']     ?? '' );
        $email    = sanitize_email(      $_POST['submitter_email']    ?? '' );
        $org      = sanitize_text_field( $_POST['submitter_org']      ?? '' );
        $title    = sanitize_text_field( $_POST['submitter_title']    ?? '' );
        $client   = sanitize_textarea_field( $_POST['client_disclosed'] ?? '' );
        $sub_title= sanitize_text_field( $_POST['submission_title']   ?? '' );
        $content  = wp_kses_post(        $_POST['submission_content'] ?? '' );

        if ( ! $name || ! $email || ! $org || ! $sub_title || ! $content ) {
            wp_send_json_error( [ 'message' => 'Please fill in all required fields.' ] );
        }

        if ( ! is_email($email) ) {
            wp_send_json_error( [ 'message' => 'Please enter a valid email address.' ] );
        }

        $post_id = wp_insert_post( [
            'post_title'   => $sub_title,
            'post_content' => $content,
            'post_status'  => 'draft',
            'post_type'    => 'ca_submission',
            'meta_input'   => [
                '_ca_submitter_name'  => $name,
                '_ca_submitter_email' => $email,
                '_ca_submitter_org'   => $org,
                '_ca_submitter_title' => $title,
                '_ca_client_disclosed'=> $client,
            ],
        ], true );

        if ( is_wp_error($post_id) ) {
            wp_send_json_error( [ 'message' => 'Server error. Please try again.' ] );
        }

        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'ca_submissions',
            [
                'post_id'          => $post_id,
                'submitter_name'   => $name,
                'submitter_email'  => $email,
                'submitter_org'    => $org,
                'submitter_title'  => $title,
                'client_disclosed' => $client,
                'status'           => 'pending',
                'ip_address'       => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
            ],
            [ '%d','%s','%s','%s','%s','%s','%s','%s' ]
        );

        $editorial_email = get_option('ca_civic_editorial_email', get_option('admin_email'));
        wp_mail( $editorial_email,
            '[CA Civic] New Opinion Submission: ' . $sub_title,
            "Name: $name\nEmail: $email\nOrg: $org\n\nReview: " . admin_url("post.php?post=$post_id&action=edit")
        );

        wp_mail( $email,
            'Your submission to California Civic Intelligence has been received',
            "Dear $name,\n\nThank you for submitting: \"$sub_title\"\n\nOur editorial team will review it and contact you if we decide to publish.\n\nCalifornia Civic Intelligence Editorial Team"
        );

        wp_send_json_success( [ 'message' => 'Thank you! Your submission has been received. We will contact you if we decide to publish your piece.' ] );
    }
}
