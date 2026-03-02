<?php
/**
 * Competitors Page — add, validate, remove competitor WordPress sites.
 *
 * @package AITF\Admin
 */

namespace AITF\Admin;

use AITF\Core\Competitor_Fetcher;
use AITF\Models\Competitor_Model;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Competitors_Page {

    private Competitor_Model $model;

    public function __construct() {
        $this->model = new Competitor_Model();
        add_action( 'admin_init', [ $this, 'handle_actions' ] );
    }

    /**
     * Handle add/delete actions.
     */
    public function handle_actions(): void {
        // Add competitor.
        if ( isset( $_POST['aitf_add_competitor_nonce'] ) ) {
            $this->handle_add();
        }

        // Delete competitor.
        if ( isset( $_GET['aitf_action'] ) && $_GET['aitf_action'] === 'delete_competitor' ) {
            $this->handle_delete();
        }

        // Toggle status.
        if ( isset( $_GET['aitf_action'] ) && $_GET['aitf_action'] === 'toggle_competitor' ) {
            $this->handle_toggle();
        }
    }

    /**
     * Handle adding a new competitor.
     */
    private function handle_add(): void {
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aitf_add_competitor_nonce'] ) ), 'aitf_add_competitor' ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $url = esc_url_raw( wp_unslash( $_POST['competitor_url'] ?? '' ) );
        if ( empty( $url ) ) {
            $this->redirect_with_error( 'Please enter a URL.' );
            return;
        }

        // Normalize URL.
        $url = trailingslashit( $url );

        // Check if already exists.
        if ( $this->model->url_exists( $url ) ) {
            $this->redirect_with_error( 'This competitor is already in your list.' );
            return;
        }

        // Validate REST API.
        $fetcher    = new Competitor_Fetcher();
        $validation = $fetcher->validate_rest_api( $url );

        if ( is_wp_error( $validation ) ) {
            $this->redirect_with_error( $validation->get_error_message() );
            return;
        }

        // Insert.
        $result = $this->model->insert( [
            'site_url'  => $url,
            'site_name' => $validation['site_name'],
        ] );

        if ( ! $result ) {
            $this->redirect_with_error( 'Failed to save competitor. Database error.' );
            return;
        }

        wp_safe_redirect( add_query_arg( [
            'page'       => 'aitf-competitors',
            'aitf_added' => 1,
        ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Handle deleting a competitor.
     */
    private function handle_delete(): void {
        $id = absint( $_GET['competitor_id'] ?? 0 );
        if ( ! $id ) {
            return;
        }

        check_admin_referer( 'aitf_delete_competitor_' . $id );

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $this->model->delete( $id );

        wp_safe_redirect( add_query_arg( [
            'page'         => 'aitf-competitors',
            'aitf_deleted' => 1,
        ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Handle toggling a competitor's status (active/paused).
     */
    private function handle_toggle(): void {
        $id = absint( $_GET['competitor_id'] ?? 0 );
        if ( ! $id ) {
            return;
        }

        check_admin_referer( 'aitf_toggle_competitor_' . $id );

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $competitor = $this->model->get( $id );
        if ( ! $competitor ) {
            return;
        }

        $new_status = $competitor->status === 'active' ? 'paused' : 'active';
        $this->model->update( $id, [ 'status' => $new_status ] );

        wp_safe_redirect( add_query_arg( [
            'page'          => 'aitf-competitors',
            'aitf_toggled'  => 1,
        ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Redirect with an error message.
     */
    private function redirect_with_error( string $message ): void {
        wp_safe_redirect( add_query_arg( [
            'page'       => 'aitf-competitors',
            'aitf_error' => urlencode( $message ),
        ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Render the competitors page.
     */
    public function render(): void {
        $competitors = $this->model->get_all();
        $count       = $this->model->count();

        include AITF_PLUGIN_DIR . 'templates/competitors.php';
    }
}
