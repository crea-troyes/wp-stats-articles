<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;
$prefix = $wpdb->prefix;

$tables = [
    $prefix . 'stats_post_views',
    $prefix . 'stats_post_totals',
    $prefix . 'stats_sessions',
];

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}
