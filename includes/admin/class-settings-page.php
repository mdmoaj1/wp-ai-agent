<?php
/**
 * Settings Page — API configuration, publishing mode, language, etc.
 *
 * @package AITF\Admin
 */

namespace AITF\Admin;

use AITF\Core\Sitemap_Pinger;
use AITF\Cron\Cron_Handler;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Settings_Page {

    public function __construct() {
        add_action( 'admin_init', [ $this, 'handle_save' ] );
    }

    /**
     * Handle form submission.
     */
    public function handle_save(): void {
        if ( ! isset( $_POST['aitf_settings_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aitf_settings_nonce'] ) ), 'aitf_save_settings' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = [
            'api_provider'            => sanitize_text_field( wp_unslash( $_POST['api_provider'] ?? 'groq' ) ),
            'api_key'                 => sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) ),
            'model'                   => sanitize_text_field( wp_unslash( $_POST['model'] ?? '' ) ),
            'language'                => sanitize_text_field( wp_unslash( $_POST['language'] ?? 'en' ) ),
            'article_length'          => absint( $_POST['article_length'] ?? 1500 ),
            'publish_mode'            => sanitize_text_field( wp_unslash( $_POST['publish_mode'] ?? 'draft' ) ),
            'category_mode'           => sanitize_text_field( wp_unslash( $_POST['category_mode'] ?? 'fixed' ) ),
            'enable_featured_image'   => isset( $_POST['enable_featured_image'] ) ? '1' : '0',
            'pixabay_api_key'         => sanitize_text_field( wp_unslash( $_POST['pixabay_api_key'] ?? '' ) ),
            'pexels_api_key'          => sanitize_text_field( wp_unslash( $_POST['pexels_api_key'] ?? '' ) ),
            'image_text_position'     => sanitize_text_field( wp_unslash( $_POST['image_text_position'] ?? 'bottom-left' ) ),
            'image_gradient_opacity'  => absint( $_POST['image_gradient_opacity'] ?? 70 ),
        ];

        update_option( 'aitf_settings', $settings );

        // Redirect with success message.
        wp_safe_redirect( add_query_arg( [
            'page'          => 'aitf-settings',
            'aitf_saved'    => 1,
        ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Render the settings page.
     */
    public function render(): void {
        $settings       = get_option( 'aitf_settings', [] );
        $schedule_info  = Cron_Handler::get_schedule_info();
        $run_now_url    = wp_nonce_url( admin_url( 'admin-post.php?action=aitf_run_now' ), 'aitf_run_now', 'aitf_nonce' );
        $sitemap_next   = Sitemap_Pinger::get_next_ping_time();
        $sitemap_human  = $sitemap_next ? human_time_diff( time(), $sitemap_next ) . ' ' . __( 'from now', 'ai-for-techforus' ) : __( 'Not scheduled', 'ai-for-techforus' );

        include AITF_PLUGIN_DIR . 'templates/settings.php';
    }
}
