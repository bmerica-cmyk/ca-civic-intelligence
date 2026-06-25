<?php
/**
 * Dashboard — admin metrics and public /civic-data endpoint.
 *
 * @package CA_Civic_Intel
 */

namespace CA_Civic_Intel;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Dashboard class.
 * Shows legislature activity, agency rulemaking, and content metrics.
 */
class Dashboard {

    public function add_menus() {
        // This is handled by Admin::add_menus()
    }

    public static function dashboard_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Access denied' );
        }

        global $wpdb;

        // Collect metrics
        $opinions_count    = wp_count_posts( 'ca_opinion' );
        $explainers_count  = wp_count_posts( 'ca_explainer' );
        $ai_briefs_count   = wp_count_posts( 'ca_ai_brief' );
        $agencies_count    = wp_count_posts( 'ca_agency' );
        $bills_count       = wp_count_posts( 'ca_bill' );
        $dockets_count     = wp_count_posts( 'ca_reg_docket' );
        $events_count      = wp_count_posts( 'ca_public_event' );
        $submissions_count = wp_count_posts( 'ca_submission' );

        // Recent legislative activity from bills CPT
        $recent_bills = get_posts( [
            'post_type'      => 'ca_bill',
            'posts_per_page' => 10,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'post_status'    => [ 'publish', 'draft' ],
        ] );

        // Recent AI briefs pending review
        $pending_briefs = get_posts( [
            'post_type'      => 'ca_ai_brief',
            'posts_per_page' => 5,
            'post_status'    => 'draft',
            'meta_query'     => [
                [
                    'key'     => '_ca_ai_reviewed',
                    'value'   => '1',
                    'compare' => '!=',
                ],
            ],
        ] );

        // Recent agency dockets
        $recent_dockets = get_posts( [
            'post_type'      => 'ca_reg_docket',
            'posts_per_page' => 5,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'post_status'    => [ 'publish', 'draft' ],
        ] );

        $auto_publish = get_option( 'ca_civic_auto_publish_ai', '0' );
        $safety_class = ( $auto_publish === '0' ) ? 'ca-safety-ok' : 'ca-safety-warn';
        $safety_label = ( $auto_publish === '0' ) ? 'DISABLED (Safe)' : 'ENABLED (Danger!)';

        echo '<div class="wrap ca-civic-dashboard">';
        echo '<h1>California Civic Intelligence — Platform Dashboard</h1>';

        // AI Safety Lock
        echo '<div class="ca-safety-banner ' . esc_attr( $safety_class ) . '">';
        echo '<strong>AI Auto-Publish:</strong> <span class="ca-safety-status">' . esc_html( $safety_label ) . '</span>';
        echo '</div>';

        // Metrics Grid
        echo '<h2>Content Metrics</h2>';
        echo '<div class="ca-metrics-grid">';
        $metrics = [
            'Opinions'    => $opinions_count->publish ?? 0,
            'Explainers'  => $explainers_count->publish ?? 0,
            'AI Briefs'   => ( $ai_briefs_count->publish ?? 0 ) + ( $ai_briefs_count->draft ?? 0 ),
            'Bills'       => $bills_count->publish ?? 0,
            'Dockets'     => $dockets_count->publish ?? 0,
            'Agencies'    => $agencies_count->publish ?? 0,
            'Events'      => $events_count->publish ?? 0,
            'Submissions' => $submissions_count->pending ?? 0,
        ];
        foreach ( $metrics as $label => $count ) {
            echo '<div class="ca-metric-card">';
            echo '<div class="ca-metric-number">' . intval( $count ) . '</div>';
            echo '<div class="ca-metric-label">' . esc_html( $label ) . '</div>';
            echo '</div>';
        }
        echo '</div>';

        // Pending AI Briefs
        if ( ! empty( $pending_briefs ) ) {
            echo '<h2>AI Briefs Awaiting Review (' . count( $pending_briefs ) . ')</h2>';
            echo '<table class="wp-list-table widefat striped">';
            echo '<thead><tr><th>Title</th><th>Date</th><th>Action</th></tr></thead><tbody>';
            foreach ( $pending_briefs as $brief ) {
                $edit_url = get_edit_post_link( $brief->ID );
                echo '<tr>';
                echo '<td>' . esc_html( $brief->post_title ) . '</td>';
                echo '<td>' . esc_html( get_the_date( 'M j, Y', $brief ) ) . '</td>';
                echo '<td><a href="' . esc_url( $edit_url ) . '" class="button button-small">Review</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        // Recent Bills
        if ( ! empty( $recent_bills ) ) {
            echo '<h2>Recent Legislative Activity</h2>';
            echo '<table class="wp-list-table widefat striped">';
            echo '<thead><tr><th>Bill</th><th>Status</th><th>Date</th></tr></thead><tbody>';
            foreach ( $recent_bills as $bill ) {
                $status_terms = get_the_terms( $bill->ID, 'ca_issue_area' );
                $issue = $status_terms && ! is_wp_error( $status_terms ) ? $status_terms[0]->name : '—';
                echo '<tr>';
                echo '<td>' . esc_html( $bill->post_title ) . '</td>';
                echo '<td>' . esc_html( $issue ) . '</td>';
                echo '<td>' . esc_html( get_the_date( 'M j, Y', $bill ) ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        // Recent Dockets
        if ( ! empty( $recent_dockets ) ) {
            echo '<h2>Recent Agency Rulemaking</h2>';
            echo '<table class="wp-list-table widefat striped">';
            echo '<thead><tr><th>Docket</th><th>Date</th><th>Action</th></tr></thead><tbody>';
            foreach ( $recent_dockets as $docket ) {
                $edit_url = get_edit_post_link( $docket->ID );
                echo '<tr>';
                echo '<td>' . esc_html( $docket->post_title ) . '</td>';
                echo '<td>' . esc_html( get_the_date( 'M j, Y', $docket ) ) . '</td>';
                echo '<td><a href="' . esc_url( $edit_url ) . '" class="button button-small">View</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        echo '</div><!-- .ca-civic-dashboard -->';
    }
}
