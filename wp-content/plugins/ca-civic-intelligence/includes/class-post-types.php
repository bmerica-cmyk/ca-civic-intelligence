<?php
namespace CA_Civic_Intel;
defined( 'ABSPATH' ) || exit;

class Post_Types {
    public static function init() {
        add_action( 'init', [ __CLASS__, 'register' ] );
    }

    public static function register() {
        register_post_type( 'ca_opinion', [
            'labels'        => self::labels( 'Opinion', 'Opinions' ),
            'public'        => true,
            'show_in_rest'  => true,
            'supports'      => [ 'title','editor','author','excerpt','thumbnail','revisions','custom-fields' ],
            'has_archive'   => 'opinion',
            'rewrite'       => [ 'slug' => 'opinion', 'with_front' => false ],
            'menu_icon'     => 'dashicons-edit-large',
            'menu_position' => 5,
        ] );

        register_post_type( 'ca_ai_brief', [
            'labels'        => self::labels( 'Civic Brief', 'Civic Briefs' ),
            'public'        => true,
            'show_in_rest'  => true,
            'supports'      => [ 'title','editor','author','excerpt','thumbnail','revisions','custom-fields' ],
            'has_archive'   => 'briefs',
            'rewrite'       => [ 'slug' => 'briefs', 'with_front' => false ],
            'menu_icon'     => 'dashicons-analytics',
            'menu_position' => 6,
        ] );

        register_post_type( 'ca_explainer', [
            'labels'        => self::labels( 'Explainer', 'Explainers' ),
            'public'        => true,
            'show_in_rest'  => true,
            'supports'      => [ 'title','editor','author','excerpt','thumbnail','revisions','custom-fields' ],
            'has_archive'   => 'explainers',
            'rewrite'       => [ 'slug' => 'explainers', 'with_front' => false ],
            'menu_icon'     => 'dashicons-book-alt',
            'menu_position' => 7,
        ] );

        register_post_type( 'ca_public_event', [
            'labels'        => self::labels( 'Public Meeting', 'Public Meetings' ),
            'public'        => true,
            'show_in_rest'  => true,
            'supports'      => [ 'title','editor','excerpt','custom-fields' ],
            'has_archive'   => 'meetings',
            'rewrite'       => [ 'slug' => 'meetings', 'with_front' => false ],
            'menu_icon'     => 'dashicons-calendar-alt',
            'menu_position' => 8,
        ] );

        register_post_type( 'ca_bill', [
            'labels'        => self::labels( 'Bill', 'Bills' ),
            'public'        => true,
            'show_in_rest'  => true,
            'supports'      => [ 'title','editor','excerpt','custom-fields' ],
            'has_archive'   => 'bills',
            'rewrite'       => [ 'slug' => 'bills', 'with_front' => false ],
            'menu_icon'     => 'dashicons-media-document',
            'menu_position' => 9,
        ] );

        register_post_type( 'ca_reg_docket', [
            'labels'        => self::labels( 'Reg Docket', 'Reg Dockets' ),
            'public'        => true,
            'show_in_rest'  => true,
            'supports'      => [ 'title','editor','excerpt','custom-fields' ],
            'has_archive'   => 'dockets',
            'rewrite'       => [ 'slug' => 'dockets', 'with_front' => false ],
            'menu_icon'     => 'dashicons-clipboard',
            'menu_position' => 10,
        ] );

        register_post_type( 'ca_agency', [
            'labels'        => self::labels( 'Agency', 'Agencies' ),
            'public'        => true,
            'show_in_rest'  => true,
            'supports'      => [ 'title','editor','thumbnail','custom-fields' ],
            'has_archive'   => 'agencies',
            'rewrite'       => [ 'slug' => 'agencies', 'with_front' => false ],
            'menu_icon'     => 'dashicons-building',
            'menu_position' => 11,
        ] );

        register_post_type( 'ca_submission', [
            'labels'        => self::labels( 'Submission', 'Submissions' ),
            'public'        => false,
            'show_ui'       => true,
            'show_in_rest'  => false,
            'supports'      => [ 'title','editor','author','custom-fields' ],
            'capability_type' => 'post',
            'capabilities'  => [ 'create_posts' => 'do_not_allow' ],
            'map_meta_cap'  => true,
            'menu_icon'     => 'dashicons-email-alt',
            'menu_position' => 12,
        ] );

        register_post_type( 'ca_promotion', [
            'labels'        => self::labels( 'Promotion', 'Promotions' ),
            'public'        => false,
            'show_ui'       => true,
            'show_in_rest'  => false,
            'supports'      => [ 'title','custom-fields' ],
            'menu_icon'     => 'dashicons-megaphone',
            'menu_position' => 13,
        ] );
    }

    private static function labels( $singular, $plural ) {
        return [
            'name'               => $plural,
            'singular_name'      => $singular,
            'add_new_item'       => "Add New $singular",
            'edit_item'          => "Edit $singular",
            'new_item'           => "New $singular",
            'view_item'          => "View $singular",
            'search_items'       => "Search $plural",
            'not_found'          => "No $plural found.",
            'not_found_in_trash' => "No $plural in trash.",
        ];
    }
}
