<?php
/**
 * Plugin Name:       WPYog Fleet – Booking Manager for Taxi, Cab & Travel Businesses
 * Plugin URI:        https://demo.poppinsdigital.com/wpyog-fleet/
 * Description:       Manage bookings, vehicles, drivers, payments, advances, balances, and trip expenses from inside your WordPress dashboard.
 * Version:           3.5.6
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            Poppins Digital
 * Author URI:        https://poppinsdigital.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpyog-fleet
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Constants
define( 'WPYOGFLEET_VERSION',     '3.5.6' );
define( 'WPYOGFLEET_PLUGIN_FILE', __FILE__ );
define( 'WPYOGFLEET_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'WPYOGFLEET_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'WPYOGFLEET_PREFIX',      'wpyogfleet_' );

// Autoload
require_once WPYOGFLEET_PLUGIN_DIR . 'includes/class-jtb-activator.php';
require_once WPYOGFLEET_PLUGIN_DIR . 'includes/class-jtb-settings.php';
require_once WPYOGFLEET_PLUGIN_DIR . 'includes/class-jtb-db.php';
require_once WPYOGFLEET_PLUGIN_DIR . 'includes/class-jtb-input.php';
require_once WPYOGFLEET_PLUGIN_DIR . 'includes/class-jtb-admin.php';
require_once WPYOGFLEET_PLUGIN_DIR . 'includes/class-jtb-ajax.php';
require_once WPYOGFLEET_PLUGIN_DIR . 'includes/class-jtb-frontend.php';

// Activation / Deactivation
register_activation_hook( __FILE__, array( 'WPYOGFLEET_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPYOGFLEET_Activator', 'deactivate' ) );

// Bootstrap
function wpyogfleet_init() {
    // Run DB migrations automatically when plugin version changes
    if ( get_option( 'wpyogfleet_db_version' ) !== WPYOGFLEET_VERSION ) {
        WPYOGFLEET_Activator::activate();
    }

    $admin    = new WPYOGFLEET_Admin();
    $admin->init();

    $ajax = new WPYOGFLEET_Ajax();
    $ajax->init();

    $frontend = new WPYOGFLEET_Frontend();
    $frontend->init();
}
add_action( 'plugins_loaded', 'wpyogfleet_init' );
