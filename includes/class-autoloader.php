<?php
/**
 * PSR-4 style autoloader for the AITF namespace.
 *
 * Maps:
 *   AITF\Foo_Bar          → includes/class-foo-bar.php
 *   AITF\Admin\Foo_Bar    → includes/admin/class-foo-bar.php
 *   AITF\AI\Foo_Bar       → includes/ai/class-foo-bar.php
 *   AITF\Core\Foo_Bar     → includes/core/class-foo-bar.php
 *   AITF\Cron\Foo_Bar     → includes/cron/class-foo-bar.php
 *   AITF\Models\Foo_Bar   → includes/models/class-foo-bar.php
 *
 * @package AITF
 */

namespace AITF;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Autoloader {

    /**
     * Register the autoloader.
     */
    public static function register(): void {
        spl_autoload_register( [ __CLASS__, 'autoload' ] );
    }

    /**
     * Autoload callback.
     *
     * @param string $class Fully-qualified class name.
     */
    public static function autoload( string $class ): void {

        $prefix = 'AITF\\';

        // Bail if not our namespace.
        if ( strpos( $class, $prefix ) !== 0 ) {
            return;
        }

        // Strip prefix: e.g. "Admin\Settings_Page"
        $relative = substr( $class, strlen( $prefix ) );

        // Convert namespace separators to directory separators.
        $relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );

        // Split into parts to build WordPress-style filename.
        $parts     = explode( DIRECTORY_SEPARATOR, $relative );
        $classname = array_pop( $parts );

        // Convert class name: "Settings_Page" → "class-settings-page"
        $filename = 'class-' . str_replace( '_', '-', strtolower( $classname ) ) . '.php';

        // Build path segments (lowercased subdirectories).
        $subdir = '';
        if ( ! empty( $parts ) ) {
            $subdir = strtolower( implode( DIRECTORY_SEPARATOR, $parts ) ) . DIRECTORY_SEPARATOR;
        }

        $filepath = AITF_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . $subdir . $filename;

        if ( file_exists( $filepath ) ) {
            require_once $filepath;
        }
    }
}
