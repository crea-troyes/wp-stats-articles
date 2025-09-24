<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Stats_DB {
    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;

        $sql = [];

        // table : post views (historique pour filtres)
        $sql[] = "CREATE TABLE {$prefix}stats_post_views (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            viewed_at DATETIME NOT NULL,
            ip_hash VARCHAR(64) NOT NULL,
            user_agent TEXT,
            PRIMARY KEY (id),
            INDEX (post_id),
            INDEX (viewed_at)
        ) $charset_collate;";

        // table : totaux (optimisation pour tri)
        $sql[] = "CREATE TABLE {$prefix}stats_post_totals (
            post_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            view_count BIGINT UNSIGNED NOT NULL DEFAULT 0
        ) $charset_collate;";

        // table : sessions / visiteurs actifs
        $sql[] = "CREATE TABLE {$prefix}stats_sessions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ip_hash VARCHAR(64) NOT NULL,
            last_activity DATETIME NOT NULL,
            page VARCHAR(191) DEFAULT NULL,
            post_id BIGINT UNSIGNED DEFAULT NULL,
            user_agent TEXT,
            PRIMARY KEY (id),
            UNIQUE KEY ip_hash_unique (ip_hash)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // ensure default capability or option if needed
        add_option( 'stats_visites_db_version', '1.0' );
    }

    public static function deactivate() {
        // pas de suppression automatique
    }
}
