<?php
/**
 * Category Manager — handles fixed-category system for AI-generated posts.
 *
 * @package AITF\Core
 */

namespace AITF\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Category_Manager {

    /** @var int Maximum fixed categories. */
    const MAX_CATEGORIES = 10;

    /**
     * Get the list of fixed category term IDs.
     *
     * @return array
     */
    public function get_fixed_categories(): array {
        return get_option( 'aitf_fixed_categories', [] );
    }

    /**
     * Get fixed categories with full term objects.
     *
     * @return array Array of WP_Term objects.
     */
    public function get_fixed_category_terms(): array {
        $ids = $this->get_fixed_categories();
        if ( empty( $ids ) ) {
            return [];
        }

        $terms = get_terms( [
            'taxonomy'   => 'category',
            'include'    => $ids,
            'hide_empty' => false,
        ] );

        return is_array( $terms ) ? $terms : [];
    }

    /**
     * Get category names for the AI prompt.
     *
     * @return array
     */
    public function get_category_names(): array {
        $terms = $this->get_fixed_category_terms();
        return array_map( fn( $t ) => $t->name, $terms );
    }

    /**
     * Add a category to the fixed list.
     *
     * @param string $name Category name.
     * @return int|\WP_Error Term ID or error.
     */
    public function add_category( string $name ): mixed {
        $ids = $this->get_fixed_categories();

        if ( count( $ids ) >= self::MAX_CATEGORIES ) {
            return new \WP_Error( 'max_reached', 'Maximum of ' . self::MAX_CATEGORIES . ' categories allowed.' );
        }

        // Create or get existing WP category.
        $term = term_exists( $name, 'category' );
        if ( $term ) {
            $term_id = (int) $term['term_id'];
        } else {
            $result = wp_insert_term( $name, 'category' );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
            $term_id = (int) $result['term_id'];
        }

        // Add to fixed list if not already there.
        if ( ! in_array( $term_id, $ids, true ) ) {
            $ids[] = $term_id;
            update_option( 'aitf_fixed_categories', $ids );
        }

        return $term_id;
    }

    /**
     * Remove a category from the fixed list (does NOT delete the WP category).
     *
     * @param int $term_id
     * @return bool
     */
    public function remove_category( int $term_id ): bool {
        $ids = $this->get_fixed_categories();
        $ids = array_filter( $ids, fn( $id ) => $id !== $term_id );
        return update_option( 'aitf_fixed_categories', array_values( $ids ) );
    }

    /**
     * Auto-initialize default categories if none exist.
     * Called during first content generation when categories are empty.
     *
     * @param string $niche Detected site niche (from existing content or AI).
     * @return array Created term IDs.
     */
    public function auto_initialize( string $niche = '' ): array {
        if ( ! empty( $this->get_fixed_categories() ) ) {
            return $this->get_fixed_categories();
        }

        // Default general categories if no niche provided.
        $defaults = [
            'Technology',
            'Business',
            'How-To Guides',
            'Reviews',
            'News',
            'Tips & Tricks',
            'Tutorials',
            'Industry Insights',
        ];

        $ids = [];
        foreach ( $defaults as $name ) {
            $result = $this->add_category( $name );
            if ( ! is_wp_error( $result ) ) {
                $ids[] = $result;
            }
        }

        return $ids;
    }

    /**
     * Match AI-suggested categories against the fixed list.
     * Returns the best 1-2 matching term IDs.
     *
     * @param array $suggested_names Category names suggested by AI.
     * @return array Term IDs (max 2).
     */
    public function match_categories( array $suggested_names ): array {
        $settings = get_option( 'aitf_settings', [] );
        $mode     = $settings['category_mode'] ?? 'fixed';

        $fixed_terms = $this->get_fixed_category_terms();
        if ( empty( $fixed_terms ) ) {
            $this->auto_initialize();
            $fixed_terms = $this->get_fixed_category_terms();
        }

        $matched = [];

        foreach ( $suggested_names as $suggested ) {
            $suggested_lower = strtolower( trim( $suggested ) );

            foreach ( $fixed_terms as $term ) {
                // Exact or near match.
                if (
                    strtolower( $term->name ) === $suggested_lower
                    || str_contains( strtolower( $term->name ), $suggested_lower )
                    || str_contains( $suggested_lower, strtolower( $term->name ) )
                ) {
                    $matched[] = $term->term_id;
                    break;
                }
            }

            if ( count( $matched ) >= 2 ) {
                break;
            }
        }

        // If no match found, default to the first fixed category.
        if ( empty( $matched ) && ! empty( $fixed_terms ) ) {
            $matched[] = $fixed_terms[0]->term_id;
        }

        return array_unique( array_slice( $matched, 0, 2 ) );
    }

    /**
     * Check if category mode is "fixed".
     *
     * @return bool
     */
    public function is_fixed_mode(): bool {
        $settings = get_option( 'aitf_settings', [] );
        return ( $settings['category_mode'] ?? 'fixed' ) === 'fixed';
    }
}
