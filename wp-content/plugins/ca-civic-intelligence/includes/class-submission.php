<?php
namespace CA_Civic_Intel;
if ( ! defined( 'ABSPATH' ) ) exit;

class Submission {
    public static function register_shortcode() {
        add_shortcode( 'ca_submission_form', array( __CLASS__, 'render_form' ) );
    }

    public static function render_form( $atts ) {
        if ( isset( $_POST['ca_civic_submit'] ) ) {
            return self::handle_submission();
        }
        return self::form_html();
    }

    private static function form_html() {
        $nonce = wp_create_nonce( 'ca_civic_submission' );
        $out   = '<div class="ca-civic-submission-form">';
        $out  .= '<h2>Submit an Opinion or Commentary</h2>';
        $out  .= '<p>We welcome submissions from California public affairs professionals. All submissions are reviewed by our editorial team.</p>';
        $out  .= '<form method="post">';
        $out  .= '<input type="hidden" name="ca_civic_nonce" value="' . esc_attr( $nonce ) . '">';
        $out  .= '<p><label>Your Full Name *<br><input type="text" name="ca_sub_name" required style="width:100%"></label></p>';
        $out  .= '<p><label>Email Address *<br><input type="email" name="ca_sub_email" required style="width:100%"></label></p>';
        $out  .= '<p><label>Organization / Affiliation<br><input type="text" name="ca_sub_org" style="width:100%"></label></p>';
        $out  .= '<p><label>Submission Title *<br><input type="text" name="ca_sub_title" required style="width:100%"></label></p>';
        $out  .= '<p><label>Your Submission *<br><textarea name="ca_sub_body" rows="12" required style="width:100%"></textarea></label></p>';
        $out  .= '<p><label>Client / Funder Disclosure<br><input type="text" name="ca_sub_disclosure" style="width:100%"></label></p>';
        $out  .= '<p><label><input type="checkbox" name="ca_sub_agree" required> I confirm this is my original work and I accept the editorial terms.</label></p>';
        $out  .= '<p><button type="submit" name="ca_civic_submit" value="1">Submit for Review</button></p>';
        $out  .= '</form></div>';
        return $out;
    }

    private static function handle_submission() {
        if ( ! isset( $_POST['ca_civic_nonce'] ) || ! wp_verify_nonce( $_POST['ca_civic_nonce'], 'ca_civic_submission' ) ) {
            return '<p>Security check failed. Please try again.</p>';
        }
        $name  = sanitize_text_field( $_POST['ca_sub_name'] ?? '' );
        $email = sanitize_email( $_POST['ca_sub_email'] ?? '' );
        $org   = sanitize_text_field( $_POST['ca_sub_org'] ?? '' );
        $title = sanitize_text_field( $_POST['ca_sub_title'] ?? '' );
        $body  = wp_kses_post( $_POST['ca_sub_body'] ?? '' );
        $disc  = sanitize_text_field( $_POST['ca_sub_disclosure'] ?? '' );
        if ( empty( $name ) || empty( $email ) || empty( $title ) || empty( $body ) ) {
            return '<p>Please fill in all required fields.</p>';
        }
        $post_id = wp_insert_post( array(
            'post_type'    => 'ca_submission',
            'post_title'   => $title,
            'post_content' => $body,
            'post_status'  => 'private',
            'post_author'  => 1,
        ), true );
        if ( is_wp_error( $post_id ) ) return '<p>Error saving submission. Please try again.</p>';
        update_post_meta( $post_id, '_ca_submitter_name',  $name );
        update_post_meta( $post_id, '_ca_submitter_email', $email );
        update_post_meta( $post_id, '_ca_submitter_org',   $org );
        update_post_meta( $post_id, '_ca_submitter_disclosure', $disc );
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'ca_civic_submissions', array(
            'submission_post_id' => $post_id,
            'submitter_name'     => $name,
            'submitter_email'    => $email,
            'submitter_org'      => $org,
            'status'             => 'pending',
        ), array( '%d', '%s', '%s', '%s', '%s' ) );
        wp_mail( get_option( 'admin_email' ), 'New Submission: ' . $title, 'From: ' . $name . ' <' . $email . '>' );
        return '<div><h3>Thank you!</h3><p>Your submission has been received. We will be in touch at ' . esc_html( $email ) . '.</p></div>';
    }
}
