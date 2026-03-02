<?php
/**
 * Categories Page — manage fixed categories for AI-generated posts.
 *
 * @package AITF\Admin
 */

namespace AITF\Admin;

use AITF\Core\Category_Manager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Categories_Page {

    private Category_Manager $manager;

    public function __construct() {
        $this->manager = new Category_Manager();
        add_action( 'admin_init', [ $this, 'handle_actions' ] );
    }

    /**
     * Handle add/remove category actions.
     */
    public function handle_actions(): void {
        if ( isset( $_POST['aitf_add_category_nonce'] ) ) {
            $this->handle_add();
        }

        if ( isset( $_GET['aitf_action'] ) && $_GET['aitf_action'] === 'remove_category' ) {
            $this->handle_remove();
        }

        if ( isset( $_POST['aitf_init_categories_nonce'] ) ) {
            $this->handle_auto_init();
        }
    }

    /**
     * Handle adding a category.
     */
    private function handle_add(): void {
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aitf_add_category_nonce'] ) ), 'aitf_add_category' ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $name = sanitize_text_field( wp_unslash( $_POST['category_name'] ?? '' ) );
        if ( empty( $name ) ) {
            $this->redirect_with_error( 'Please enter a category name.' );
            return;
        }

        $result = $this->manager->add_category( $name );
        if ( is_wp_error( $result ) ) {
            $this->redirect_with_error( $result->get_error_message() );
            return;
        }

        wp_safe_redirect( add_query_arg( [
            'page'       => 'aitf-categories',
            'aitf_added' => 1,
        ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Handle removing a category from fixed list.
     */
    private function handle_remove(): void {
        $term_id = absint( $_GET['term_id'] ?? 0 );
        if ( ! $term_id ) {
            return;
        }

        check_admin_referer( 'aitf_remove_category_' . $term_id );

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $this->manager->remove_category( $term_id );

        wp_safe_redirect( add_query_arg( [
            'page'         => 'aitf-categories',
            'aitf_removed' => 1,
        ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Handle auto-initialization of default categories.
     */
    private function handle_auto_init(): void {
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aitf_init_categories_nonce'] ) ), 'aitf_init_categories' ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $this->manager->auto_initialize();

        wp_safe_redirect( add_query_arg( [
            'page'       => 'aitf-categories',
            'aitf_init'  => 1,
        ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Redirect with error.
     */
    private function redirect_with_error( string $message ): void {
        wp_safe_redirect( add_query_arg( [
            'page'       => 'aitf-categories',
            'aitf_error' => urlencode( $message ),
        ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Render the categories page.
     */
    public function render(): void {
        $categories   = $this->manager->get_fixed_category_terms();
        $fixed_ids    = $this->manager->get_fixed_categories();
        $max_reached  = count( $fixed_ids ) >= Category_Manager::MAX_CATEGORIES;

        include AITF_PLUGIN_DIR . 'templates/categories.php';
    }
}
