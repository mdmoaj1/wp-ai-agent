<?php
/**
 * Settings page template.
 *
 * @var array  $settings       Plugin settings.
 * @var array  $schedule_info  Cron schedule info.
 * @var string $run_now_url    URL for "Run Now" action.
 * @var string $sitemap_human  Next sitemap ping (human-readable).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap aitf-wrap">
    <h1 class="aitf-page-title">
        <span class="dashicons dashicons-welcome-write-blog"></span>
        <?php esc_html_e( 'AI Content Generator — Settings', 'ai-for-techforus' ); ?>
    </h1>

    <?php if ( isset( $_GET['aitf_saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Settings saved successfully!', 'ai-for-techforus' ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( isset( $_GET['aitf_ran'] ) ) : ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <?php printf(
                    esc_html__( 'Content generation completed — Fetched: %d, Generated: %d, Skipped: %d, Errors: %d', 'ai-for-techforus' ),
                    absint( $_GET['fetched'] ?? 0 ),
                    absint( $_GET['generated'] ?? 0 ),
                    absint( $_GET['skipped'] ?? 0 ),
                    absint( $_GET['errors'] ?? 0 )
                ); ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- Status Card -->
    <div class="aitf-card aitf-status-card">
        <h2><?php esc_html_e( 'System Status', 'ai-for-techforus' ); ?></h2>
        <div class="aitf-status-grid">
            <div class="aitf-status-item">
                <span class="aitf-status-label"><?php esc_html_e( 'Cron Status', 'ai-for-techforus' ); ?></span>
                <span class="aitf-badge <?php echo $schedule_info['scheduled'] ? 'aitf-badge-success' : 'aitf-badge-warning'; ?>">
                    <?php echo $schedule_info['scheduled'] ? esc_html__( 'Active', 'ai-for-techforus' ) : esc_html__( 'Inactive', 'ai-for-techforus' ); ?>
                </span>
            </div>
            <div class="aitf-status-item">
                <span class="aitf-status-label"><?php esc_html_e( 'Next Run', 'ai-for-techforus' ); ?></span>
                <span class="aitf-status-value"><?php echo esc_html( $schedule_info['next_run_human'] ); ?></span>
            </div>
            <div class="aitf-status-item">
                <span class="aitf-status-label"><?php esc_html_e( 'Next Sitemap Ping', 'ai-for-techforus' ); ?></span>
                <span class="aitf-status-value"><?php echo esc_html( $sitemap_human ); ?></span>
            </div>
            <div class="aitf-status-item">
                <span class="aitf-status-label"><?php esc_html_e( 'API Provider', 'ai-for-techforus' ); ?></span>
                <span class="aitf-status-value"><?php echo esc_html( strtoupper( $settings['api_provider'] ?? 'N/A' ) ); ?></span>
            </div>
            <div class="aitf-status-item">
                <span class="aitf-status-label"><?php esc_html_e( 'API Key', 'ai-for-techforus' ); ?></span>
                <span class="aitf-badge <?php echo ! empty( $settings['api_key'] ) ? 'aitf-badge-success' : 'aitf-badge-danger'; ?>">
                    <?php echo ! empty( $settings['api_key'] ) ? esc_html__( 'Configured', 'ai-for-techforus' ) : esc_html__( 'Not Set', 'ai-for-techforus' ); ?>
                </span>
            </div>
        </div>
        <div class="aitf-status-actions">
            <a href="<?php echo esc_url( $run_now_url ); ?>" class="button button-primary aitf-btn-run" id="aitf-run-now">
                <span class="dashicons dashicons-controls-play"></span>
                <?php esc_html_e( 'Run Now', 'ai-for-techforus' ); ?>
            </a>
        </div>
    </div>

    <!-- Settings Form -->
    <form method="post" class="aitf-card">
        <?php wp_nonce_field( 'aitf_save_settings', 'aitf_settings_nonce' ); ?>

        <h2><?php esc_html_e( 'API Configuration', 'ai-for-techforus' ); ?></h2>

        <table class="form-table aitf-form-table">
            <tr>
                <th scope="row">
                    <label for="api_provider"><?php esc_html_e( 'API Provider', 'ai-for-techforus' ); ?></label>
                </th>
                <td>
                    <select name="api_provider" id="api_provider" class="regular-text">
                        <option value="groq" <?php selected( $settings['api_provider'] ?? '', 'groq' ); ?>>Groq</option>
                        <option value="openai" <?php selected( $settings['api_provider'] ?? '', 'openai' ); ?>>OpenAI</option>
                    </select>
                    <p class="description"><?php esc_html_e( 'Select your AI provider.', 'ai-for-techforus' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="api_key"><?php esc_html_e( 'API Key', 'ai-for-techforus' ); ?></label>
                </th>
                <td>
                    <input type="password" name="api_key" id="api_key" class="regular-text"
                           value="<?php echo esc_attr( $settings['api_key'] ?? '' ); ?>"
                           autocomplete="off">
                    <button type="button" class="button aitf-toggle-password" data-target="api_key">
                        <span class="dashicons dashicons-visibility"></span>
                    </button>
                    <p class="description"><?php esc_html_e( 'Your API key. Stored securely in the database.', 'ai-for-techforus' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="model"><?php esc_html_e( 'Model', 'ai-for-techforus' ); ?></label>
                </th>
                <td>
                    <select name="model" id="model" class="regular-text">
                        <option value=""><?php esc_html_e( '— Use Default —', 'ai-for-techforus' ); ?></option>

                        <optgroup label="Groq Models" class="aitf-model-group" data-provider="groq">
                            <option value="openai/gpt-oss-120b" <?php selected( $settings['model'] ?? '', 'openai/gpt-oss-120b' ); ?>>GPT OSS 120B</option>
                        </optgroup>

                        <optgroup label="OpenAI Models" class="aitf-model-group" data-provider="openai">
                            <option value="gpt-4o-mini" <?php selected( $settings['model'] ?? '', 'gpt-4o-mini' ); ?>>GPT-4o Mini</option>
                            <option value="gpt-4o" <?php selected( $settings['model'] ?? '', 'gpt-4o' ); ?>>GPT-4o</option>
                            <option value="gpt-4-turbo" <?php selected( $settings['model'] ?? '', 'gpt-4-turbo' ); ?>>GPT-4 Turbo</option>
                            <option value="gpt-3.5-turbo" <?php selected( $settings['model'] ?? '', 'gpt-3.5-turbo' ); ?>>GPT-3.5 Turbo</option>
                        </optgroup>
                    </select>
                    <p class="description"><?php esc_html_e( 'Choose a specific model or use the provider default.', 'ai-for-techforus' ); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'Content Settings', 'ai-for-techforus' ); ?></h2>

        <table class="form-table aitf-form-table">
            <tr>
                <th scope="row">
                    <label for="language"><?php esc_html_e( 'Language', 'ai-for-techforus' ); ?></label>
                </th>
                <td>
                    <select name="language" id="language" class="regular-text">
                        <option value="en" <?php selected( $settings['language'] ?? '', 'en' ); ?>>English</option>
                        <option value="bn" <?php selected( $settings['language'] ?? '', 'bn' ); ?>>বাংলা (Bengali)</option>
                        <option value="es" <?php selected( $settings['language'] ?? '', 'es' ); ?>>Español (Spanish)</option>
                        <option value="fr" <?php selected( $settings['language'] ?? '', 'fr' ); ?>>Français (French)</option>
                        <option value="de" <?php selected( $settings['language'] ?? '', 'de' ); ?>>Deutsch (German)</option>
                        <option value="hi" <?php selected( $settings['language'] ?? '', 'hi' ); ?>>हिन्दी (Hindi)</option>
                        <option value="ar" <?php selected( $settings['language'] ?? '', 'ar' ); ?>>العربية (Arabic)</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="article_length"><?php esc_html_e( 'Article Length (words)', 'ai-for-techforus' ); ?></label>
                </th>
                <td>
                    <input type="number" name="article_length" id="article_length" class="small-text"
                           value="<?php echo absint( $settings['article_length'] ?? 1500 ); ?>"
                           min="500" max="5000" step="100">
                    <p class="description"><?php esc_html_e( 'Approximate word count for generated articles (500 – 5000).', 'ai-for-techforus' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="publish_mode"><?php esc_html_e( 'Publishing Mode', 'ai-for-techforus' ); ?></label>
                </th>
                <td>
                    <select name="publish_mode" id="publish_mode" class="regular-text">
                        <option value="draft" <?php selected( $settings['publish_mode'] ?? '', 'draft' ); ?>>
                            <?php esc_html_e( 'Save as Draft', 'ai-for-techforus' ); ?>
                        </option>
                        <option value="publish" <?php selected( $settings['publish_mode'] ?? '', 'publish' ); ?>>
                            <?php esc_html_e( 'Publish Immediately', 'ai-for-techforus' ); ?>
                        </option>
                        <option value="scheduled" <?php selected( $settings['publish_mode'] ?? '', 'scheduled' ); ?>>
                            <?php esc_html_e( 'Schedule (+1 hour)', 'ai-for-techforus' ); ?>
                        </option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="category_mode"><?php esc_html_e( 'Category Mode', 'ai-for-techforus' ); ?></label>
                </th>
                <td>
                    <select name="category_mode" id="category_mode" class="regular-text">
                        <option value="fixed" <?php selected( $settings['category_mode'] ?? '', 'fixed' ); ?>>
                            ✅ <?php esc_html_e( 'Use fixed categories (recommended)', 'ai-for-techforus' ); ?>
                        </option>
                        <option value="auto" <?php selected( $settings['category_mode'] ?? '', 'auto' ); ?>>
                            ➕ <?php esc_html_e( 'Allow creating new categories', 'ai-for-techforus' ); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php esc_html_e( 'Fixed mode keeps your site to 8-10 categories. Manage them in the Categories page.', 'ai-for-techforus' ); ?>
                    </p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'Featured Image Settings', 'ai-for-techforus' ); ?></h2>

        <table class="form-table aitf-form-table">
            <tr>
                <th scope="row">
                    <label for="enable_featured_image"><?php esc_html_e( 'Auto-Generate Featured Images', 'ai-for-techforus' ); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="enable_featured_image" id="enable_featured_image" value="1"
                               <?php checked( $settings['enable_featured_image'] ?? '0', '1' ); ?>>
                        <?php esc_html_e( 'Automatically create featured images for every post', 'ai-for-techforus' ); ?>
                    </label>
                    <p class="description"><?php esc_html_e( 'Uses Pixabay or Pexels to find relevant images with gradient overlay and post title.', 'ai-for-techforus' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="pixabay_api_key"><?php esc_html_e( 'Pixabay API Key', 'ai-for-techforus' ); ?></label>
                </th>
                <td>
                    <input type="password" name="pixabay_api_key" id="pixabay_api_key" class="regular-text"
                           value="<?php echo esc_attr( $settings['pixabay_api_key'] ?? '' ); ?>"
                           autocomplete="off">
                    <button type="button" class="button aitf-toggle-password" data-target="pixabay_api_key">
                        <span class="dashicons dashicons-visibility"></span>
                    </button>
                    <p class="description">
                        <?php esc_html_e( 'Get your free API key from', 'ai-for-techforus' ); ?>
                        <a href="https://pixabay.com/api/docs/" target="_blank">Pixabay API</a>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="pexels_api_key"><?php esc_html_e( 'Pexels API Key', 'ai-for-techforus' ); ?></label>
                </th>
                <td>
                    <input type="password" name="pexels_api_key" id="pexels_api_key" class="regular-text"
                           value="<?php echo esc_attr( $settings['pexels_api_key'] ?? '' ); ?>"
                           autocomplete="off">
                    <button type="button" class="button aitf-toggle-password" data-target="pexels_api_key">
                        <span class="dashicons dashicons-visibility"></span>
                    </button>
                    <p class="description">
                        <?php esc_html_e( 'Get your free API key from', 'ai-for-techforus' ); ?>
                        <a href="https://www.pexels.com/api/" target="_blank">Pexels API</a>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="image_text_position"><?php esc_html_e( 'Text Position', 'ai-for-techforus' ); ?></label>
                </th>
                <td>
                    <select name="image_text_position" id="image_text_position" class="regular-text">
                        <option value="bottom-left" <?php selected( $settings['image_text_position'] ?? '', 'bottom-left' ); ?>>
                            <?php esc_html_e( 'Bottom Left', 'ai-for-techforus' ); ?>
                        </option>
                        <option value="bottom-center" <?php selected( $settings['image_text_position'] ?? '', 'bottom-center' ); ?>>
                            <?php esc_html_e( 'Bottom Center', 'ai-for-techforus' ); ?>
                        </option>
                        <option value="center" <?php selected( $settings['image_text_position'] ?? '', 'center' ); ?>>
                            <?php esc_html_e( 'Center', 'ai-for-techforus' ); ?>
                        </option>
                    </select>
                    <p class="description"><?php esc_html_e( 'Where to position the post title on the image.', 'ai-for-techforus' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="image_gradient_opacity"><?php esc_html_e( 'Gradient Opacity', 'ai-for-techforus' ); ?></label>
                </th>
                <td>
                    <input type="range" name="image_gradient_opacity" id="image_gradient_opacity"
                           value="<?php echo absint( $settings['image_gradient_opacity'] ?? 70 ); ?>"
                           min="0" max="100" step="5">
                    <span id="opacity-value"><?php echo absint( $settings['image_gradient_opacity'] ?? 70 ); ?>%</span>
                    <p class="description"><?php esc_html_e( 'Darkness of the gradient overlay (0 = transparent, 100 = opaque).', 'ai-for-techforus' ); ?></p>
                </td>
            </tr>
        </table>

        <?php submit_button( __( 'Save Settings', 'ai-for-techforus' ) ); ?>
    </form>
</div>
