<?php
namespace CA_Civic_Intel;
if ( ! defined( 'ABSPATH' ) ) exit;

class Activator {
    public static function activate() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ca_civic_sources (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            source_url varchar(2048) NOT NULL,
            source_title varchar(512) DEFAULT '',
            source_type varchar(64) DEFAULT '',
            fetched_at datetime DEFAULT CURRENT_TIMESTAMP,
            raw_hash varchar(64) DEFAULT '',
            PRIMARY KEY (id)
        ) $charset;" );

        dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ca_civic_ai_log (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            brief_post_id bigint(20) NOT NULL DEFAULT 0,
            prompt_version varchar(32) DEFAULT '',
            prompt_text longtext,
            ai_output longtext,
            model varchar(64) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;" );

        dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ca_civic_submissions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            submission_post_id bigint(20) DEFAULT 0,
            submitter_name varchar(256) DEFAULT '',
            submitter_email varchar(256) DEFAULT '',
            submitter_org varchar(256) DEFAULT '',
            submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(32) DEFAULT 'pending',
            PRIMARY KEY (id)
        ) $charset;" );

        dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ca_civic_promotions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            sponsor_name varchar(256) DEFAULT '',
            sponsor_email varchar(256) DEFAULT '',
            budget_cents bigint(20) DEFAULT 0,
            impressions_delivered bigint(20) DEFAULT 0,
            start_date date DEFAULT NULL,
            end_date date DEFAULT NULL,
            status varchar(32) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;" );

        dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ca_civic_events_feed (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_post_id bigint(20) DEFAULT 0,
            external_url varchar(2048) DEFAULT '',
            source_agency varchar(256) DEFAULT '',
            event_date datetime DEFAULT NULL,
            imported_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;" );

        dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ca_civic_bill_tracking (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            bill_post_id bigint(20) DEFAULT 0,
            bill_number varchar(64) DEFAULT '',
            legislature_session varchar(32) DEFAULT '',
            leginfo_url varchar(2048) DEFAULT '',
            last_status varchar(256) DEFAULT '',
            last_checked datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;" );

        dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ca_civic_reg_dockets (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            docket_post_id bigint(20) DEFAULT 0,
            agency_name varchar(256) DEFAULT '',
            docket_number varchar(128) DEFAULT '',
            comment_deadline datetime DEFAULT NULL,
            oal_url varchar(2048) DEFAULT '',
            imported_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;" );

        dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ca_civic_audience_segments (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            segment_name varchar(128) DEFAULT '',
            segment_slug varchar(128) DEFAULT '',
            description text,
            PRIMARY KEY (id)
        ) $charset;" );

        update_option( 'ca_civic_auto_publish_ai', '0' );
        update_option( 'ca_civic_db_version', CA_CIVIC_VERSION );

        flush_rewrite_rules();
    }
}
