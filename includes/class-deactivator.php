<?php
/**
 * Handles plugin deactivation: clears cron schedules.
 *
 * @package AITF
 */

namespace AITF;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Deactivator {

    /**
     * Run on plugin deactivation.
     */
    public static function deactivate(): void {
        $timestamp = wp_next_scheduled( 'aitf_cron_hook' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'aitf_cron_hook' );
        }

        $timestamp = wp_next_scheduled( 'aitf_ping_sitemap' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'aitf_ping_sitemap' );
        }
    }
}
