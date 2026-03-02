<?php
/**
 * Sitemap Pinger — notifies Google and Bing when the sitemap is updated.
 *
 * @package AITF\Core
 */

namespace AITF\Core;

use AITF\Models\Log_Model;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sitemap_Pinger {

    private Log_Model $log;

    public function __construct() {
        $this->log = new Log_Model();
    }

    /**
     * Get the sitemap URL for this site.
     * Uses WordPress 5.5+ core sitemap; can be filtered by themes/plugins (e.g. Yoast, RankMath).
     *
     * @return string
     */
    public function get_sitemap_url(): string {
        $url = home_url( '/wp-sitemap.xml' );
        return (string) apply_filters( 'aitf_sitemap_url', $url );
    }

    /**
     * Ping Google and Bing with the sitemap URL.
     * Call this on post publish and from the scheduled cron.
     *
     * @return array{ google: bool, bing: bool, sitemap_url: string }
     */
    public function ping(): array {
        $sitemap_url = $this->get_sitemap_url();
        $encoded     = rawurlencode( $sitemap_url );
        $result      = [
            'google'       => false,
            'bing'         => false,
            'sitemap_url'  => $sitemap_url,
        ];

        $google_url = 'https://www.google.com/ping?sitemap=' . $encoded;
        $response   = wp_remote_get( $google_url, [ 'timeout' => 10, 'blocking' => true ] );
        $result['google'] = ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200;

        $bing_url   = 'https://www.bing.com/ping?sitemap=' . $encoded;
        $response   = wp_remote_get( $bing_url, [ 'timeout' => 10, 'blocking' => true ] );
        $result['bing'] = ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200;

        $this->log->insert( [
            'event_type' => 'sitemap_ping',
            'status'     => ( $result['google'] || $result['bing'] ) ? 'success' : 'fail',
            'message'    => sprintf(
                'Sitemap ping: Google %s, Bing %s',
                $result['google'] ? 'OK' : 'fail',
                $result['bing'] ? 'OK' : 'fail'
            ),
        ] );

        return $result;
    }

    /**
     * Get the next scheduled sitemap ping time (for display in settings).
     *
     * @return int|null Unix timestamp or null if not scheduled.
     */
    public static function get_next_ping_time(): ?int {
        $timestamp = wp_next_scheduled( 'aitf_ping_sitemap' );
        return $timestamp ? (int) $timestamp : null;
    }
}
