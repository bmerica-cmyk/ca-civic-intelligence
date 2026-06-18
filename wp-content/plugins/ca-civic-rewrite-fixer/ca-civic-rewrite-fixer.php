<?php
/**
 * Plugin Name: CA Civic Rewrite Fixer
 * Description: Adds explicit rewrite rules for CA Civic Intelligence CPT archives and flushes on init.
 * Version: 1.0.0
 * Author: California Civic Intelligence
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'ca_civic_rewrite_fixer', 99 );

function ca_civic_rewrite_fixer() {
    $cpts = array(
        'ca_opinion'    => 'opinions',
        'ca_explainer'  => 'explainers',
        'ca_ai_brief'   => 'ai-briefs',
        'ca_public_event' => 'public-events',
        'ca_bill'       => 'bills',
        'ca_reg_docket' => 'reg-dockets',
        'ca_agency'     => 'agencies',
    );

    foreach ( $cpts as $cpt => $slug ) {
        // Archive: /opinions/
        add_rewrite_rule(
            '^(' . $slug . ')/?$',
            'index.php?post_type=' . $cpt,
            'top'
        );
        // Paged archive: /opinions/page/2/
        add_rewrite_rule(
            '^(' . $slug . ')/page/?([0-9]{1,})/?$',
            'index.php?post_type=' . $cpt . '&paged=$matches[2]',
            'top'
        );
    }

    // One-time flush to register the new rules
    if ( ! get_option( 'ca_civic_rewrite_flushed_v2' ) ) {
        flush_rewrite_rules( false );
        update_option( 'ca_civic_rewrite_flushed_v2', true );
    }
}
