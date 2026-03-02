<?php
/**
 * Generate from URL Page — allows generating content from a single post URL.
 *
 * @package AITF\Admin
 */

namespace AITF\Admin;

use AITF\Core\Content_Generator;
use AITF\Core\Competitor_Fetcher;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Single_URL_Page {

    private Competitor_Fetcher $fetcher;
    private Content_Generator  $generator;

    public function __construct() {
        $this->fetcher   = new Competitor_Fetcher();
        $this->generator = new Content_Generator();

        add_action( 'admin_post_aitf_generate_from_url', [ $this, 'handle_generate' ] );
    }

    /**
     * Handle form submission for single URL generation.
     */
    public function handle_generate(): void {
        if ( ! isset( $_POST['aitf_generate_url_nonce'] ) ) {
            wp_die( 'Invalid request.' );
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aitf_generate_url_nonce'] ) ), 'aitf_generate_from_url' ) ) {
            wp_die( 'Invalid nonce.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        $post_url = esc_url_raw( wp_unslash( $_POST['post_url'] ?? '' ) );

        if ( empty( $post_url ) ) {
            wp_safe_redirect( add_query_arg( [
                'page'  => 'aitf-generate-url',
                'error' => 'empty_url',
            ], admin_url( 'admin.php' ) ) );
            exit;
        }

        if ( strpos( $post_url, 'http' ) !== 0 ) {
            $post_url = 'https://' . $post_url;
        }

        // Extract site URL and post ID/slug from the URL.
        $parsed    = wp_parse_url( $post_url );
        $site_url  = $parsed['scheme'] . '://' . $parsed['host'];
        $path      = trim( $parsed['path'] ?? '', '/' );
        
        // 1. Try numeric ID.
        preg_match( '/\d+/', $path, $matches );
        $remote_id = ! empty( $matches ) ? $matches[0] : null;

        // 2. If no ID, try to get ID from slug via REST API.
        if ( ! $remote_id ) {
            $path_parts = explode( '/', $path );
            $slug       = end( $path_parts );

            if ( ! empty( $slug ) ) {
                $lookup_url = $site_url . '/wp-json/wp/v2/posts?slug=' . urlencode( $slug );
                $response   = wp_remote_get( $lookup_url, [ 'timeout' => 10 ] );

                if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                    $data = json_decode( wp_remote_retrieve_body( $response ), true );
                    if ( ! empty( $data ) && isset( $data[0]['id'] ) ) {
                        $remote_id = $data[0]['id'];
                        // Update fetcher to use this ID.
                    }
                }
            }
        }

        if ( ! $remote_id ) {
            wp_safe_redirect( add_query_arg( [
                'page'  => 'aitf-generate-url',
                'error' => 'invalid_url',
            ], admin_url( 'admin.php' ) ) );
            exit;
        }

        // Fetch post detail.
        $detail = $this->fetcher->fetch_post_detail( $site_url, $remote_id );

        if ( is_wp_error( $detail ) ) {
            wp_safe_redirect( add_query_arg( [
                'page'  => 'aitf-generate-url',
                'error' => 'fetch_failed',
            ], admin_url( 'admin.php' ) ) );
            exit;
        }

        // Mock a post structure for the generator.
        $post = [
            'title'     => $detail['title'] ?? 'Untitled',
            'excerpt'   => $detail['excerpt'] ?? '',
            'link'      => $post_url,
            'remote_id' => $remote_id,
        ];

        // Generate article.
        $provider = \AITF\AI\AI_Provider::factory();
        if ( is_wp_error( $provider ) ) {
            wp_safe_redirect( add_query_arg( [
                'page'  => 'aitf-generate-url',
                'error' => 'ai_provider_error',
            ], admin_url( 'admin.php' ) ) );
            exit;
        }

        $article = $this->generator->generate_article_public( $provider, $post, $detail );

        if ( is_wp_error( $article ) ) {
            wp_safe_redirect( add_query_arg( [
                'page'  => 'aitf-generate-url',
                'error' => 'generation_failed',
            ], admin_url( 'admin.php' ) ) );
            exit;
        }

        // Publish article.
        $post_id = $this->generator->publish_article_public( $article );

        if ( is_wp_error( $post_id ) ) {
            wp_safe_redirect( add_query_arg( [
                'page'  => 'aitf-generate-url',
                'error' => 'publish_failed',
            ], admin_url( 'admin.php' ) ) );
            exit;
        }

        // Success — redirect with post ID.
        wp_safe_redirect( add_query_arg( [
            'page'    => 'aitf-generate-url',
            'success' => $post_id,
        ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Render the Generate from URL page.
     */
    public function render(): void {
        $error   = sanitize_text_field( $_GET['error'] ?? '' );
        $success = absint( $_GET['success'] ?? 0 );

        include AITF_PLUGIN_DIR . 'templates/generate-url.php';
    }
}
