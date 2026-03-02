<?php
/**
 * Cron Handler — manages WP-Cron schedule and "Run Now" capability.
 *
 * @package AITF\Cron
 */

namespace AITF\Cron;

use AITF\Core\Content_Generator;
use AITF\Core\Sitemap_Pinger;
use AITF\Models\Log_Model;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cron_Handler {

    /** Minimum seconds between sitemap pings (1 hour). */
    const SITEMAP_PING_MIN = 3600;

    /** Maximum seconds between sitemap pings (1.5 hours). */
    const SITEMAP_PING_MAX = 5400;

    public function __construct() {
        // Register custom cron interval.
        add_filter( 'cron_schedules', [ $this, 'add_custom_interval' ] );

        // Register the cron callback.
        add_action( 'aitf_cron_hook', [ $this, 'run_cron' ] );

        // Register the "Run Now" admin action.
        add_action( 'admin_post_aitf_run_now', [ $this, 'handle_run_now' ] );

        // Self-healing: re-schedule if the event is missing.
        add_action( 'init', [ $this, 'ensure_scheduled' ] );

        // Sitemap ping: recurring with random interval (1h–1.5h).
        add_action( 'aitf_ping_sitemap', [ $this, 'run_sitemap_ping' ] );

        // Ping sitemap when a new post is published.
        add_action( 'transition_post_status', [ $this, 'maybe_ping_sitemap_on_publish' ], 10, 3 );
    }

    /**
     * Ensure the cron event is always scheduled (self-healing).
     */
    public function ensure_scheduled(): void {
        if ( ! wp_next_scheduled( 'aitf_cron_hook' ) ) {
            wp_schedule_event( time(), 'every_four_hours', 'aitf_cron_hook' );
        }

        // Sitemap ping: schedule next single event if none (random 1h–1.5h from now).
        if ( ! wp_next_scheduled( 'aitf_ping_sitemap' ) ) {
            $delay = wp_rand( self::SITEMAP_PING_MIN, self::SITEMAP_PING_MAX );
            wp_schedule_single_event( time() + $delay, 'aitf_ping_sitemap' );
        }
    }

    /**
     * Run sitemap ping, then schedule the next run at a random time (1h–1.5h).
     */
    public function run_sitemap_ping(): void {
        $pinger = new Sitemap_Pinger();
        $pinger->ping();

        $delay = wp_rand( self::SITEMAP_PING_MIN, self::SITEMAP_PING_MAX );
        wp_schedule_single_event( time() + $delay, 'aitf_ping_sitemap' );
    }

    /**
     * When a post transitions to published, ping the sitemap once.
     *
     * @param string   $new_status New post status.
     * @param string   $old_status Old post status.
     * @param \WP_Post $post       Post object.
     */
    public function maybe_ping_sitemap_on_publish( string $new_status, string $old_status, $post ): void {
        if ( $new_status !== 'publish' || $old_status === 'publish' ) {
            return;
        }
        if ( ! $post instanceof \WP_Post || $post->post_type !== 'post' ) {
            return;
        }
        $pinger = new Sitemap_Pinger();
        $pinger->ping();
    }

    /**
     * Add "every 4 hours" interval to WP-Cron.
     *
     * @param array $schedules
     * @return array
     */
    public function add_custom_interval( array $schedules ): array {
        $schedules['every_four_hours'] = [
            'interval' => 4 * HOUR_IN_SECONDS,
            'display'  => __( 'Every 4 Hours', 'ai-for-techforus' ),
        ];
        return $schedules;
    }

    /**
     * Cron callback — runs the content generation pipeline.
     */
    public function run_cron(): void {
        // Prevent timeout for long runs.
        if ( function_exists( 'set_time_limit' ) ) {
            set_time_limit( 300 );
        }

        $generator = new Content_Generator();
        $generator->run();
    }

    /**
     * Handle the manual "Run Now" admin action.
     */
    public function handle_run_now(): void {
        // Security check.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to do this.', 'ai-for-techforus' ) );
        }

        check_admin_referer( 'aitf_run_now', 'aitf_nonce' );

        // Run the generation pipeline.
        $generator = new Content_Generator();
        $summary   = $generator->run();

        // Redirect back with result.
        $redirect_url = add_query_arg( [
            'page'      => 'aitf-settings',
            'aitf_ran'  => 1,
            'fetched'   => $summary['fetched'],
            'generated' => $summary['generated'],
            'skipped'   => $summary['skipped'],
            'errors'    => $summary['errors'],
        ], admin_url( 'admin.php' ) );

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Ensure the cron event is scheduled.
     */
    public static function schedule(): void {
        if ( ! wp_next_scheduled( 'aitf_cron_hook' ) ) {
            wp_schedule_event( time(), 'every_four_hours', 'aitf_cron_hook' );
        }
    }

    /**
     * Unschedule the cron event.
     */
    public static function unschedule(): void {
        $timestamp = wp_next_scheduled( 'aitf_cron_hook' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'aitf_cron_hook' );
        }
    }

    /**
     * Get info about the next scheduled run.
     *
     * @return array { scheduled: bool, next_run: string|null, next_run_human: string }
     */
    public static function get_schedule_info(): array {
        $timestamp = wp_next_scheduled( 'aitf_cron_hook' );

        if ( ! $timestamp ) {
            return [
                'scheduled'      => false,
                'next_run'       => null,
                'next_run_human' => __( 'Not scheduled', 'ai-for-techforus' ),
            ];
        }

        return [
            'scheduled'      => true,
            'next_run'       => gmdate( 'Y-m-d H:i:s', $timestamp ),
            'next_run_human' => human_time_diff( time(), $timestamp ) . ' ' . __( 'from now', 'ai-for-techforus' ),
        ];
    }
}
