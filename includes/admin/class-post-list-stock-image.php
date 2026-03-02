<?php
/**
 * Post List Stock Image — add "Stock image" row action; modal to search and set featured image via AJAX.
 *
 * @package AITF\Admin
 */

namespace AITF\Admin;

use AITF\Core\Image_Generator;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Post_List_Stock_Image {

    public function __construct() {
        add_filter( 'post_row_actions', [ $this, 'add_row_action' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_footer-edit.php', [ $this, 'render_modal' ] );
        add_action( 'wp_ajax_aitf_set_featured_from_url', [ $this, 'ajax_set_featured_from_url' ] );
    }

    /**
     * Add "Stock image" to post row actions.
     *
     * @param array    $actions
     * @param \WP_Post $post
     * @return array
     */
    public function add_row_action( array $actions, \WP_Post $post ): array {
        if ( $post->post_type !== 'post' || $post->post_status === 'trash' ) {
            return $actions;
        }

        $actions['aitf_stock_image'] = sprintf(
            '<a href="#" class="aitf-stock-image-link" data-post-id="%d" data-post-title="%s">%s</a>',
            (int) $post->ID,
            esc_attr( $post->post_title ),
            esc_html__( 'Stock image', 'ai-for-techforus' )
        );

        return $actions;
    }

    /**
     * Enqueue script only on Posts list (edit.php).
     */
    public function enqueue_assets( string $hook_suffix ): void {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'edit-post' ) {
            return;
        }

        wp_enqueue_style(
            'aitf-admin',
            AITF_PLUGIN_URL . 'assets/css/admin.css',
            [],
            AITF_VERSION
        );

        wp_enqueue_script(
            'aitf-post-list-stock-image',
            AITF_PLUGIN_URL . 'assets/js/post-list-stock-image.js',
            [ 'jquery' ],
            AITF_VERSION,
            true
        );

        wp_localize_script( 'aitf-post-list-stock-image', 'aitfPostListStock', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'aitf_ajax_nonce' ),
            'i18n'    => [
                'search'        => __( 'Search', 'ai-for-techforus' ),
                'searching'     => __( 'Searching...', 'ai-for-techforus' ),
                'setFeatured'   => __( 'Set as featured', 'ai-for-techforus' ),
                'setting'      => __( 'Setting...', 'ai-for-techforus' ),
                'done'         => __( 'Featured image set.', 'ai-for-techforus' ),
                'error'        => __( 'Failed to set image. Try again.', 'ai-for-techforus' ),
                'noResults'    => __( 'No images found. Try another keyword or add API keys in Settings.', 'ai-for-techforus' ),
                'close'        => __( 'Close', 'ai-for-techforus' ),
                'keywordPlaceholder' => __( 'e.g. technology, office', 'ai-for-techforus' ),
            ],
        ] );
    }

    /**
     * Output modal markup in Posts list footer.
     */
    public function render_modal(): void {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'edit-post' ) {
            return;
        }
        ?>
        <div id="aitf-stock-image-modal" class="aitf-modal" style="display:none;">
            <div class="aitf-modal-backdrop"></div>
            <div class="aitf-modal-content">
                <div class="aitf-modal-header">
                    <h2><?php esc_html_e( 'Set featured image from stock', 'ai-for-techforus' ); ?></h2>
                    <button type="button" class="aitf-modal-close" aria-label="<?php esc_attr_e( 'Close', 'ai-for-techforus' ); ?>">&times;</button>
                </div>
                <div class="aitf-modal-body">
                    <input type="hidden" id="aitf-stock-image-post-id" value="">
                    <div class="aitf-stock-search-row">
                        <input type="search" id="aitf-stock-image-keyword" class="regular-text" placeholder="<?php esc_attr_e( 'Search keyword', 'ai-for-techforus' ); ?>" autocomplete="off">
                        <button type="button" id="aitf-stock-image-search-btn" class="button button-primary"><?php esc_html_e( 'Search', 'ai-for-techforus' ); ?></button>
                    </div>
                    <div id="aitf-stock-image-message" class="aitf-stock-image-message"></div>
                    <div id="aitf-stock-image-results" class="aitf-stock-image-results"></div>
                </div>
                <div class="aitf-modal-footer">
                    <button type="button" class="button aitf-modal-close"><?php esc_html_e( 'Close', 'ai-for-techforus' ); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: set featured image from URL (download, attach, set thumbnail).
     */
    public function ajax_set_featured_from_url(): void {
        check_ajax_referer( 'aitf_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ai-for-techforus' ) ] );
        }

        $post_id   = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $image_url = isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '';

        if ( ! $post_id || ! $image_url ) {
            wp_send_json_error( [ 'message' => __( 'Missing post ID or image URL.', 'ai-for-techforus' ) ] );
        }

        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'post' ) {
            wp_send_json_error( [ 'message' => __( 'Invalid post.', 'ai-for-techforus' ) ] );
        }

        $gen   = new Image_Generator();
        $result = $gen->attach_image_url_to_post( $post_id, $image_url, $post->post_title );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        $thumb_url = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
        wp_send_json_success( [
            'attachment_id' => $result,
            'thumbnail_url' => $thumb_url ?: '',
        ] );
    }
}
