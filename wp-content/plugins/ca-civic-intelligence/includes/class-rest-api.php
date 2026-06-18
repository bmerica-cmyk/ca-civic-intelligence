<?php
namespace CA_Civic_Intel;

if ( ! defined( 'ABSPATH' ) ) exit;

class REST_API {

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes() {
        $ns = 'ca-civic/v1';

        register_rest_route( $ns, '/status', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'status' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( $ns, '/ingest/record', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'ingest_record' ),
            'permission_callback' => array( __CLASS__, 'check_hmac' ),
        ) );

        foreach ( array( 'event', 'bill', 'docket', 'brief' ) as $leg ) {
            register_rest_route( $ns, '/ingest/' . $leg, array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'ingest_record' ),
                'permission_callback' => array( __CLASS__, 'check_hmac' ),
            ) );
        }

        register_rest_route( $ns, '/submission/(?P<id>[0-9]+)', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_submission_status' ),
            'permission_callback' => array( __CLASS__, 'check_hmac' ),
            'args'                => array( 'id' => array( 'validate_callback' => 'is_numeric' ) ),
        ) );
    }

    public static function status( $request ) {
        return rest_ensure_response( array(
            'status'          => 'ok',
            'plugin_version'  => CA_CIVIC_VERSION,
            'auto_publish_ai' => get_option( 'ca_civic_auto_publish_ai', '0' ),
            'wp_version'      => get_bloginfo( 'version' ),
            'timestamp'       => gmdate( 'c' ),
        ) );
    }

    public static function ingest_record( $request ) {
        $params = $request->get_json_params();
        if ( empty( $params ) ) {
            return new WP_Error( 'no_data', 'No JSON body provided.', array( 'status' => 400 ) );
        }
        $type  = isset( $params['record_type'] ) ? sanitize_text_field( $params['record_type'] ) : 'ca_ai_brief';
        $types = array( 'ca_ai_brief', 'ca_public_event', 'ca_bill', 'ca_reg_docket' );
        if ( ! in_array( $type, $types, true ) ) {
            return new WP_Error( 'invalid_type', 'Invalid record_type.', array( 'status' => 400 ) );
        }
        $title   = isset( $params['title'] )      ? sanitize_text_field( $params['title'] ) : '';
        $content = isset( $params['content'] )    ? wp_kses_post( $params['content'] )      : '';
        $source  = isset( $params['source_url'] ) ? esc_url_raw( $params['source_url'] )    : '';
        if ( empty( $title ) ) {
            return new WP_Error( 'no_title', 'Title is required.', array( 'status' => 400 ) );
        }
        $post_id = wp_insert_post( array(
            'post_type' => $type, 'post_title' => $title,
            'post_content' => $content, 'post_status' => 'draft',
        ), true );
        if ( is_wp_error( $post_id ) ) { return $post_id; }
        if ( $source ) { update_post_meta( $post_id, '_ca_source_url', $source ); }
        update_post_meta( $post_id, '_ca_ai_reviewed', '0' );
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'ca_civic_ingestion_log', array(
            'source_name' => isset( $params['source_name'] ) ? sanitize_text_field( $params['source_name'] ) : 'api',
            'source_url' => $source, 'record_type' => $type,
            'raw_payload' => wp_json_encode( $params ), 'post_id' => $post_id,
            'status' => 'ingested', 'ingested_at' => current_time( 'mysql', true ),
        ), array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' ) );
        return rest_ensure_response( array( 'success' => true, 'post_id' => $post_id, 'post_status' => 'draft', 'message' => 'Ingested as draft.' ) );
    }

    public static function get_submission_status( $request ) {
        $id = (int) $request['id']; $post = get_post( $id );
        if ( ! $post || $post->post_type !== 'ca_submission' ) {
            return new WP_Error( 'not_found', 'Not found.', array( 'status' => 404 ) );
        }
        return rest_ensure_response( array( 'id' => $id, 'status' => $post->post_status, 'date' => $post->post_date_gmt ) );
    }

    public static function check_hmac( $request ) {
        $secret = defined( 'CA_CIVIC_HMAC_SECRET' ) ? CA_CIVIC_HMAC_SECRET : '';
        if ( empty( $secret ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { return true; }
            return new WP_Error( 'not_configured', 'HMAC secret not configured.', array( 'status' => 503 ) );
        }
        $sig = $request->get_header( 'X-CA-Civic-Signature' );
        if ( empty( $sig ) ) {
            return new WP_Error( 'missing_signature', 'Missing sig.', array( 'status' => 401 ) );
        }
        $expected = 'sha256=' . hash_hmac( 'sha256', $request->get_body(), $secret );
        if ( ! hash_equals( $expected, $sig ) ) {
            return new WP_Error( 'invalid_signature', 'Invalid HMAC.', array( 'status' => 401 ) );
        }
        return true;
    }
}
