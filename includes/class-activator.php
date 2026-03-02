<?php
/**
 * Handles plugin activation: creates custom DB tables and default options.
 *
 * @package AITF
 */

namespace AITF;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Activator {

    /**
     * Run on plugin activation.
     */
    public static function activate(): void {
        self::create_tables();
        self::set_defaults();

        // Register the custom interval so it exists at activation time.
        add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_interval' ] );

        // Schedule cron if not already scheduled.
        if ( ! wp_next_scheduled( 'aitf_cron_hook' ) ) {
            wp_schedule_event( time(), 'every_four_hours', 'aitf_cron_hook' );
        }

        // Schedule first sitemap ping at a random time 5–15 min from now (stagger).
        if ( ! wp_next_scheduled( 'aitf_ping_sitemap' ) ) {
            $delay = wp_rand( 5 * 60, 15 * 60 );
            wp_schedule_single_event( time() + $delay, 'aitf_ping_sitemap' );
        }

        // Store version for future upgrade routines.
        update_option( 'aitf_version', AITF_VERSION );
    }

    /**
     * Create custom database tables.
     */
    private static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $competitors_table = $wpdb->prefix . 'aitf_competitors';
        $logs_table        = $wpdb->prefix . 'aitf_logs';
        $hashes_table      = $wpdb->prefix . 'aitf_content_hashes';

        $sql = "
CREATE TABLE {$competitors_table} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    site_url varchar(500) NOT NULL,
    site_name varchar(255) NOT NULL DEFAULT '',
    last_fetched_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
    last_fetched_at datetime DEFAULT NULL,
    status varchar(20) NOT NULL DEFAULT 'active',
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_status (status)
) {$charset_collate};

CREATE TABLE {$logs_table} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    event_type varchar(50) NOT NULL DEFAULT '',
    competitor_url varchar(500) DEFAULT NULL,
    status varchar(20) NOT NULL DEFAULT 'success',
    message text,
    post_id bigint(20) unsigned DEFAULT NULL,
    token_usage int(11) DEFAULT NULL,
    provider varchar(20) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_timestamp (timestamp),
    KEY idx_event_type (event_type),
    KEY idx_status (status)
) {$charset_collate};

CREATE TABLE {$hashes_table} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    title_hash varchar(64) NOT NULL,
    slug_hash varchar(64) NOT NULL DEFAULT '',
    source_url varchar(500) NOT NULL DEFAULT '',
    created_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_title_hash (title_hash),
    KEY idx_slug_hash (slug_hash)
) {$charset_collate};
";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Set default plugin options.
     */
    private static function set_defaults(): void {
        $defaults = [
            'api_provider'   => 'groq',
            'api_key'        => '',
            'model'          => '',
            'language'       => 'en',
            'article_length' => 1500,
            'publish_mode'   => 'draft',
            'category_mode'  => 'fixed',
        ];

        if ( false === get_option( 'aitf_settings' ) ) {
            add_option( 'aitf_settings', $defaults );
        }

        if ( false === get_option( 'aitf_fixed_categories' ) ) {
            add_option( 'aitf_fixed_categories', [] );
        }
    }

    /**
     * Register the custom cron interval (used during activation).
     *
     * @param array $schedules
     * @return array
     */
    public static function add_cron_interval( array $schedules ): array {
        $schedules['every_four_hours'] = [
            'interval' => 4 * HOUR_IN_SECONDS,
            'display'  => 'Every 4 Hours',
        ];
        return $schedules;
    }
}
