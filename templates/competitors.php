<?php
/**
 * Competitors page template.
 *
 * @var array  $competitors Array of competitor objects.
 * @var int    $count       Total competitors count.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap aitf-wrap">
    <h1 class="aitf-page-title">
        <span class="dashicons dashicons-networking"></span>
        <?php esc_html_e( 'Competitor Websites', 'ai-for-techforus' ); ?>
        <span class="aitf-count-badge"><?php echo esc_html( $count ); ?></span>
    </h1>

    <?php if ( isset( $_GET['aitf_added'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Competitor added successfully!', 'ai-for-techforus' ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( isset( $_GET['aitf_deleted'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Competitor removed.', 'ai-for-techforus' ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( isset( $_GET['aitf_error'] ) ) : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html( urldecode( $_GET['aitf_error'] ) ); ?></p>
        </div>
    <?php endif; ?>

    <!-- Add Competitor Form -->
    <div class="aitf-card">
        <h2><?php esc_html_e( 'Add Competitor Website', 'ai-for-techforus' ); ?></h2>
        <p class="description">
            <?php esc_html_e( 'Add a WordPress website to monitor. The site must have the WP REST API enabled (most WordPress sites do).', 'ai-for-techforus' ); ?>
        </p>

        <form method="post" class="aitf-inline-form">
            <?php wp_nonce_field( 'aitf_add_competitor', 'aitf_add_competitor_nonce' ); ?>
            <div class="aitf-input-group">
                <input type="url" name="competitor_url" id="competitor_url"
                       placeholder="https://example.com"
                       class="regular-text" required>
                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php esc_html_e( 'Add & Validate', 'ai-for-techforus' ); ?>
                </button>
            </div>
            <p class="description" style="margin-top: 8px;">
                <?php esc_html_e( 'The system will validate the REST API at: https://domain.com/wp-json/wp/v2/posts', 'ai-for-techforus' ); ?>
            </p>
        </form>
    </div>

    <!-- Competitors List -->
    <div class="aitf-card">
        <h2><?php esc_html_e( 'Monitored Websites', 'ai-for-techforus' ); ?></h2>

        <?php if ( empty( $competitors ) ) : ?>
            <div class="aitf-empty-state">
                <span class="dashicons dashicons-admin-site-alt3"></span>
                <p><?php esc_html_e( 'No competitors added yet. Add your first competitor website above.', 'ai-for-techforus' ); ?></p>
            </div>
        <?php else : ?>
            <table class="widefat striped aitf-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Website', 'ai-for-techforus' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'ai-for-techforus' ); ?></th>
                        <th><?php esc_html_e( 'Last Fetched', 'ai-for-techforus' ); ?></th>
                        <th><?php esc_html_e( 'Last Post ID', 'ai-for-techforus' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'ai-for-techforus' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $competitors as $comp ) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $comp->site_name ); ?></strong>
                                <br>
                                <a href="<?php echo esc_url( $comp->site_url ); ?>" target="_blank" rel="noopener" class="aitf-link-muted">
                                    <?php echo esc_html( $comp->site_url ); ?>
                                    <span class="dashicons dashicons-external"></span>
                                </a>
                            </td>
                            <td>
                                <span class="aitf-badge <?php echo $comp->status === 'active' ? 'aitf-badge-success' : 'aitf-badge-warning'; ?>">
                                    <?php echo esc_html( ucfirst( $comp->status ) ); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                if ( $comp->last_fetched_at ) {
                                    echo esc_html( human_time_diff( strtotime( $comp->last_fetched_at ) ) . ' ago' );
                                } else {
                                    esc_html_e( 'Never', 'ai-for-techforus' );
                                }
                                ?>
                            </td>
                            <td>
                                <?php echo esc_html( $comp->last_fetched_post_id ?: '—' ); ?>
                            </td>
                            <td class="aitf-actions">
                                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [
                                    'page'          => 'aitf-competitors',
                                    'aitf_action'   => 'toggle_competitor',
                                    'competitor_id'  => $comp->id,
                                ], admin_url( 'admin.php' ) ), 'aitf_toggle_competitor_' . $comp->id ) ); ?>"
                                   class="button button-small">
                                    <?php echo $comp->status === 'active'
                                        ? esc_html__( 'Pause', 'ai-for-techforus' )
                                        : esc_html__( 'Activate', 'ai-for-techforus' ); ?>
                                </a>
                                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [
                                    'page'          => 'aitf-competitors',
                                    'aitf_action'   => 'delete_competitor',
                                    'competitor_id'  => $comp->id,
                                ], admin_url( 'admin.php' ) ), 'aitf_delete_competitor_' . $comp->id ) ); ?>"
                                   class="button button-small aitf-btn-danger"
                                   onclick="return confirm('<?php esc_attr_e( 'Delete this competitor?', 'ai-for-techforus' ); ?>');">
                                    <?php esc_html_e( 'Delete', 'ai-for-techforus' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
