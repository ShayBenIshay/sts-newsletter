<?php
/**
 * Plugin Name: STS Newsletter
 * Plugin URI: https://shaytechsolutions.com
 * Description: Newsletter subscription with WhatsApp and email support.
 * Version: 1.0.0
 * Author: Shay Tech Solutions
 * Author URI: https://shaytechsolutions.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sts-newsletter
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

define( 'STS_NL_VERSION', '1.0.0' );
define( 'STS_NL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STS_NL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once STS_NL_PLUGIN_DIR . 'includes/class-sts-newsletter.php';
require_once STS_NL_PLUGIN_DIR . 'includes/class-sts-nl-encryption.php';
require_once STS_NL_PLUGIN_DIR . 'includes/class-sts-nl-settings.php';
require_once STS_NL_PLUGIN_DIR . 'includes/class-sts-nl-secret.php';
require_once STS_NL_PLUGIN_DIR . 'includes/class-sts-nl-phone.php';
require_once STS_NL_PLUGIN_DIR . 'includes/class-sts-nl-whatsapp-client.php';
require_once STS_NL_PLUGIN_DIR . 'includes/class-sts-nl-connection.php';
require_once STS_NL_PLUGIN_DIR . 'includes/class-sts-nl-unsubscribe.php';
require_once STS_NL_PLUGIN_DIR . 'includes/class-sts-newsletter-admin.php';

STS_Newsletter::get_instance();
STS_Newsletter_Admin::get_instance();
STS_NL_Connection::register();

add_action( 'rest_api_init', array( 'STS_NL_Unsubscribe', 'register_routes' ) );
add_action( 'init',              array( 'STS_NL_Unsubscribe', 'register_short_url' ) );
add_filter( 'query_vars',        array( 'STS_NL_Unsubscribe', 'add_query_vars' ) );
add_action( 'template_redirect', array( 'STS_NL_Unsubscribe', 'handle_short_url' ) );

// Flush rewrite rules on activation so /unsub/TOKEN works immediately.
register_activation_hook( __FILE__, function () {
    STS_NL_Unsubscribe::register_short_url();
    flush_rewrite_rules();
} );
