<?php
namespace CA_Civic_Intel;
defined( 'ABSPATH' ) || exit;

class Activator {
      public static function activate() {
                self::create_tables();
                self::set_defaults();
                flush_rewrite_rules();
      }

    private static function create_tables() {
              global $wpdb;
              $charset = $wpdb->get_charset_collate();
              $pfx     = $wpdb->prefix;
              require_once ABSPATH . 'wp-admin/includes/upgrade.php';

          dbDelta( "CREATE TABLE {$pfx}ca_brief_sources (
                      id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                                  brief_id    BIGINT UNSIGNED NOT NULL,
                                              source_url  TEXT            NOT NULL,
                                                          source_title VARCHAR(500)   NOT NULL DEFAULT '',
                                                                      source_date DATE            NULL,
                                                                                  agency_slug VARCHAR(100)    NOT NULL DEFAULT '',
                                                                                              retrieved_at DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                                                                                          PRIMARY KEY (id),
                                                                                                                      KEY brief_id (brief_id)
                                                                                                                              ) $charset;" );

          dbDelta( "CREATE TABLE {$pfx}ca_ai_prompt_log (
                      id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                                  brief_id     BIGINT UNSIGNED NOT NULL,
                                              prompt_hash  CHAR(64)        NOT NULL,
                                                          prompt_text  LONGTEXT        NOT NULL,
                                                                      response_text LONGTEXT       NOT NULL,
                                                                                  model        VARCHAR(100)    NOT NULL DEFAULT '',
                                                                                              tokens_in    INT             NOT NULL DEFAULT 0,
                                                                                                          tokens_out   INT             NOT NULL DEFAULT 0,
                                                                                                                      created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                                                                                                                  PRIMARY KEY (id),
                                                                                                                                              KEY brief_id (brief_id),
                                                                                                                                                          KEY prompt_hash (prompt_hash)
                                                                                                                                                                  ) $charset;" );

          dbDelta( "CREATE TABLE {$pfx}ca_ingestion_queue (
                      id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                                  source_slug  VARCHAR(100)    NOT NULL,
                                              source_url   TEXT            NOT NULL,
                                                          raw_content  LONGTEXT        NOT NULL,
                                                                      status       ENUM('pending','processing','done','error') NOT NULL DEFAULT 'pending',
                                                                                  error_msg    TEXT            NULL,
                                                                                              ingested_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                                                                                          processed_at DATETIME        NULL,
                                                                                                                      PRIMARY KEY (id),
                                                                                                                                  KEY status (status),
                                                                                                                                              KEY source_slug (source_slug)
                                                                                                                                                      ) $charset;" );

          dbDelta( "CREATE TABLE {$pfx}ca_submissions (
                      id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                                  post_id         BIGINT UNSIGNED NULL,
                                              submitter_name  VARCHAR(255)    NOT NULL,
                                                          submitter_email VARCHAR(255)    NOT NULL,
                                                                      submitter_org   VARCHAR(255)    NOT NULL DEFAULT '',
                                                                                  submitter_title VARCHAR(255)    NOT NULL DEFAULT '',
                                                                                              client_disclosed TEXT           NULL,
                                                                                                          submitted_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                                                                                                      status          ENUM('pending','under_review','approved','rejected','published') NOT NULL DEFAULT 'pending',
                                                                                                                                  editor_notes    TEXT            NULL,
                                                                                                                                              ip_address      VARCHAR(45)     NOT NULL DEFAULT '',
                                                                                                                                                          PRIMARY KEY (id),
                                                                                                                                                                      KEY post_id (post_id),
                                                                                                                                                                                  KEY status (status)
                                                                                                                                                                                          ) $charset;" );

          dbDelta( "CREATE TABLE {$pfx}ca_promotions (
                      id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                                  post_id         BIGINT UNSIGNED NOT NULL,
                                              sponsor_name    VARCHAR(255)    NOT NULL,
                                                          sponsor_email   VARCHAR(255)    NOT NULL,
                                                                      amount_cents    INT             NOT NULL DEFAULT 0,
                                                                                  currency        CHAR(3)         NOT NULL DEFAULT 'USD',
                                                                                              stripe_pi_id    VARCHAR(255)    NOT NULL DEFAULT '',
                                                                                                          status          ENUM('pending','paid','active','expired','refunded') NOT NULL DEFAULT 'pending',
                                                                                                                      starts_at       DATE            NULL,
                                                                                                                                  ends_at         DATE            NULL,
                                                                                                                                              created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                                                                                                                                          PRIMARY KEY (id),
                                                                                                                                                                      KEY post_id (post_id),
                                                                                                                                                                                  KEY status (status)
                                                                                                                                                                                          ) $charset;" );

          dbDelta( "CREATE TABLE {$pfx}ca_alerts (
                      id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                                  user_id      BIGINT UNSIGNED NOT NULL,
                                              alert_type   VARCHAR(100)    NOT NULL,
                                                          keywords     TEXT            NULL,
                                                                      issue_area   VARCHAR(100)    NULL,
                                                                                  agency_slug  VARCHAR(100)    NULL,
                                                                                              email        VARCHAR(255)    NOT NULL,
                                                                                                          active       TINYINT(1)      NOT NULL DEFAULT 1,
                                                                                                                      created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                                                                                                                  PRIMARY KEY (id),
                                                                                                                                              KEY user_id (user_id)
                                                                                                                                                      ) $charset;" );

          dbDelta( "CREATE TABLE {$pfx}ca_source_monitor_log (
                      id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                                  source_slug  VARCHAR(100)    NOT NULL,
                                              checked_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                                          items_found  INT             NOT NULL DEFAULT 0,
                                                                      items_new    INT             NOT NULL DEFAULT 0,
                                                                                  success      TINYINT(1)      NOT NULL DEFAULT 1,
                                                                                              error_msg    TEXT            NULL,
                                                                                                          PRIMARY KEY (id),
                                                                                                                      KEY source_slug_checked (source_slug, checked_at)
                                                                                                                              ) $charset;" );

          dbDelta( "CREATE TABLE {$pfx}ca_corrections (
                      id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                                  post_id      BIGINT UNSIGNED NOT NULL,
                                              editor_id    BIGINT UNSIGNED NOT NULL,
                                                          correction   TEXT            NOT NULL,
                                                                      created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                                                                  PRIMARY KEY (id),
                                                                                              KEY post_id (post_id)
                                                                                                      ) $charset;" );

          update_option( 'ca_civic_db_version', CA_CIVIC_DB_VERSION );
    }

    private static function set_defaults() {
              add_option( 'ca_civic_editorial_email', get_option('admin_email') );
              add_option( 'ca_civic_auto_publish_ai', '0' );
              add_option( 'ca_civic_openai_model',    'gpt-4o' );
              add_option( 'ca_civic_sources_active',  json_encode([
                                                                              'ca_legislature', 'ca_governor', 'ca_oal', 'ca_cpuc', 'ca_courts'
                                                                          ]) );
    }
}
