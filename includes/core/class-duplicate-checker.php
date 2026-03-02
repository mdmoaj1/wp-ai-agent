<?php
/**
 * Duplicate Checker — prevents generating articles for topics we already covered.
 *
 * Uses SHA-256 hashes of normalized titles.
 *
 * @package AITF\Core
 */

namespace AITF\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Duplicate_Checker {

    /** @var string */
    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'aitf_content_hashes';
    }

    /**
     * Check if a title has already been used.
     *
     * @param string $title
     * @return bool
     */
    public function is_duplicate( string $title ): bool {
        global $wpdb;

        $hash = $this->hash_title( $title );
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE title_hash = %s",
            $hash
        ) );

        return (int) $count > 0;
    }

    /**
     * Store a hash record after successful post creation.
     *
     * @param string $title
     * @param string $slug
     * @param string $source_url
     * @param int    $post_id
     * @return bool
     */
    public function store( string $title, string $slug, string $source_url, int $post_id ): bool {
        global $wpdb;

        return (bool) $wpdb->insert(
            $this->table,
            [
                'title_hash'      => $this->hash_title( $title ),
                'slug_hash'       => hash( 'sha256', $this->normalize( $slug ) ),
                'source_url'      => sanitize_url( $source_url ),
                'created_post_id' => $post_id,
                'created_at'      => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%d', '%s' ]
        );
    }

    /**
     * Hash a title: normalize → SHA-256.
     *
     * @param string $title
     * @return string
     */
    private function hash_title( string $title ): string {
        return hash( 'sha256', $this->normalize( $title ) );
    }

    /**
     * Normalize a string for hashing: lowercase, strip punctuation, collapse spaces.
     *
     * @param string $text
     * @return string
     */
    private function normalize( string $text ): string {
        $text = strtolower( trim( $text ) );
        $text = preg_replace( '/[^\p{L}\p{N}\s]/u', '', $text );
        $text = preg_replace( '/\s+/', ' ', $text );
        return $text;
    }
}
