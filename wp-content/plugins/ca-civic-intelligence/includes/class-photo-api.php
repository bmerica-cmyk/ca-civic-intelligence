<?php
/**
 * Photo API integration — Pexels stock photo auto-assignment.
 *
 * Requires: define( 'CA_CIVIC_PEXELS_KEY', 'your-key' ); in wp-config.php
 *
 * @package CA_Civic_Intel
 */

namespace CA_Civic_Intel;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Photo_Api class.
 * Fetches a relevant Pexels stock photo and assigns it as
 * the featured image for qualifying posts on publish.
 */
class Photo_Api {

    const PEXELS_API = 'https://api.pexels.com/v1/search';

    /** Post types that get auto-assigned photos. */
    const PHOTO_POST_TYPES = [ 'ca_opinion', 'ca_explainer', 'ca_ai_brief', 'ca_bill', 'ca_reg_docket' ];

    /**
     * Hook: fires when a post transitions status.
     * Schedules a single cron event to do the photo fetch asynchronously.
     */
    public function on_publish( $new_status, $old_status, $post ) {
        if ( $new_status !== 'publish' ) {
            return;
        }
        if ( ! in_array( $post->post_type, self::PHOTO_POST_TYPES, true ) ) {
            return;
        }
        if ( has_post_thumbnail( $post->ID ) ) {
            return; // Already has a photo
        }
        // Schedule async fetch
        if ( ! wp_next_scheduled( 'ca_civic_assign_photo', [ $post->ID ] ) ) {
            wp_schedule_single_event( time() + 5, 'ca_civic_assign_photo', [ $post->ID ] );
        }
    }

    /**
     * Build a search query from the post.
     * Uses title keywords + primary taxonomy term if available.
     */
    public static function build_query_for_post( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return 'california government';
        }

        // Try issue_area taxonomy first
        $terms = get_the_terms( $post_id, 'ca_issue_area' );
        if ( $terms && ! is_wp_error( $terms ) ) {
            return sanitize_text_field( $terms[0]->name ) . ' california';
        }

        // Fall back to first 5 words of post title
        $words = explode( ' ', wp_strip_all_tags( $post->post_title ) );
        $query = implode( ' ', array_slice( $words, 0, 5 ) );
        return $query . ' california';
    }

    /**
     * Fetch a photo from Pexels and attach it as featured image.
     *
     * @param int $post_id
     * @return bool
     */
    public static function assign_featured_image( $post_id ) {
        $api_key = defined( 'CA_CIVIC_PEXELS_KEY' ) ? CA_CIVIC_PEXELS_KEY : '';
        if ( empty( $api_key ) ) {
            return false;
        }

        $query = self::build_query_for_post( $post_id );

        $response = wp_remote_get(
            add_query_arg( [
                'query'       => urlencode( $query ),
                'per_page'    => 5,
                'orientation' => 'landscape',
            ], self::PEXELS_API ),
            [
                'headers' => [
                    'Authorization' => $api_key,
                ],
                'timeout' => 15,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['photos'] ) ) {
            return false;
        }

        // Pick a random photo from top results
        $photo = $body['photos'][ array_rand( $body['photos'] ) ];
        $photo_url = $photo['src']['large'] ?? $photo['src']['original'] ?? '';
        $photographer = $photo['photographer'] ?? 'Pexels';

        if ( empty( $photo_url ) ) {
            return false;
        }

        // Download and attach photo
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url( $photo_url );
        if ( is_wp_error( $tmp ) ) {
            return false;
        }

        $file_array = [
            'name'     => 'pexels-' . $photo['id'] . '.jpg',
            'tmp_name' => $tmp,
        ];

        $attachment_id = media_handle_sideload( $file_array, $post_id, 'Photo by ' . $photographer . ' (Pexels)' );

        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $tmp );
            return false;
        }

        // Store attribution
        update_post_meta( $attachment_id, '_ca_civic_photo_credit', 'Photo by ' . $photographer . ' via Pexels' );
        update_post_meta( $attachment_id, '_ca_civic_pexels_id', $photo['id'] );

        set_post_thumbnail( $post_id, $attachment_id );

        return true;
    }
}

// Register the cron callback
add_action( 'ca_civic_assign_photo', function( $post_id ) {
    CA_Civic_Intel\Photo_Api::assign_featured_image( $post_id );
} );
