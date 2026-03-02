<?php
/**
 * Uninstall script — runs when the plugin is deleted from WP Admin.
 *
 * Drops custom tables and removes all plugin options.
 *
 * @package AITF
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop custom tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aitf_competitors" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aitf_logs" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aitf_content_hashes" );

// Delete options.
delete_option( 'aitf_settings' );
delete_option( 'aitf_fixed_categories' );
delete_option( 'aitf_version' );
