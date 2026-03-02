<?php
/**
 * Competitor Fetcher — scans competitor WP REST APIs for new posts.
 *
 * @package AITF\Core
 */

namespace AITF\Core;

use AITF\Models\Competitor_Model;
use AITF\Models\Log_Model;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Competitor_Fetcher {

    private Competitor_Model $competitor_model;
    private Log_Model $log_model;

    public function __construct() {
        $this->competitor_model = new Competitor_Model();
        $this->log_model        = new Log_Model();
    }

    /**
     * Validate that a URL has a working WP REST API.
     *
     * @param string $url
     * @return array|\WP_Error { site_name: string, api_url: string } or WP_Error.
     */
    public function validate_rest_api( string $url ): mixed {
        $api_url  = trailingslashit( $url ) . 'wp-json/wp/v2/posts';
        $response = wp_remote_get( $api_url, [
            'timeout' => 15,
            'headers' => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) ) {
            return new \WP_Error( 'connection_failed', sprintf(
                'Could not connect to %s: %s',
                esc_url( $url ),
                $response->get_error_message()
            ) );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return new \WP_Error( 'invalid_api', sprintf(
                'REST API at %s returned HTTP %d. Ensure the site has WP REST API enabled.',
                esc_url( $api_url ),
                $code
            ) );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $body ) ) {
            return new \WP_Error( 'invalid_response', 'REST API returned invalid JSON.' );
        }

        // Try to get site name from the index endpoint.
        $site_name = $this->fetch_site_name( $url );

        return [
            'site_name' => $site_name,
            'api_url'   => $api_url,
        ];
    }

    /**
     * Fetch the site name from the WP REST index.
     *
     * @param string $url
     * @return string
     */
    private function fetch_site_name( string $url ): string {
        $index_url = trailingslashit( $url ) . 'wp-json';
        $response  = wp_remote_get( $index_url, [ 'timeout' => 10 ] );

        if ( ! is_wp_error( $response ) ) {
            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( ! empty( $data['name'] ) ) {
                return sanitize_text_field( $data['name'] );
            }
        }

        return wp_parse_url( $url, PHP_URL_HOST ) ?: $url;
    }

    /**
     * Fetch new posts from all active competitors.
     *
     * @return array Array of [ competitor_id, posts[] ] results.
     */
    public function fetch_all_new_posts(): array {
        $competitors = $this->competitor_model->get_all_active();
        $results     = [];

        foreach ( $competitors as $competitor ) {
            // Rate limit: 1 second between requests.
            if ( ! empty( $results ) ) {
                sleep( 1 );
            }

            $new_posts = $this->fetch_new_posts_for( $competitor );

            if ( is_wp_error( $new_posts ) ) {
                $this->log_model->insert( [
                    'event_type'     => 'fetch',
                    'competitor_url' => $competitor->site_url,
                    'status'         => 'fail',
                    'message'        => $new_posts->get_error_message(),
                ] );
                continue;
            }

            if ( ! empty( $new_posts ) ) {
                $results[] = [
                    'competitor'  => $competitor,
                    'posts'       => $new_posts,
                ];

                $this->log_model->insert( [
                    'event_type'     => 'fetch',
                    'competitor_url' => $competitor->site_url,
                    'status'         => 'success',
                    'message'        => sprintf( 'Found %d new post(s).', count( $new_posts ) ),
                ] );
            }
        }

        return $results;
    }

    /**
     * Fetch new posts for a single competitor.
     *
     * @param object $competitor
     * @return array|\WP_Error
     */
    private function fetch_new_posts_for( object $competitor ): mixed {

        // Use transient cache to avoid hammering.
        $cache_key = 'aitf_fetch_' . md5( $competitor->site_url );
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        $api_url = trailingslashit( $competitor->site_url ) . 'wp-json/wp/v2/posts';
        $args    = [
            'per_page' => 5,
            'orderby'  => 'date',
            'order'    => 'desc',
            '_fields'  => 'id,title,slug,excerpt,link,date,categories,tags',
        ];

        $url      = add_query_arg( $args, $api_url );
        $response = wp_remote_get( $url, [ 'timeout' => 20 ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return new \WP_Error( 'api_error', "REST API returned HTTP {$code}" );
        }

        $posts = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $posts ) ) {
            return new \WP_Error( 'invalid_json', 'Could not parse posts response.' );
        }

        // Filter to only new posts (ID > last fetched).
        $last_id   = (int) $competitor->last_fetched_post_id;
        $new_posts = [];

        foreach ( $posts as $post ) {
            $post_id = (int) ( $post['id'] ?? 0 );
            if ( $post_id > $last_id ) {
                $new_posts[] = $this->normalize_post( $post, $competitor->site_url );
            }
        }

        // Update last fetched ID if we found new posts.
        if ( ! empty( $new_posts ) ) {
            $max_id = max( array_column( $new_posts, 'remote_id' ) );
            $this->competitor_model->update( (int) $competitor->id, [
                'last_fetched_post_id' => $max_id,
                'last_fetched_at'      => current_time( 'mysql' ),
            ] );
        }

        // Cache for 15 minutes.
        set_transient( $cache_key, $new_posts, 15 * MINUTE_IN_SECONDS );

        return $new_posts;
    }

    /**
     * Normalize a raw WP REST post into a clean structure.
     *
     * @param array  $post
     * @param string $source_url
     * @return array
     */
    private function normalize_post( array $post, string $source_url ): array {
        $title = wp_strip_all_tags( $post['title']['rendered'] ?? '' );

        return [
            'remote_id'   => (int) ( $post['id'] ?? 0 ),
            'title'       => $title,
            'slug'        => sanitize_title( $post['slug'] ?? '' ),
            'excerpt'     => wp_strip_all_tags( $post['excerpt']['rendered'] ?? '' ),
            'link'        => esc_url( $post['link'] ?? '' ),
            'date'        => sanitize_text_field( $post['date'] ?? '' ),
            'source_url'  => $source_url,
        ];
    }

    /**
     * Fetch the full content + headings of a single post for deeper analysis.
     *
     * @param string $site_url
     * @param int    $post_id
     * @return array|\WP_Error
     */
    public function fetch_post_detail( string $site_url, int $post_id ): mixed {
        $api_url  = trailingslashit( $site_url ) . 'wp-json/wp/v2/posts/' . $post_id;
        $url      = add_query_arg( [ '_fields' => 'id,title,content,slug,excerpt,categories,tags' ], $api_url );
        $response = wp_remote_get( $url, [ 'timeout' => 20 ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $data ) ) {
            return new \WP_Error( 'empty_post', 'Could not fetch post detail.' );
        }

        $content  = $data['content']['rendered'] ?? '';
        $headings = $this->extract_headings( $content );

        return [
            'title'    => wp_strip_all_tags( $data['title']['rendered'] ?? '' ),
            'slug'     => $data['slug'] ?? '',
            'excerpt'  => wp_strip_all_tags( $data['excerpt']['rendered'] ?? '' ),
            'headings' => $headings,
            'content_summary' => wp_trim_words( wp_strip_all_tags( $content ), 200 ),
        ];
    }

    /**
     * Extract H2/H3 headings from HTML content.
     *
     * @param string $html
     * @return array
     */
    private function extract_headings( string $html ): array {
        $headings = [];
        if ( preg_match_all( '/<h([23])[^>]*>(.*?)<\/h\1>/is', $html, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $match ) {
                $headings[] = [
                    'level' => 'h' . $match[1],
                    'text'  => wp_strip_all_tags( $match[2] ),
                ];
            }
        }
        return $headings;
    }
}
