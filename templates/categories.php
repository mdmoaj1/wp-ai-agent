<?php
/**
 * Categories page template.
 *
 * @var array $categories   WP_Term objects for fixed categories.
 * @var array $fixed_ids    Array of fixed term IDs.
 * @var bool  $max_reached  Whether max categories reached.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap aitf-wrap">
    <h1 class="aitf-page-title">
        <span class="dashicons dashicons-category"></span>
        <?php esc_html_e( 'Category Manager', 'ai-for-techforus' ); ?>
        <span class="aitf-count-badge"><?php echo esc_html( count( $categories ) ); ?> / 10</span>
    </h1>

    <?php if ( isset( $_GET['aitf_added'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Category added successfully!', 'ai-for-techforus' ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( isset( $_GET['aitf_removed'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Category removed from the fixed list.', 'ai-for-techforus' ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( isset( $_GET['aitf_init'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Default categories initialized!', 'ai-for-techforus' ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( isset( $_GET['aitf_error'] ) ) : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html( urldecode( $_GET['aitf_error'] ) ); ?></p>
        </div>
    <?php endif; ?>

    <!-- Info Box -->
    <div class="aitf-card aitf-info-box">
        <p>
            <span class="dashicons dashicons-info-outline"></span>
            <?php esc_html_e(
                'The AI assigns every generated post to one of these fixed categories (max 2 per post). This prevents category spam and keeps your site organized. You can have up to 10 categories.',
                'ai-for-techforus'
            ); ?>
        </p>
    </div>

    <!-- Auto-Initialize (only if empty) -->
    <?php if ( empty( $categories ) ) : ?>
        <div class="aitf-card">
            <h2><?php esc_html_e( 'Quick Start', 'ai-for-techforus' ); ?></h2>
            <p><?php esc_html_e( 'No fixed categories yet. Initialize with sensible defaults or add them manually below.', 'ai-for-techforus' ); ?></p>
            <form method="post">
                <?php wp_nonce_field( 'aitf_init_categories', 'aitf_init_categories_nonce' ); ?>
                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e( 'Initialize Default Categories', 'ai-for-techforus' ); ?>
                </button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Add Category -->
    <?php if ( ! $max_reached ) : ?>
        <div class="aitf-card">
            <h2><?php esc_html_e( 'Add Category', 'ai-for-techforus' ); ?></h2>
            <form method="post" class="aitf-inline-form">
                <?php wp_nonce_field( 'aitf_add_category', 'aitf_add_category_nonce' ); ?>
                <div class="aitf-input-group">
                    <input type="text" name="category_name" placeholder="<?php esc_attr_e( 'Category name', 'ai-for-techforus' ); ?>"
                           class="regular-text" required>
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php esc_html_e( 'Add Category', 'ai-for-techforus' ); ?>
                    </button>
                </div>
            </form>
        </div>
    <?php else : ?>
        <div class="notice notice-warning">
            <p><?php esc_html_e( 'Maximum of 10 categories reached. Remove a category before adding a new one.', 'ai-for-techforus' ); ?></p>
        </div>
    <?php endif; ?>

    <!-- Categories List -->
    <div class="aitf-card">
        <h2><?php esc_html_e( 'Fixed Categories', 'ai-for-techforus' ); ?></h2>

        <?php if ( empty( $categories ) ) : ?>
            <div class="aitf-empty-state">
                <span class="dashicons dashicons-category"></span>
                <p><?php esc_html_e( 'No fixed categories configured.', 'ai-for-techforus' ); ?></p>
            </div>
        <?php else : ?>
            <div class="aitf-categories-grid">
                <?php foreach ( $categories as $term ) : ?>
                    <div class="aitf-category-card">
                        <div class="aitf-category-info">
                            <span class="dashicons dashicons-tag"></span>
                            <strong><?php echo esc_html( $term->name ); ?></strong>
                            <span class="aitf-category-count">
                                <?php printf(
                                    esc_html( _n( '%d post', '%d posts', $term->count, 'ai-for-techforus' ) ),
                                    $term->count
                                ); ?>
                            </span>
                        </div>
                        <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [
                            'page'        => 'aitf-categories',
                            'aitf_action' => 'remove_category',
                            'term_id'     => $term->term_id,
                        ], admin_url( 'admin.php' ) ), 'aitf_remove_category_' . $term->term_id ) ); ?>"
                           class="button button-small aitf-btn-danger"
                           onclick="return confirm('<?php esc_attr_e( 'Remove this category from the fixed list?', 'ai-for-techforus' ); ?>');">
                            <span class="dashicons dashicons-no-alt"></span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
