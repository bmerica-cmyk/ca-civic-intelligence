<?php
namespace CA_Civic_Intel;
defined( 'ABSPATH' ) || exit;

class REST_API {
    const NS = 'ca-civic/v1';

    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes() {
        register_rest_route( self::NS, '/ingest', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_ingest' ],
            'permission_callback' => [ __CLASS__, 'verify_hmac' ],
            'args'                => [
                'source_slug' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ],
                'source_url'  => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'esc_url_raw' ],
                'title'       => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                'raw_content' => [ 'required' => true, 'type' => 'string' ],
                'draft_brief' => [ 'required' => false, 'type' => 'string' ],
                'source_date' => [ 'required' => false, 'type' => 'string' ],
                'sources'     => [ 'required' => false, 'type' => 'array' ],
            ],
        ] );

        register_rest_route( self::NS, '/queue', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_queue_status' ],
            'permission_callback' => [ __CLASS__, 'verify_worker_token' ],
        ] );

        register_rest_route( self::NS, '/submissions/count', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_submission_count' ],
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
        ] );

        register_rest_route( self::NS, '/health', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'health_check' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public static function verify_hmac( $request ) {
        $secret = defined('CA_CIVIC_INGESTION_HMAC_SECRET') ? CA_CIVIC_INGESTION_HMAC_SECRET : '';
        if ( empty( $secret ) ) {
            return new \WP_Error( 'no_hmac_secret', 'Ingestion HMAC secret not configured.', [ 'status' => 500 ] );
        }
        $sig_header = $request->get_header('X-CA-Civic-Signature');
        if ( ! $sig_header ) {
            return new \WP_Error( 'missing_signature', 'Missing X-CA-Civic-Signature header.', [ 'status' => 401 ] );
        }
        $raw_body = $request->get_body();
        $expected = 'sha256=' . hash_hmac( 'sha256', $raw_body, $secret );
        if ( ! hash_equals( $expected, $sig_header ) ) {
            return new \WP_Error( 'invalid_signature', 'Signature mismatch.', [ 'status' => 403 ] );
        }
        return true;
    }

    public static function verify_worker_token( $request ) {
        $token = defined('CA_CIVIC_EXTERNAL_WORKER_TOKEN') ? CA_CIVIC_EXTERNAL_WORKER_TOKEN : '';
        if ( empty( $token ) ) {
            return new \WP_Error( 'no_worker_token', 'Worker token not configured.', [ 'status' => 500 ] );
        }
        $auth = $request->get_header('Authorization');
        if ( ! $auth || ! hash_equals( 'Bearer ' . $token, $auth ) ) {
            return new \WP_Error( 'unauthorized', 'Invalid worker token.', [ 'status' => 401 ] );
        }
        return true;
    }

    public static function handle_ingest( $request ) {
        global $wpdb;
        $source_slug = $request->get_param('source_slug');
        $source_url  = $request->get_param('source_url');
        $title       = $request->get_param('title');
        $raw_content = $request->get_param('raw_content');
        $draft_brief = $request->get_param('draft_brief') ?? '';
        $source_date = $request->get_param('source_date') ?? '';
        $sources     = $request->get_param('sources')    ?? [];

        // ALWAYS create as draft - NEVER auto-publish AI content
        $post_id = wp_insert_post( [
            'post_title'   => $title,
            'post_content' => wp_kses_post( $draft_brief ),
            'post_status'  => 'draft',
            'post_type'    => 'ca_ai_brief',
            'meta_input'   => [
                '_ca_source_slug'  => sanitize_key( $source_slug ),
                '_ca_source_url'   => esc_url_raw( $source_url ),
                '_ca_source_date'  => sanitize_text_field( $source_date ),
                '_ca_raw_content'  => wp_kses_post( $raw_content ),
                '_ca_ai_generated' => '1',
                '_ca_ai_reviewed'  => '0',
            ],
        ], true );

        if ( is_wp_error( $post_id ) ) {
            return new \WP_REST_Response( [ 'error' => $post_id->get_error_message() ], 500 );
        }

        foreach ( $sources as $src ) {
            $wpdb->insert(
                $wpdb->prefix . 'ca_brief_sources',
                [
                    'brief_id'    => $post_id,
                    'source_url'  => isset($src['url'])   ? esc_url_raw($src['url'])           : '',
                    'source_title'=> isset($src['title']) ? sanitize_text_field($src['title']) : '',
                    'source_date' => isset($src['date'])  ? sanitize_text_field($src['date'])  : null,
                    'agency_slug' => isset($src['agency'])? sanitize_key($src['agency'])       : '',
                ],
                [ '%d','%s','%s','%s','%s' ]
            );
        }

        $wpdb->insert(
            $wpdb->prefix . 'ca_ingestion_queue',
            [
                'source_slug'  => $source_slug,
                'source_url'   => $source_url,
                'raw_content'  => $raw_content,
                'status'       => 'done',
                'processed_at' => current_time('mysql'),
            ],
            [ '%s','%s','%s','%s','%s' ]
        );

        $editorial_email = get_option('ca_civic_editorial_email', get_option('admin_email'));
        wp_mail(
            $editorial_email,
            '[CA Civic] New AI Brief Draft Ready: ' . $title,
            "A new AI-assisted civic brief needs editorial review.\n\n" .
            "Title: $title\nSource: $source_url\n\n" .
            "Review: " . admin_url("post.php?post=$post_id&action=edit") . "\n\n" .
            "REMINDER: This is AI-assisted content. It MUST be reviewed before publication."
        );

        return new \WP_REST_Response( [
            'success' => true,
            'post_id' => $post_id,
            'status'  => 'draft',
            'message' => 'Draft created for editorial review. AI content will NOT publish until approved.',
        ], 201 );
    }

    public static function get_queue_status( $request ) {
        global $wpdb;
        $table  = $wpdb->prefix . 'ca_ingestion_queue';
        $counts = $wpdb->get_results( "SELECT status, COUNT(*) as cnt FROM {$table} GROUP BY status", ARRAY_A );
        return new \WP_REST_Response( [ 'queue' => $counts ], 200 );
    }

    public static function get_submission_count( $request ) {
        global $wpdb;
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ca_submissions WHERE status = 'pending'" );
        return new \WP_REST_Response( [ 'pending' => (int) $count ], 200 );
    }

    public static function health_check( $request ) {
        return new \WP_REST_Response( [
            'status'  => 'ok',
            'version' => CA_CIVIC_VERSION,
            'time'    => current_time('c'),
        ], 200 );
    }
}
