<?php
/**
 * Image Generator — handles featured image creation with Pixabay/Pexels + GD overlays.
 *
 * @package AITF\Core
 */

namespace AITF\Core;

use AITF\Models\Log_Model;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Image_Generator {

    private Log_Model $log;

    public function __construct() {
        $this->log = new Log_Model();
    }

    /**
     * Generate and attach a featured image to a post.
     *
     * @param int   $post_id Post ID.
     * @param array $article Generated article data.
     * @return int|false Attachment ID or false on failure.
     */
    public function generate_and_attach( int $post_id, array $article ) {
        $settings = get_option( 'aitf_settings', [] );

        // Check if feature is enabled.
        if ( empty( $settings['enable_featured_image'] ) ) {
            return false;
        }

        // Extract keywords from title; fallback to first 5 words or full title if too short.
        $keyword = $this->extract_keyword( $article['title'] );
        if ( strlen( trim( $keyword ) ) < 2 ) {
            $words = array_slice( array_filter( explode( ' ', sanitize_text_field( $article['title'] ) ) ), 0, 5 );
            $keyword = implode( ' ', $words ) ?: sanitize_text_field( $article['title'] );
        }

        // Search for image (try Pixabay first, then Pexels).
        $image_url = $this->search_image( $keyword, $settings );

        // Retry with full title (first 6 words) if no result and keyword was shortened.
        if ( ! $image_url && strlen( $keyword ) < strlen( trim( $article['title'] ) ) ) {
            $fallback = implode( ' ', array_slice( array_filter( explode( ' ', sanitize_text_field( $article['title'] ) ) ), 0, 6 ) );
            if ( $fallback ) {
                $image_url = $this->search_image( $fallback, $settings );
            }
        }

        if ( ! $image_url ) {
            $pixabay_key = ! empty( $settings['pixabay_api_key'] );
            $pexels_key  = ! empty( $settings['pexels_api_key'] );
            $msg = ( ! $pixabay_key && ! $pexels_key )
                ? 'Stock photo not set: no Pixabay or Pexels API key configured in Settings.'
                : 'Failed to find image for keyword: ' . $keyword;
            $this->log->insert( [
                'event_type' => 'error',
                'status'     => 'fail',
                'message'    => $msg,
                'post_id'    => $post_id,
            ] );
            return false;
        }

        // Download and process image.
        $processed_file = $this->download_and_process( $image_url, $article['title'], $settings );

        if ( is_wp_error( $processed_file ) ) {
            $this->log->insert( [
                'event_type' => 'error',
                'status'     => 'fail',
                'message'    => 'Image processing failed: ' . $processed_file->get_error_message(),
                'post_id'    => $post_id,
            ] );
            return false;
        }

        // Upload to media library.
        $attachment_id = $this->upload_to_media_library( $processed_file, $post_id, $article['title'] );

        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $processed_file );
            return false;
        }

        // Set as featured image.
        set_post_thumbnail( $post_id, $attachment_id );

        // Clean up temp file.
        @unlink( $processed_file );

        $this->log->insert( [
            'event_type' => 'generate',
            'status'     => 'success',
            'message'    => 'Featured image created and attached.',
            'post_id'    => $post_id,
        ] );

        return $attachment_id;
    }

    /**
     * Extract the most important keyword from a title.
     *
     * @param string $title Post title.
     * @return string Primary keyword.
     */
    private function extract_keyword( string $title ): string {
        // Remove common stop words and get the core topic.
        $stop_words = [ 'how', 'to', 'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'for', 'with', 'guide', 'tutorial', 'complete', 'best' ];
        $words = explode( ' ', strtolower( $title ) );
        $words = array_diff( $words, $stop_words );

        // Get first 2-3 meaningful words.
        $keywords = array_slice( $words, 0, 3 );

        return implode( ' ', $keywords );
    }

    /**
     * Search for an image using Pixabay or Pexels.
     *
     * @param string $keyword  Search keyword.
     * @param array  $settings Plugin settings.
     * @return string|false Image URL or false.
     */
    private function search_image( string $keyword, array $settings ) {
        $keyword = trim( $keyword );
        if ( empty( $keyword ) ) {
            return false;
        }

        $pixabay_key = ! empty( $settings['pixabay_api_key'] ) ? $settings['pixabay_api_key'] : '';
        $pexels_key  = ! empty( $settings['pexels_api_key'] ) ? $settings['pexels_api_key'] : '';

        if ( empty( $pixabay_key ) && empty( $pexels_key ) ) {
            return false;
        }

        // Try Pixabay first.
        if ( $pixabay_key ) {
            $url = $this->search_pixabay( $keyword, $pixabay_key );
            if ( $url ) {
                return $url;
            }
        }

        // Fallback to Pexels.
        if ( $pexels_key ) {
            $url = $this->search_pexels( $keyword, $pexels_key );
            if ( $url ) {
                return $url;
            }
        }

        return false;
    }

    /**
     * Search Pixabay API.
     *
     * @param string $keyword Search keyword.
     * @param string $api_key API key.
     * @return string|false Image URL or false.
     */
    private function search_pixabay( string $keyword, string $api_key ) {
        $url = add_query_arg( [
            'key'        => $api_key,
            'q'          => $keyword,
            'image_type' => 'photo',
            'per_page'   => 10,
            'safesearch' => 'true',
        ], 'https://pixabay.com/api/' );

        $response = wp_remote_get( $url, [ 'timeout' => 15 ] );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( empty( $data['hits'] ) || ! is_array( $data['hits'] ) ) {
            return false;
        }

        // Return the highest quality image.
        return $data['hits'][0]['largeImageURL'] ?? $data['hits'][0]['webformatURL'] ?? false;
    }

    /**
     * Search Pexels API.
     *
     * @param string $keyword Search keyword.
     * @param string $api_key API key.
     * @return string|false Image URL or false.
     */
    private function search_pexels( string $keyword, string $api_key ) {
        $url = add_query_arg( [
            'query'    => $keyword,
            'per_page' => 10,
        ], 'https://api.pexels.com/v1/search' );

        $response = wp_remote_get( $url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => $api_key,
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( empty( $data['photos'] ) || ! is_array( $data['photos'] ) ) {
            return false;
        }

        // Return large size.
        return $data['photos'][0]['src']['large'] ?? $data['photos'][0]['src']['original'] ?? false;
    }

    /**
     * Download image and apply gradient overlay + text.
     *
     * @param string $image_url Source image URL.
     * @param string $title     Post title to render.
     * @param array  $settings  Plugin settings.
     * @return string|\WP_Error Path to processed temp file or error.
     */
    private function download_and_process( string $image_url, string $title, array $settings ) {
        // Download image.
        $temp_download = download_url( $image_url );

        if ( is_wp_error( $temp_download ) ) {
            return $temp_download;
        }

        // Load image with GD.
        $image_type = exif_imagetype( $temp_download );

        switch ( $image_type ) {
            case IMAGETYPE_JPEG:
                $img = imagecreatefromjpeg( $temp_download );
                break;
            case IMAGETYPE_PNG:
                $img = imagecreatefrompng( $temp_download );
                break;
            case IMAGETYPE_WEBP:
                $img = imagecreatefromwebp( $temp_download );
                break;
            default:
                @unlink( $temp_download );
                return new \WP_Error( 'unsupported_image_type', 'Unsupported image format.' );
        }

        if ( ! $img ) {
            @unlink( $temp_download );
            return new \WP_Error( 'image_load_failed', 'Failed to load image with GD.' );
        }

        // Resize to standard featured image size.
        $img = $this->resize_image( $img, 1200, 675 );

        // Apply gradient overlay.
        $opacity = absint( $settings['image_gradient_opacity'] ?? 70 );
        $img = $this->apply_gradient_overlay( $img, $opacity );

        // Render text.
        $text_position = $settings['image_text_position'] ?? 'bottom-left';
        $img = $this->render_text( $img, $title, $text_position );

        // Save to temp file.
        $temp_output = wp_tempnam( 'aitf-featured-' );
        imagejpeg( $img, $temp_output, 90 );
        imagedestroy( $img );
        @unlink( $temp_download );

        return $temp_output;
    }

    /**
     * Resize image to target dimensions (cover mode).
     *
     * @param resource $img    GD image resource.
     * @param int      $width  Target width.
     * @param int      $height Target height.
     * @return resource Resized image.
     */
    private function resize_image( $img, int $width, int $height ) {
        $orig_width  = imagesx( $img );
        $orig_height = imagesy( $img );

        // Calculate crop dimensions (cover mode).
        $ratio = max( $width / $orig_width, $height / $orig_height );
        $crop_w = (int) ( $width / $ratio );
        $crop_h = (int) ( $height / $ratio );
        $crop_x = (int) ( ( $orig_width - $crop_w ) / 2 );
        $crop_y = (int) ( ( $orig_height - $crop_h ) / 2 );

        $new_img = imagecreatetruecolor( $width, $height );
        imagecopyresampled( $new_img, $img, 0, 0, $crop_x, $crop_y, $width, $height, $crop_w, $crop_h );
        imagedestroy( $img );

        return $new_img;
    }

    /**
     * Apply gradient overlay (dark to transparent).
     *
     * @param resource $img     GD image resource.
     * @param int      $opacity Opacity (0-100).
     * @return resource Image with gradient.
     */
    /**
     * Apply gradient overlay (transparent top to dark/colored bottom).
     *
     * @param resource $img     GD image resource.
     * @param int      $opacity Opacity (0-100).
     * @return resource Image with gradient.
     */
    private function apply_gradient_overlay( $img, int $opacity ) {
        $width  = imagesx( $img );
        $height = imagesy( $img );

        // Pick a random gradient color shade.
        $colors = [
            'black'  => [ 0, 0, 0 ],
            'blue'   => [ 10, 25, 60 ],
            'red'    => [ 60, 10, 15 ],
            'gold'   => [ 60, 45, 5 ],
            'purple' => [ 40, 10, 50 ],
        ];
        $color_key = array_rand( $colors );
        $rgb = $colors[ $color_key ];

        // Create gradient from bottom to 70% up.
        $gradient_height = (int) ( $height * 0.7 ); 

        for ( $y = $height - $gradient_height; $y < $height; $y++ ) {
            // Calculate progress (0 at top of gradient, 1 at bottom of image).
            $progress = ( $y - ( $height - $gradient_height ) ) / $gradient_height;
            
            // GD Alpha: 0 = Opaque, 127 = Transparent.
            // We want top to be transparent (127) and bottom to be opaque-ish based on setting.
            // Target bottom alpha: 127 - ( 1.27 * opacity ). E.g. 100% opacity -> 0 alpha (fully opaque).
            
            $target_alpha = 127 - (int) ( 1.27 * $opacity );
            $current_alpha = (int) ( 127 - ( ( 127 - $target_alpha ) * $progress ) );
            
            // Limit alpha to valid range.
            $current_alpha = max( 0, min( 127, $current_alpha ) );

            $color = imagecolorallocatealpha( $img, $rgb[0], $rgb[1], $rgb[2], $current_alpha );
            imageline( $img, 0, $y, $width, $y, $color );
        }

        return $img;
    }

    /**
     * Render post title on image with professional styling.
     *
     * @param resource $img      GD image resource.
     * @param string   $title    Post title.
     * @param string   $position Text position (bottom-left, bottom-center, center).
     * @return resource Image with text.
     */
    private function render_text( $img, string $title, string $position ) {
        $width  = imagesx( $img );
        $height = imagesy( $img );

        // Use built-in GD font (font 5 is largest built-in).
        // For production, embed a TTF font and use imagettftext().
        $font_size = 5;
        $font_path = AITF_PLUGIN_DIR . 'assets/fonts/Roboto-Bold.ttf';

        // Add padding to prevent clipping.
        $padding = 40;
        $max_width = $width - ( $padding * 2 );

        $wrapped_lines = $this->wrap_text( $title, $max_width, $font_path );

        // Calculate text block dimensions.
        $line_height = 60; // Increased line height for better readability
        $block_height = count( $wrapped_lines ) * $line_height;

        // Force center alignment for better mobile/thumbnail view if requested.
        // User requested: "place the text middle of the text horizontally"
        // So we will center the text block horizontally regardless of position setting, 
        // but respect vertical positioning.
        
        $x_center = $width / 2;
        
        // Determine Y position.
        switch ( $position ) {
            case 'center':
                $y_start = ( $height - $block_height ) / 2;
                break;
            case 'bottom-left': // Keeping name but enforcing horizontal centering per request
            case 'bottom-center':
            default:
                $y_start = $height - $block_height - 60; // 60px from bottom
                break;
        }

        // Render each line.
        $white = imagecolorallocate( $img, 255, 255, 255 );
        $black = imagecolorallocate( $img, 0, 0, 0 );
        $gold  = imagecolorallocate( $img, 255, 215, 0 ); // For accents if needed

        foreach ( $wrapped_lines as $i => $line ) {
            $line_y = $y_start + ( $i * $line_height );

            if ( file_exists( $font_path ) ) {
                // Use TrueType font.
                $bbox = imagettfbbox( 40, 0, $font_path, $line );
                $text_width = $bbox[2] - $bbox[0];

                // Always center text horizontally.
                $line_x = $x_center - ( $text_width / 2 );

                // Stronger Drop Shadow (black stroke look).
                imagettftext( $img, 40, 0, $line_x + 2, $line_y + 2, $black, $font_path, $line );
                imagettftext( $img, 40, 0, $line_x + 1, $line_y + 1, $black, $font_path, $line );

                // Main text.
                imagettftext( $img, 40, 0, $line_x, $line_y, $white, $font_path, $line );
            } else {
                // Fallback to built-in font.
                $text_width = imagefontwidth( $font_size ) * strlen( $line );
                $line_x = $x_center - ( $text_width / 2 );

                imagestring( $img, $font_size, $line_x, $line_y, $line, $white );
            }
        }

        return $img;
    }

    /**
     * Wrap text into multiple lines based on max width.
     *
     * @param string $text      Text to wrap.
     * @param int    $max_width Maximum pixel width.
     * @param string $font_path Path to TTF font.
     * @return array Array of text lines.
     */
    private function wrap_text( string $text, int $max_width, string $font_path ): array {
        $words = explode( ' ', $text );
        $lines = [];
        $current_line = '';

        foreach ( $words as $word ) {
            $test_line = empty( $current_line ) ? $word : $current_line . ' ' . $word;

            if ( file_exists( $font_path ) ) {
                $bbox = imagettfbbox( 40, 0, $font_path, $test_line );
                $width = $bbox[2] - $bbox[0];
            } else {
                $width = strlen( $test_line ) * 10; // Rough estimate.
            }

            if ( $width > $max_width && ! empty( $current_line ) ) {
                $lines[] = $current_line;
                $current_line = $word;
            } else {
                $current_line = $test_line;
            }
        }

        if ( ! empty( $current_line ) ) {
            $lines[] = $current_line;
        }

        return $lines;
    }

    /**
     * Upload processed image to WordPress media library.
     *
     * @param string $file_path Path to image file.
     * @param int    $post_id   Post ID to attach to.
     * @param string $title     Image title.
     * @return int|\WP_Error Attachment ID or error.
     */
    private function upload_to_media_library( string $file_path, int $post_id, string $title ) {
        $filename = basename( sanitize_file_name( $title ) ) . '.jpg';

        $upload = wp_upload_bits( $filename, null, file_get_contents( $file_path ) );

        if ( ! empty( $upload['error'] ) ) {
            return new \WP_Error( 'upload_failed', $upload['error'] );
        }

        $attachment = [
            'post_mime_type' => 'image/jpeg',
            'post_title'     => sanitize_text_field( $title ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attachment_id = wp_insert_attachment( $attachment, $upload['file'], $post_id );

        if ( is_wp_error( $attachment_id ) ) {
            return $attachment_id;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
        wp_update_attachment_metadata( $attachment_id, $attach_data );

        return $attachment_id;
    }

    /**
     * Download an image from URL and set it as the post's featured image (no overlay).
     * Used when admin selects a stock image from the Posts list.
     *
     * @param int    $post_id   Post ID.
     * @param string $image_url Full image URL (e.g. from Pixabay/Pexels).
     * @param string $title     Optional title for the attachment (default: post title).
     * @return int|\WP_Error Attachment ID or error.
     */
    public function attach_image_url_to_post( int $post_id, string $image_url, string $title = '' ) {
        $image_url = esc_url_raw( $image_url );
        if ( empty( $image_url ) ) {
            return new \WP_Error( 'invalid_url', 'Invalid image URL.' );
        }

        $temp_download = download_url( $image_url );
        if ( is_wp_error( $temp_download ) ) {
            return $temp_download;
        }

        $image_type = @exif_imagetype( $temp_download );
        switch ( $image_type ) {
            case IMAGETYPE_JPEG:
                $img = @imagecreatefromjpeg( $temp_download );
                break;
            case IMAGETYPE_PNG:
                $img = @imagecreatefrompng( $temp_download );
                break;
            case IMAGETYPE_WEBP:
                $img = @imagecreatefromwebp( $temp_download );
                break;
            default:
                @unlink( $temp_download );
                return new \WP_Error( 'unsupported_type', 'Unsupported image format.' );
        }

        if ( ! $img ) {
            @unlink( $temp_download );
            return new \WP_Error( 'image_load_failed', 'Could not load image.' );
        }

        $img = $this->resize_image( $img, 1200, 675 );
        $temp_output = wp_tempnam( 'aitf-stock-' );
        imagejpeg( $img, $temp_output, 90 );
        imagedestroy( $img );
        @unlink( $temp_download );

        if ( empty( $title ) ) {
            $title = get_the_title( $post_id ) ?: 'Featured image';
        }

        $attachment_id = $this->upload_to_media_library( $temp_output, $post_id, $title );
        @unlink( $temp_output );

        if ( is_wp_error( $attachment_id ) ) {
            return $attachment_id;
        }

        set_post_thumbnail( $post_id, $attachment_id );
        return $attachment_id;
    }

    /**
     * Search stock photos by keyword (Pixabay + Pexels) — for Stock Footages admin page.
     * Returns multiple results for preview without attaching to a post.
     *
     * @param string $keyword Search term.
     * @param int    $limit   Max results to return (default 20).
     * @return array List of items: [ 'url' => string, 'thumbnail' => string, 'source' => 'pixabay'|'pexels', 'id' => string ].
     */
    public function search_stock_photos( string $keyword, int $limit = 20 ): array {
        $settings = get_option( 'aitf_settings', [] );
        $keyword  = trim( $keyword );
        if ( empty( $keyword ) ) {
            return [];
        }

        $results = [];
        $half    = (int) ceil( $limit / 2 );

        if ( ! empty( $settings['pixabay_api_key'] ) ) {
            $pixabay = $this->search_pixabay_results( $keyword, $settings['pixabay_api_key'], $half );
            foreach ( $pixabay as $item ) {
                $results[] = $item;
            }
        }

        if ( count( $results ) < $limit && ! empty( $settings['pexels_api_key'] ) ) {
            $pexels = $this->search_pexels_results( $keyword, $settings['pexels_api_key'], $limit - count( $results ) );
            foreach ( $pexels as $item ) {
                $results[] = $item;
            }
        }

        return array_slice( $results, 0, $limit );
    }

    /**
     * Search Pixabay and return multiple result items.
     *
     * @param string $keyword Search keyword.
     * @param string $api_key API key.
     * @param int    $per_page Number of results.
     * @return array Items with url, thumbnail, source, id.
     */
    private function search_pixabay_results( string $keyword, string $api_key, int $per_page = 10 ): array {
        $url = add_query_arg( [
            'key'        => $api_key,
            'q'          => $keyword,
            'image_type' => 'photo',
            'per_page'   => min( 20, max( 1, $per_page ) ),
            'safesearch' => 'true',
        ], 'https://pixabay.com/api/' );

        $response = wp_remote_get( $url, [ 'timeout' => 15 ] );
        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return [];
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $data['hits'] ) || ! is_array( $data['hits'] ) ) {
            return [];
        }

        $out = [];
        foreach ( $data['hits'] as $hit ) {
            $out[] = [
                'id'        => 'pixabay-' . ( $hit['id'] ?? uniqid() ),
                'url'       => $hit['largeImageURL'] ?? $hit['webformatURL'] ?? '',
                'thumbnail' => $hit['previewURL'] ?? $hit['webformatURL'] ?? '',
                'source'    => 'pixabay',
            ];
        }
        return $out;
    }

    /**
     * Search Pexels and return multiple result items.
     *
     * @param string $keyword Search keyword.
     * @param string $api_key API key.
     * @param int    $per_page Number of results.
     * @return array Items with url, thumbnail, source, id.
     */
    private function search_pexels_results( string $keyword, string $api_key, int $per_page = 10 ): array {
        $url = add_query_arg( [
            'query'    => $keyword,
            'per_page' => min( 15, max( 1, $per_page ) ),
        ], 'https://api.pexels.com/v1/search' );

        $response = wp_remote_get( $url, [
            'timeout' => 15,
            'headers' => [ 'Authorization' => $api_key ],
        ] );
        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return [];
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $data['photos'] ) || ! is_array( $data['photos'] ) ) {
            return [];
        }

        $out = [];
        foreach ( $data['photos'] as $photo ) {
            $src = $photo['src'] ?? [];
            $out[] = [
                'id'        => 'pexels-' . ( $photo['id'] ?? uniqid() ),
                'url'       => $src['large'] ?? $src['original'] ?? '',
                'thumbnail' => $src['medium'] ?? $src['small'] ?? $src['large'] ?? '',
                'source'    => 'pexels',
            ];
        }
        return $out;
    }
}
