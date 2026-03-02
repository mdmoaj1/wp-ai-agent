<?php
/**
 * Stock Footages Page — search Pixabay/Pexels photos via AJAX (no page reload).
 *
 * @package AITF\Admin
 */

namespace AITF\Admin;

use AITF\Core\Image_Generator;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Stock_Footages_Page {

    public function __construct() {
        add_action( 'wp_ajax_aitf_search_stock_photos', [ $this, 'ajax_search_stock_photos' ] );
    }

    /**
     * AJAX handler: search stock photos and return JSON.
     */
    public function ajax_search_stock_photos(): void {
        check_ajax_referer( 'aitf_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ai-for-techforus' ) ] );
        }

        $keyword = isset( $_GET['keyword'] ) ? sanitize_text_field( wp_unslash( $_GET['keyword'] ) ) : '';
        $keyword = trim( $keyword );
        if ( strlen( $keyword ) < 2 ) {
            wp_send_json_success( [
                'results' => [],
                'message' => __( 'Enter at least 2 characters to search.', 'ai-for-techforus' ),
            ] );
        }

        $limit   = min( 30, max( 5, (int) ( $_GET['limit'] ?? 20 ) ) );
        $gen     = new Image_Generator();
        $results = $gen->search_stock_photos( $keyword, $limit );

        wp_send_json_success( [
            'results' => $results,
            'message' => empty( $results )
                ? __( 'No images found. Check Settings for Pixabay/Pexels API keys.', 'ai-for-techforus' )
                : '',
        ] );
    }

    /**
     * Render the Stock Footages admin page.
     */
    public function render(): void {
        $settings = get_option( 'aitf_settings', [] );
        $has_keys = ! empty( $settings['pixabay_api_key'] ) || ! empty( $settings['pexels_api_key'] );

        include AITF_PLUGIN_DIR . 'templates/stock-footages.php';
    }
}
