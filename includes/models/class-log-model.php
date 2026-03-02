<?php
/**
 * Log data model — CRUD + query operations for the aitf_logs table.
 *
 * @package AITF\Models
 */

namespace AITF\Models;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Log_Model {

    /** @var string */
    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'aitf_logs';
    }

    /*--------------------------------------------------------------
     * Write
     *------------------------------------------------------------*/

    /**
     * Insert a log entry.
     *
     * @param array $data {
     *     @type string $event_type    cron_run|fetch|generate|publish|error
     *     @type string $competitor_url
     *     @type string $status        success|fail
     *     @type string $message
     *     @type int    $post_id
     *     @type int    $token_usage
     *     @type string $provider      groq|openai
     * }
     * @return int|false Insert ID or false.
     */
    public function insert( array $data ) {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table,
            [
                'timestamp'      => current_time( 'mysql' ),
                'event_type'     => sanitize_text_field( $data['event_type'] ?? '' ),
                'competitor_url' => sanitize_url( $data['competitor_url'] ?? '' ),
                'status'         => sanitize_text_field( $data['status'] ?? 'success' ),
                'message'        => sanitize_textarea_field( $data['message'] ?? '' ),
                'post_id'        => isset( $data['post_id'] ) ? absint( $data['post_id'] ) : null,
                'token_usage'    => isset( $data['token_usage'] ) ? absint( $data['token_usage'] ) : null,
                'provider'       => sanitize_text_field( $data['provider'] ?? '' ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' ]
        );

        return $result ? $wpdb->insert_id : false;
    }

    /*--------------------------------------------------------------
     * Read
     *------------------------------------------------------------*/

    /**
     * Get paginated, filtered log entries.
     *
     * @param array $filters { date_from, date_to, event_type, status, competitor_url }
     * @param int   $per_page
     * @param int   $page
     * @return object { items: array, total: int }
     */
    public function query( array $filters = [], int $per_page = 25, int $page = 1 ): object {
        global $wpdb;

        $where  = [];
        $values = [];

        if ( ! empty( $filters['date_from'] ) ) {
            $where[]  = 'timestamp >= %s';
            $values[] = sanitize_text_field( $filters['date_from'] ) . ' 00:00:00';
        }

        if ( ! empty( $filters['date_to'] ) ) {
            $where[]  = 'timestamp <= %s';
            $values[] = sanitize_text_field( $filters['date_to'] ) . ' 23:59:59';
        }

        if ( ! empty( $filters['event_type'] ) ) {
            $where[]  = 'event_type = %s';
            $values[] = sanitize_text_field( $filters['event_type'] );
        }

        if ( ! empty( $filters['status'] ) ) {
            $where[]  = 'status = %s';
            $values[] = sanitize_text_field( $filters['status'] );
        }

        if ( ! empty( $filters['competitor_url'] ) ) {
            $where[]  = 'competitor_url LIKE %s';
            $values[] = '%' . $wpdb->esc_like( sanitize_url( $filters['competitor_url'] ) ) . '%';
        }

        $where_sql = '';
        if ( ! empty( $where ) ) {
            $where_sql = 'WHERE ' . implode( ' AND ', $where );
        }

        // Total count.
        $count_sql = "SELECT COUNT(*) FROM {$this->table} {$where_sql}";
        $total     = empty( $values )
            ? (int) $wpdb->get_var( $count_sql )
            : (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$values ) );

        // Items.
        $offset    = max( 0, ( $page - 1 ) * $per_page );
        $query_sql = "SELECT * FROM {$this->table} {$where_sql} ORDER BY timestamp DESC LIMIT %d OFFSET %d";

        $query_values   = array_merge( $values, [ $per_page, $offset ] );
        $items          = $wpdb->get_results( $wpdb->prepare( $query_sql, ...$query_values ) );

        return (object) [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * Get all matching log entries for CSV export.
     *
     * @param array $filters Same as query().
     * @return array
     */
    public function get_all_for_export( array $filters = [] ): array {
        global $wpdb;

        $where  = [];
        $values = [];

        if ( ! empty( $filters['date_from'] ) ) {
            $where[]  = 'timestamp >= %s';
            $values[] = sanitize_text_field( $filters['date_from'] ) . ' 00:00:00';
        }

        if ( ! empty( $filters['date_to'] ) ) {
            $where[]  = 'timestamp <= %s';
            $values[] = sanitize_text_field( $filters['date_to'] ) . ' 23:59:59';
        }

        if ( ! empty( $filters['event_type'] ) ) {
            $where[]  = 'event_type = %s';
            $values[] = sanitize_text_field( $filters['event_type'] );
        }

        if ( ! empty( $filters['status'] ) ) {
            $where[]  = 'status = %s';
            $values[] = sanitize_text_field( $filters['status'] );
        }

        $where_sql = '';
        if ( ! empty( $where ) ) {
            $where_sql = 'WHERE ' . implode( ' AND ', $where );
        }

        $sql = "SELECT * FROM {$this->table} {$where_sql} ORDER BY timestamp DESC";

        return empty( $values )
            ? $wpdb->get_results( $sql, ARRAY_A )
            : $wpdb->get_results( $wpdb->prepare( $sql, ...$values ), ARRAY_A );
    }

    /**
     * Delete logs older than a given number of days.
     *
     * @param int $days
     * @return int Number of rows deleted.
     */
    public function purge_older_than( int $days ): int {
        global $wpdb;
        return (int) $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$this->table} WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) );
    }
}
