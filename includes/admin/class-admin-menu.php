<?php
/**
 * Admin Menu — registers the top-level menu and subpages.
 *
 * @package AITF\Admin
 */

namespace AITF\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin_Menu {

    private Single_URL_Page      $single_url_page;
    private Stock_Footages_Page  $stock_footages_page;

    public function __construct() {
        $this->single_url_page    = new Single_URL_Page();
        $this->stock_footages_page = new Stock_Footages_Page();

        add_action( 'admin_menu', [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Register admin menu and sub-pages.
     */
    public function register_menus(): void {

        // Top-level menu.
        add_menu_page(
            __( 'AI Content Generator', 'ai-for-techforus' ),
            __( 'AI Content Gen', 'ai-for-techforus' ),
            'manage_options',
            'aitf-settings',
            [ new Settings_Page(), 'render' ],
            'dashicons-welcome-write-blog',
            30
        );

        // Sub-pages.
        add_submenu_page(
            'aitf-settings',
            __( 'Settings', 'ai-for-techforus' ),
            __( 'Settings', 'ai-for-techforus' ),
            'manage_options',
            'aitf-settings',
            [ new Settings_Page(), 'render' ]
        );

        add_submenu_page(
            'aitf-settings',
            __( 'Competitors', 'ai-for-techforus' ),
            __( 'Competitors', 'ai-for-techforus' ),
            'manage_options',
            'aitf-competitors',
            [ new Competitors_Page(), 'render' ]
        );

        add_submenu_page(
            'aitf-settings',
            __( 'Categories', 'ai-for-techforus' ),
            __( 'Categories', 'ai-for-techforus' ),
            'manage_options',
            'aitf-categories',
            [ new Categories_Page(), 'render' ]
        );

        add_submenu_page(
            'aitf-settings',
            __( 'Generate from URL', 'ai-for-techforus' ),
            __( 'Generate from URL', 'ai-for-techforus' ),
            'manage_options',
            'aitf-generate-url',
            [ $this->single_url_page, 'render' ]
        );

        add_submenu_page(
            'aitf-settings',
            __( 'Stock Footages', 'ai-for-techforus' ),
            __( 'Stock Footages', 'ai-for-techforus' ),
            'manage_options',
            'aitf-stock-footages',
            [ $this->stock_footages_page, 'render' ]
        );

        add_submenu_page(
            'aitf-settings',
            __( 'Logs', 'ai-for-techforus' ),
            __( 'Logs', 'ai-for-techforus' ),
            'manage_options',
            'aitf-logs',
            [ new Logs_Page(), 'render' ]
        );
    }

    /**
     * Enqueue admin CSS + JS on our plugin pages only.
     *
     * @param string $hook_suffix
     */
    public function enqueue_assets( string $hook_suffix ): void {
        // Only on our pages.
        if ( strpos( $hook_suffix, 'aitf-' ) === false && $hook_suffix !== 'toplevel_page_aitf-settings' ) {
            return;
        }

        wp_enqueue_style(
            'aitf-admin',
            AITF_PLUGIN_URL . 'assets/css/admin.css',
            [],
            AITF_VERSION
        );

        wp_enqueue_script(
            'aitf-admin',
            AITF_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            AITF_VERSION,
            true
        );

        wp_localize_script( 'aitf-admin', 'aitfAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'aitf_ajax_nonce' ),
            'i18n'    => [
                'confirmDelete' => __( 'Are you sure you want to delete this?', 'ai-for-techforus' ),
                'validating'    => __( 'Validating...', 'ai-for-techforus' ),
                'running'       => __( 'Running... This may take a few minutes.', 'ai-for-techforus' ),
            ],
        ] );

        // Stock Footages page: AJAX search script (no page reload).
        if ( strpos( $hook_suffix, 'aitf-stock-footages' ) !== false ) {
            wp_enqueue_script(
                'aitf-stock-footages',
                AITF_PLUGIN_URL . 'assets/js/stock-footages.js',
                [ 'jquery' ],
                AITF_VERSION,
                true
            );
            wp_localize_script( 'aitf-stock-footages', 'aitfStockFootages', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'aitf_ajax_nonce' ),
                'i18n'    => [
                    'searching' => __( 'Searching...', 'ai-for-techforus' ),
                    'noResults' => __( 'No images found. Try another keyword or check API keys in Settings.', 'ai-for-techforus' ),
                    'error'     => __( 'Request failed. Please try again.', 'ai-for-techforus' ),
                ],
            ] );
        }
    }
}
