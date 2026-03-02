<?php
/**
 * Logs Page — displays log entries with filtering, pagination, and CSV export.
 *
 * @package AITF\Admin
 */

namespace AITF\Admin;

use AITF\Models\Log_Model;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Logs_Page {

    private Log_Model $model;

    public function __construct() {
        $this->model = new Log_Model();
        add_action( 'admin_init', [ $this, 'handle_export' ] );
    }

    /**
     * Handle CSV export.
     */
    public function handle_export(): void {
        if ( ! isset( $_GET['aitf_action'] ) || $_GET['aitf_action'] !== 'export_logs' ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        check_admin_referer( 'aitf_export_logs' );

        $filters = $this->get_filters();
        $rows    = $this->model->get_all_for_export( $filters );

        // Send CSV headers.
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=aitf-logs-' . gmdate( 'Y-m-d' ) . '.csv' );

        $output = fopen( 'php://output', 'w' );

        // Header row.
        fputcsv( $output, [ 'ID', 'Timestamp', 'Event Type', 'Competitor URL', 'Status', 'Message', 'Post ID', 'Tokens', 'Provider' ] );

        foreach ( $rows as $row ) {
            fputcsv( $output, [
                $row['id'],
                $row['timestamp'],
                $row['event_type'],
                $row['competitor_url'],
                $row['status'],
                $row['message'],
                $row['post_id'],
                $row['token_usage'],
                $row['provider'],
            ] );
        }

        fclose( $output );
        exit;
    }

    /**
     * Get filters from query parameters.
     *
     * @return array
     */
    private function get_filters(): array {
        return [
            'date_from'      => sanitize_text_field( $_GET['date_from'] ?? '' ),
            'date_to'        => sanitize_text_field( $_GET['date_to'] ?? '' ),
            'event_type'     => sanitize_text_field( $_GET['event_type'] ?? '' ),
            'status'         => sanitize_text_field( $_GET['filter_status'] ?? '' ),
            'competitor_url' => sanitize_text_field( $_GET['competitor_url'] ?? '' ),
        ];
    }

    /**
     * Render the logs page.
     */
    public function render(): void {
        $filters  = $this->get_filters();
        $per_page = 25;
        $page     = max( 1, absint( $_GET['paged'] ?? 1 ) );

        $result     = $this->model->query( $filters, $per_page, $page );
        $logs       = $result->items;
        $total      = $result->total;
        $total_pages = ceil( $total / $per_page );

        $export_url = wp_nonce_url( add_query_arg( array_merge(
            [ 'page' => 'aitf-logs', 'aitf_action' => 'export_logs' ],
            array_filter( $filters )
        ), admin_url( 'admin.php' ) ), 'aitf_export_logs' );

        include AITF_PLUGIN_DIR . 'templates/logs.php';
    }
}
