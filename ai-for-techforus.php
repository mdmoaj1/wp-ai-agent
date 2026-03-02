<?php
/**
 * Plugin Name: AI Auto Content Generator
 * Plugin URI:  https://techforus.com/
 * Description: Autonomous SEO content engine — monitors competitors, generates superior articles via Groq/OpenAI, and publishes automatically with full logging.
 * Version:     2.5.2
 * Author:      TechForUs
 * Author URI:  https://techforus.com/
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-for-techforus
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*--------------------------------------------------------------
 * Constants
 *------------------------------------------------------------*/
define( 'AITF_VERSION', '1.0.0' );
define( 'AITF_PLUGIN_FILE', __FILE__ );
define( 'AITF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AITF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AITF_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/*--------------------------------------------------------------
 * Autoloader
 *------------------------------------------------------------*/
require_once AITF_PLUGIN_DIR . 'includes/class-autoloader.php';
\AITF\Autoloader::register();

/*--------------------------------------------------------------
 * Activation / Deactivation
 *------------------------------------------------------------*/
register_activation_hook( __FILE__, [ \AITF\Activator::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \AITF\Deactivator::class, 'deactivate' ] );

/*--------------------------------------------------------------
 * Boot the plugin
 *------------------------------------------------------------*/
add_action( 'plugins_loaded', function () {

    // Admin pages
    if ( is_admin() ) {
        new \AITF\Admin\Admin_Menu();
        new \AITF\Admin\Post_List_Stock_Image();
    }

    // Cron handler (always register so the callback works)
    new \AITF\Cron\Cron_Handler();

} );
