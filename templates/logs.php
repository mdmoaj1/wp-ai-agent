<?php
/**
 * Logs page template.
 *
 * @var array  $logs         Array of log objects.
 * @var array  $filters      Active filters.
 * @var int    $total        Total matching logs.
 * @var int    $total_pages  Total pages.
 * @var int    $page         Current page.
 * @var int    $per_page     Items per page.
 * @var string $export_url   CSV export URL.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap aitf-wrap">
    <h1 class="aitf-page-title">
        <span class="dashicons dashicons-list-view"></span>
        <?php esc_html_e( 'Activity Logs', 'ai-for-techforus' ); ?>
        <span class="aitf-count-badge"><?php echo esc_html( $total ); ?></span>
    </h1>

    <!-- Filters -->
    <div class="aitf-card">
        <form method="get" class="aitf-filter-form">
            <input type="hidden" name="page" value="aitf-logs">

            <div class="aitf-filter-row">
                <div class="aitf-filter-field">
                    <label for="date_from"><?php esc_html_e( 'From', 'ai-for-techforus' ); ?></label>
                    <input type="date" name="date_from" id="date_from"
                           value="<?php echo esc_attr( $filters['date_from'] ); ?>">
                </div>

                <div class="aitf-filter-field">
                    <label for="date_to"><?php esc_html_e( 'To', 'ai-for-techforus' ); ?></label>
                    <input type="date" name="date_to" id="date_to"
                           value="<?php echo esc_attr( $filters['date_to'] ); ?>">
                </div>

                <div class="aitf-filter-field">
                    <label for="event_type"><?php esc_html_e( 'Event Type', 'ai-for-techforus' ); ?></label>
                    <select name="event_type" id="event_type">
                        <option value=""><?php esc_html_e( 'All', 'ai-for-techforus' ); ?></option>
                        <option value="cron_run" <?php selected( $filters['event_type'], 'cron_run' ); ?>>Cron Run</option>
                        <option value="fetch" <?php selected( $filters['event_type'], 'fetch' ); ?>>Fetch</option>
                        <option value="generate" <?php selected( $filters['event_type'], 'generate' ); ?>>Generate</option>
                        <option value="publish" <?php selected( $filters['event_type'], 'publish' ); ?>>Publish</option>
                        <option value="error" <?php selected( $filters['event_type'], 'error' ); ?>>Error</option>
                    </select>
                </div>

                <div class="aitf-filter-field">
                    <label for="filter_status"><?php esc_html_e( 'Status', 'ai-for-techforus' ); ?></label>
                    <select name="filter_status" id="filter_status">
                        <option value=""><?php esc_html_e( 'All', 'ai-for-techforus' ); ?></option>
                        <option value="success" <?php selected( $filters['status'], 'success' ); ?>>Success</option>
                        <option value="fail" <?php selected( $filters['status'], 'fail' ); ?>>Fail</option>
                    </select>
                </div>

                <div class="aitf-filter-field aitf-filter-actions">
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'ai-for-techforus' ); ?></button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aitf-logs' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'ai-for-techforus' ); ?></a>
                    <a href="<?php echo esc_url( $export_url ); ?>" class="button">
                        <span class="dashicons dashicons-download" style="margin-top:4px"></span>
                        <?php esc_html_e( 'Export CSV', 'ai-for-techforus' ); ?>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="aitf-card">
        <?php if ( empty( $logs ) ) : ?>
            <div class="aitf-empty-state">
                <span class="dashicons dashicons-media-text"></span>
                <p><?php esc_html_e( 'No log entries found.', 'ai-for-techforus' ); ?></p>
            </div>
        <?php else : ?>
            <table class="widefat striped aitf-table aitf-logs-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Time', 'ai-for-techforus' ); ?></th>
                        <th><?php esc_html_e( 'Event', 'ai-for-techforus' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'ai-for-techforus' ); ?></th>
                        <th><?php esc_html_e( 'Competitor', 'ai-for-techforus' ); ?></th>
                        <th><?php esc_html_e( 'Message', 'ai-for-techforus' ); ?></th>
                        <th><?php esc_html_e( 'Post', 'ai-for-techforus' ); ?></th>
                        <th><?php esc_html_e( 'Tokens', 'ai-for-techforus' ); ?></th>
                        <th><?php esc_html_e( 'Provider', 'ai-for-techforus' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $logs as $log ) : ?>
                        <tr>
                            <td class="aitf-nowrap">
                                <?php echo esc_html( wp_date( 'M j, H:i', strtotime( $log->timestamp ) ) ); ?>
                            </td>
                            <td>
                                <span class="aitf-event-badge aitf-event-<?php echo esc_attr( $log->event_type ); ?>">
                                    <?php echo esc_html( str_replace( '_', ' ', ucfirst( $log->event_type ) ) ); ?>
                                </span>
                            </td>
                            <td>
                                <span class="aitf-badge <?php echo $log->status === 'success' ? 'aitf-badge-success' : 'aitf-badge-danger'; ?>">
                                    <?php echo esc_html( ucfirst( $log->status ) ); ?>
                                </span>
                            </td>
                            <td class="aitf-truncate" title="<?php echo esc_attr( $log->competitor_url ); ?>">
                                <?php
                                if ( $log->competitor_url ) {
                                    echo esc_html( wp_parse_url( $log->competitor_url, PHP_URL_HOST ) ?: $log->competitor_url );
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td class="aitf-truncate" title="<?php echo esc_attr( $log->message ); ?>">
                                <?php echo esc_html( wp_trim_words( $log->message, 12 ) ); ?>
                            </td>
                            <td>
                                <?php if ( $log->post_id ) : ?>
                                    <a href="<?php echo esc_url( get_edit_post_link( $log->post_id ) ); ?>" target="_blank">
                                        #<?php echo esc_html( $log->post_id ); ?>
                                    </a>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?php echo $log->token_usage ? esc_html( number_format( $log->token_usage ) ) : '—'; ?></td>
                            <td><?php echo $log->provider ? esc_html( strtoupper( $log->provider ) ) : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ( $total_pages > 1 ) : ?>
                <div class="aitf-pagination">
                    <?php
                    $base_url = add_query_arg( array_merge(
                        [ 'page' => 'aitf-logs' ],
                        array_filter( $filters )
                    ), admin_url( 'admin.php' ) );

                    for ( $i = 1; $i <= $total_pages; $i++ ) :
                        $url = add_query_arg( 'paged', $i, $base_url );
                        ?>
                        <a href="<?php echo esc_url( $url ); ?>"
                           class="button <?php echo $i === $page ? 'button-primary' : ''; ?>">
                            <?php echo esc_html( $i ); ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
