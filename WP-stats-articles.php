<?php
/**
 * Plugin Name: WP Stats Articles
 * Description: Statistiques de visites des articles.
 * Version: 1.0.0
 * Author: GUILLIER Alban
 * Text Domain: WP-stats-visites
 * Author URI:  https://blog.crea-troyes.fr
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Plugin URI:  https://github.com/tonpseudo/wp-article-visitor-counter
 * Plugin URI:  https://github.com/crea-troyes/wp-stats-articles
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'STATS_VISITES_DIR', plugin_dir_path( __FILE__ ) );
define( 'STATS_VISITES_URL', plugin_dir_url( __FILE__ ) );

require_once STATS_VISITES_DIR . 'includes/class-stats-db.php';
require_once STATS_VISITES_DIR . 'includes/class-stats-tracker.php';
require_once STATS_VISITES_DIR . 'includes/class-stats-admin.php';

register_activation_hook( __FILE__, array( 'Stats_DB', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Stats_DB', 'deactivate' ) );

add_action( 'plugins_loaded', function() {
    Stats_Tracker::init();
    Stats_Admin::init();
} );
