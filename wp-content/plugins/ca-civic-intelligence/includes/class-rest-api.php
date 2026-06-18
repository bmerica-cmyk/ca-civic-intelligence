<?php
namespace CA_Civic_Intel;
if ( ! defined( 'ABSPATH' ) ) exit;

class Rest_Api {
    const NS = 'ca-civic/v1';

    public static function register_routes() {
        register_rest_route( self::NS, '/ingest/brief',  array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'ingest_brief'  ), 'permission_callback' => array( __CLASS__, 'verify_hmac' ) ) );
        register_rest_route( self::NS, '/ingest/event',  array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'ingest_event'  ), 'permission_callback' => array( __CLASS__, 'verify_hmac' ) ) );
        register_rest_route( self::NS, '/ingest/bill',   array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'ingest_bill'   ), 'permission_callback' => array( __CLASS__, 'verify_hmac' ) ) );
        register_rest_route( self::NS, '/ingest/docket', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'ingest_docket' ), 'permission_callback' => array( __CLASS__, 'verify_hmac' ) ) );
    }

    public static function verify_hmac( $request ) {
        $secret = defined( 'CA_CIVIC_HMAC_SECRET' ) ? CA_CIVIC_HMAC_SECRET : '';
        if ( empty( $secret ) ) return new WP_Error( 'no_secret', 'HMAC secret not configured.', array( 'status' => 500 ) );

        $sig  = $request->get_header( 'X-CA-Civic-Sig' );
        $ts   = $request->get_header( 'X-CA-Civic-Ts' );
        $body = $request->get_body();

        if ( empty( $sig ) || empty( $ts ) ) return new WP_Error( 'missing_sig', 'Missing signature headers.', array( 'status' => 401 ) );
        if ( abs( time() - intval( $ts ) ) > 300 ) return new WP_Error( 'expired', 'Request expired.', array( 'status' => 401 ) );

        $expected = hash_hmac( 'sha256', $ts . '.' . $body, $secret );
        if ( ! hash_equals( $expected, $sig ) ) return new WP_Error( 'bad_sig', 'Invalid signature.', array( 'status' => 403 ) );
        return true;
    }

    public static function ingest_brief( $request ) {
        if ( get_option( 'ca_civic_auto_publish_ai', '0' ) === '1' ) {
            return new WP_Error( 'auto_publish_disabled', 'AI auto-publish is permanently disabled.', array( 'status' => 403 ) );
        }
        $params = $request->get_json_params();
        if ( empty( $params['title'] ) || empty( $params['content'] ) ) {
            return new WP_Error( 'missing_fields', 'title and content are required.', array( 'status' => 400 ) );
        }
        $post_id = wp_insert_post( array(
            'post_type'    => 'ca_ai_brief',
            'post_title'   => sanitize_text_field( $params['title'] ),
            'post_content' => wp_kses_post( $params['content'] ),
            'post_status'  => 'draft',
            'post_author'  => 1,
        ), true );
        if ( is_wp_error( $post_id ) ) return $post_id;
        if ( ! empty( $params['sources'] ) && is_array( $params['sources'] ) ) {
            update_post_meta( $post_id, '_ca_civic_sources', array_map( 'esc_url_raw', $params['sources'] ) );
        }
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'ca_civic_ai_log', array(
            'brief_post_id'  => $post_id,
            'prompt_version' => sanitize_text_field( $params['prompt_version'] ?? '' ),
            'prompt_text'    => wp_kses_post( $params['prompt'] ?? '' ),
            'ai_output'      => wp_kses_post( $params['content'] ),
            'model'          => sanitize_text_field( $params['model'] ?? '' ),
        ), array( '%d', '%s', '%s', '%s', '%s' ) );
        return rest_ensure_response( array( 'id' => $post_id, 'status' => 'draft', 'message' => 'Brief created as draft pending editor review.' ) );
    }

    public static function ingest_event( $request ) {
        $params = $request->get_json_params();
        if ( empty( $params['title'] ) ) return new WP_Error( 'missing_title', 'title is required.', array( 'status' => 400 ) );
        $post_id = wp_insert_post( array( 'post_type' => 'ca_public_event', 'post_title' => sanitize_text_field( $params['title'] ), 'post_content' => wp_kses_post( $params['content'] ?? '' ), 'post_status' => 'draft' ), true );
        if ( is_wp_error( $post_id ) ) return $post_id;
        return rest_ensure_response( array( 'id' => $post_id, 'status' => 'draft' ) );
    }

    public static function ingest_bill( $request ) {
        $params = $request->get_json_params();
        if ( empty( $params['title'] ) ) return new WP_Error( 'missing_title', 'title is required.', array( 'status' => 400 ) );
        $post_id = wp_insert_post( array( 'post_type' => 'ca_bill', 'post_title' => sanitize_text_field( $params['title'] ), 'post_content' => wp_kses_post( $params['content'] ?? '' ), 'post_status' => 'draft' ), true );
        if ( is_wp_error( $post_id ) ) return $post_id;
        if ( ! empty( $params['bill_number'] ) ) update_post_meta( $post_id, '_ca_bill_number', sanitize_text_field( $params['bill_number'] ) );
        if ( ! empty( $params['leginfo_url'] ) ) update_post_meta( $post_id, '_ca_leginfo_url', esc_url_raw( $params['leginfo_url'] ) );
        return rest_ensure_response( array( 'id' => $post_id, 'status' => 'draft' ) );
    }

    public static function ingest_docket( $request ) {
        $params = $request->get_json_params();
        if ( empty( $params['title'] ) ) return new WP_Error( 'missing_title', 'title is required.', array( 'status' => 400 ) );
        $post_id = wp_insert_post( array( 'post_type' => 'ca_reg_docket', 'post_title' => sanitize_text_field( $params['title'] ), 'post_content' => wp_kses_post( $params['content'] ?? '' ), 'post_status' => 'draft' ), true );
        if ( is_wp_error( $post_id ) ) return $post_id;
        if ( ! empty( $params['docket_number'] ) ) update_post_meta( $post_id, '_ca_docket_number', sanitize_text_field( $params['docket_number'] ) );
        if ( ! empty( $params['comment_deadline'] ) ) update_post_meta( $post_id, '_ca_comment_deadline', sanitize_text_field( $params['comment_deadline'] ) );
        return rest_ensure_response( array( 'id' => $post_id, 'status' => 'draft' ) );
    }
}
