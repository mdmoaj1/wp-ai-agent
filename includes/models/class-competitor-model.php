<?php
/**
 * Competitor data model — CRUD operations for the aitf_competitors table.
 *
 * @package AITF\Models
 */

namespace AITF\Models;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Competitor_Model {

    /** @var string */
    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'aitf_competitors';
    }

    /*--------------------------------------------------------------
     * CRUD
     *------------------------------------------------------------*/

    /**
     * Insert a new competitor.
     *
     * @param array $data { site_url, site_name, status }
     * @return int|false Insert ID or false.
     */
    public function insert( array $data ) {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table,
            [
                'site_url'   => sanitize_url( $data['site_url'] ),
                'site_name'  => sanitize_text_field( $data['site_name'] ?? '' ),
                'status'     => sanitize_text_field( $data['status'] ?? 'active' ),
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s' ]
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get a single competitor by ID.
     *
     * @param int $id
     * @return object|null
     */
    public function get( int $id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) );
    }

    /**
     * Get all active competitors.
     *
     * @return array
     */
    public function get_all_active(): array {
        global $wpdb;
        return $wpdb->get_results( "SELECT * FROM {$this->table} WHERE status = 'active' ORDER BY created_at DESC" );
    }

    /**
     * Get all competitors with optional status filter.
     *
     * @param string|null $status
     * @return array
     */
    public function get_all( ?string $status = null ): array {
        global $wpdb;

        if ( $status ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE status = %s ORDER BY created_at DESC",
                $status
            ) );
        }

        return $wpdb->get_results( "SELECT * FROM {$this->table} ORDER BY created_at DESC" );
    }

    /**
     * Update a competitor record.
     *
     * @param int   $id
     * @param array $data
     * @return bool
     */
    public function update( int $id, array $data ): bool {
        global $wpdb;

        $allowed = [ 'site_url', 'site_name', 'last_fetched_post_id', 'last_fetched_at', 'status' ];
        $update  = [];
        $formats = [];

        foreach ( $allowed as $col ) {
            if ( isset( $data[ $col ] ) ) {
                $update[ $col ] = $data[ $col ];
                $formats[]      = is_int( $data[ $col ] ) ? '%d' : '%s';
            }
        }

        if ( empty( $update ) ) {
            return false;
        }

        return (bool) $wpdb->update( $this->table, $update, [ 'id' => $id ], $formats, [ '%d' ] );
    }

    /**
     * Delete a competitor.
     *
     * @param int $id
     * @return bool
     */
    public function delete( int $id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( $this->table, [ 'id' => $id ], [ '%d' ] );
    }

    /**
     * Check if a URL already exists.
     *
     * @param string $url
     * @return bool
     */
    public function url_exists( string $url ): bool {
        global $wpdb;

        $normalized = trailingslashit( strtolower( sanitize_url( $url ) ) );
        $count      = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE LOWER(site_url) LIKE %s",
            '%' . $wpdb->esc_like( $normalized ) . '%'
        ) );

        return (int) $count > 0;
    }

    /**
     * Get total count of competitors.
     *
     * @return int
     */
    public function count(): int {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
    }
}
