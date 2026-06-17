<?php
namespace CA_Civic_Intel;
defined( 'ABSPATH' ) || exit;

class Taxonomies {
    const EDITORIAL_CPTS = [ 'ca_opinion','ca_ai_brief','ca_explainer','ca_public_event','ca_bill','ca_reg_docket','ca_agency' ];

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register' ] );
    }

    public static function register() {
        $cpts = self::EDITORIAL_CPTS;

        register_taxonomy( 'ca_issue_area', $cpts, [
            'labels'            => self::tax_labels( 'Issue Area', 'Issue Areas' ),
            'hierarchical'      => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => [ 'slug' => 'issue' ],
        ] );

        register_taxonomy( 'ca_branch', $cpts, [
            'labels'            => self::tax_labels( 'Branch', 'Branches' ),
            'hierarchical'      => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => [ 'slug' => 'branch' ],
        ] );

        register_taxonomy( 'ca_agency_tax', [ 'ca_ai_brief','ca_bill','ca_reg_docket','ca_public_event' ], [
            'labels'            => self::tax_labels( 'Agency', 'Agencies' ),
            'hierarchical'      => false,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => [ 'slug' => 'agency' ],
        ] );

        register_taxonomy( 'ca_region', [ 'ca_opinion','ca_ai_brief','ca_explainer','ca_public_event' ], [
            'labels'            => self::tax_labels( 'Region', 'Regions' ),
            'hierarchical'      => true,
            'show_in_rest'      => true,
            'rewrite'           => [ 'slug' => 'region' ],
        ] );

        register_taxonomy( 'ca_source_type', [ 'ca_ai_brief','ca_bill','ca_reg_docket' ], [
            'labels'            => self::tax_labels( 'Source Type', 'Source Types' ),
            'hierarchical'      => false,
            'show_in_rest'      => true,
            'rewrite'           => [ 'slug' => 'source-type' ],
        ] );

        register_taxonomy( 'ca_audience_segment', $cpts, [
            'labels'            => self::tax_labels( 'Audience', 'Audiences' ),
            'hierarchical'      => false,
            'show_in_rest'      => true,
            'rewrite'           => [ 'slug' => 'audience' ],
        ] );

        register_taxonomy( 'ca_content_label', $cpts, [
            'labels'            => self::tax_labels( 'Content Label', 'Content Labels' ),
            'hierarchical'      => false,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => [ 'slug' => 'label' ],
        ] );

        add_action( 'init', [ __CLASS__, 'seed_terms' ], 20 );
    }

    public static function seed_terms() {
        $issue_areas = [
            'Budget & Finance','Elections & Democracy','Environment & Climate',
            'Education','Health & Human Services','Housing & Land Use',
            'Labor & Employment','Public Safety','Technology & Privacy',
            'Transportation & Infrastructure','Water','Energy',
            'Courts & Justice','State Government Operations',
        ];
        foreach ( $issue_areas as $term ) {
            if ( ! term_exists( $term, 'ca_issue_area' ) ) {
                wp_insert_term( $term, 'ca_issue_area' );
            }
        }

        $branches = [ 'Legislative','Executive','Judicial','Local Government','Independent Agencies' ];
        foreach ( $branches as $term ) {
            if ( ! term_exists( $term, 'ca_branch' ) ) {
                wp_insert_term( $term, 'ca_branch' );
            }
        }

        $regions = [
            'Statewide','Bay Area','Los Angeles','Central Valley','Sacramento Region',
            'San Diego','Inland Empire','Central Coast','North Coast','Sierra Nevada',
        ];
        foreach ( $regions as $term ) {
            if ( ! term_exists( $term, 'ca_region' ) ) {
                wp_insert_term( $term, 'ca_region' );
            }
        }

        $labels = [
            'AI-Assisted','Editor Reviewed','Guest Opinion','Explainer',
            'Breaking','Developing','Analysis','Data',
        ];
        foreach ( $labels as $term ) {
            if ( ! term_exists( $term, 'ca_content_label' ) ) {
                wp_insert_term( $term, 'ca_content_label' );
            }
        }

        $source_types = [ 'Legislation','Regulation','Court Opinion','Executive Action','Meeting Notice','Budget Document' ];
        foreach ( $source_types as $term ) {
            if ( ! term_exists( $term, 'ca_source_type' ) ) {
                wp_insert_term( $term, 'ca_source_type' );
            }
        }
    }

    private static function tax_labels( $singular, $plural ) {
        return [
            'name'          => $plural,
            'singular_name' => $singular,
            'search_items'  => "Search $plural",
            'all_items'     => "All $plural",
            'edit_item'     => "Edit $singular",
            'update_item'   => "Update $singular",
            'add_new_item'  => "Add New $singular",
            'new_item_name' => "New $singular Name",
            'menu_name'     => $plural,
        ];
    }
}
