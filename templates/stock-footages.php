<?php
/**
 * Stock Footages page template — search Pixabay/Pexels (AJAX, no reload).
 *
 * @var bool $has_keys Whether at least one API key is configured.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap aitf-wrap">
    <h1 class="aitf-page-title">
        <span class="dashicons dashicons-format-gallery"></span>
        <?php esc_html_e( 'Stock Footages', 'ai-for-techforus' ); ?>
    </h1>

    <?php if ( ! $has_keys ) : ?>
        <div class="notice notice-warning">
            <p>
                <?php esc_html_e( 'Add a Pixabay or Pexels API key in', 'ai-for-techforus' ); ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=aitf-settings' ) ); ?>"><?php esc_html_e( 'Settings', 'ai-for-techforus' ); ?></a>
                <?php esc_html_e( 'to search stock photos.', 'ai-for-techforus' ); ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="aitf-card">
        <h2><?php esc_html_e( 'Search stock photos', 'ai-for-techforus' ); ?></h2>
        <p class="description">
            <?php esc_html_e( 'Search Pixabay and Pexels. Results load without page reload.', 'ai-for-techforus' ); ?>
        </p>
        <div class="aitf-stock-search-row" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin:12px 0;">
            <input type="search"
                   id="aitf-stock-keyword"
                   class="regular-text"
                   placeholder="<?php esc_attr_e( 'e.g. technology, office, laptop', 'ai-for-techforus' ); ?>"
                   autocomplete="off"
                   style="max-width:320px;">
            <button type="button" id="aitf-stock-search-btn" class="button button-primary">
                <?php esc_html_e( 'Search', 'ai-for-techforus' ); ?>
            </button>
            <span id="aitf-stock-loading" class="spinner is-active" style="float:none; margin:0; display:none;"></span>
        </div>
        <div id="aitf-stock-message" class="aitf-stock-message" style="margin:8px 0; min-height:24px;"></div>
        <div id="aitf-stock-results" class="aitf-stock-results" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:16px; margin-top:16px;"></div>
    </div>
</div>
