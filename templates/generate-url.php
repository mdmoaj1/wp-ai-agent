<?php
/**
 * Generate from URL template.
 *
 * @var string $error   Error code.
 * @var int    $success Post ID if successful.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap aitf-wrap">
    <h1 class="aitf-page-title">
        <span class="dashicons dashicons-admin-links"></span>
        <?php esc_html_e( 'Generate from URL', 'ai-for-techforus' ); ?>
    </h1>

    <?php if ( ! empty( $error ) ) : ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php
                $messages = [
                    'empty_url'          => __( 'Please enter a valid post URL.', 'ai-for-techforus' ),
                    'invalid_url'        => __( 'Could not extract post ID from the URL. Make sure it\'s a WordPress post permalink.', 'ai-for-techforus' ),
                    'fetch_failed'       => __( 'Failed to fetch the post from the URL. Check if REST API is enabled.', 'ai-for-techforus' ),
                    'ai_provider_error'  => __( 'AI Provider error. Check your API settings.', 'ai-for-techforus' ),
                    'generation_failed'  => __( 'Failed to generate article content.', 'ai-for-techforus' ),
                    'publish_failed'     => __( 'Failed to publish the article.', 'ai-for-techforus' ),
                ];
                echo esc_html( $messages[ $error ] ?? __( 'An unknown error occurred.', 'ai-for-techforus' ) );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $success ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                printf(
                    /* translators: %s: Edit post link */
                    esc_html__( 'Article generated successfully! %s', 'ai-for-techforus' ),
                    '<a href="' . esc_url( get_edit_post_link( $success ) ) . '">' . esc_html__( 'Edit Post', 'ai-for-techforus' ) . '</a>'
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="aitf-card">
        <h2><?php esc_html_e( 'Generate Article from Single URL', 'ai-for-techforus' ); ?></h2>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="aitf_generate_from_url">
            <?php wp_nonce_field( 'aitf_generate_from_url', 'aitf_generate_url_nonce' ); ?>

            <table class="form-table aitf-form-table">
                <tr>
                    <th scope="row">
                        <label for="post_url"><?php esc_html_e( 'Post URL', 'ai-for-techforus' ); ?></label>
                    </th>
                    <td>
                        <input type="url" name="post_url" id="post_url" class="large-text"
                               placeholder="https://example.com/2024/01/sample-post/"
                               required>
                        <p class="description">
                            <?php esc_html_e( 'Enter the full URL of a WordPress post. The plugin will fetch its content and generate a superior version.', 'ai-for-techforus' ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button( __( 'Generate & Publish', 'ai-for-techforus' ), 'primary', 'submit', false ); ?>
        </form>
    </div>

    <div class="aitf-card aitf-info-box">
        <p>
            <span class="dashicons dashicons-info"></span>
            <strong><?php esc_html_e( 'How it works:', 'ai-for-techforus' ); ?></strong>
            <?php esc_html_e( 'Paste a competitor post URL, click Generate, and the plugin will create a better version with a professional featured image automatically.', 'ai-for-techforus' ); ?>
        </p>
    </div>
</div>
