<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Stats_Tracker {
    protected static $bot_patterns = [
        // moteurs de recherche classiques
        'googlebot', 'bingbot', 'slurp', 'yahoo', 'yandex', 'duckduckbot', 
        'baiduspider', 'sogou', 'exabot', 'facebot', 'facebookexternalhit', 
        'mediapartners-google', 'bingpreview', 'seznambot', 'qwantify', 'qwantbot', 'nutch', 'archive.org_bot', 'yeti', 'ahoy',

        // bots SEO et analyse
        'ahrefsbot', 'semrushbot', 'mj12bot', 'dotbot', 'majestic12', 'rogerbot', 
        'blekkobot', 'sitebot', 'crawler', 'spider', 'robot', 'surveybot', 'heritrix', 'dataprovider', 'linkdexbot',

        // scrapers et outils divers
        'curl', 'wget', 'python-requests', 'httpclient', 'libwww-perl', 'java', 'node-fetch', 'ruby', 'php', 'perl', 'scrapy', 'go-http-client', 'okhttp',
        'httpx', 'axios', 'urllib', 'python-urllib', 'httpie', 'winhttp', 'httrack', 'masscan', 'fiddler', 'wordpress', 'freshrss', 'feedparser',
        'firefox/42.0', '@', 'WP.com',

        // réseaux sociaux
        'twitterbot', 'linkedinbot', 'pinterest', 'slackbot', 'telegrambot', 'line-pizza', 'whatsapp', 'tumblr', 'redditbot',

        // assistants IA et chatbots
        'discordbot', 'applebot', 'embedly', 'quora link preview', 'ahoy', 'msnbot', 'perplexitybot', 'openai', 'chatgpt', 'gptbot', 'chatgpt-user',
        'anthropic', 'bard', 'huggingface', 'bingpreview', 'youdao', 'deepmind', 'openai', 'stabilityai', 'perplexity',

        // autres bots fréquents
        'amazonbot', 'petalbot', 'yak/1.0', 'google-safety', 'trendictionbot', 'yisou', 'wotbox', 'livelapbot', 'adsbot-google', 'linkedinbot',
        'semantabot', 'uptimerobot', 'pingdom', 'site24x7', 'uptimerobot', 'monitoring', 'statuscake', 'downtimebot', 'ccleaner'
    ];

    public static function init() {
        add_action( 'template_redirect', [ __CLASS__, 'track_and_update_session' ], 10 );
    }

    protected static function is_bot( $ua ) {
        if ( empty( $ua ) ) return true;
        $ua = strtolower( $ua );
        foreach ( self::$bot_patterns as $p ) {
            if ( strpos( $ua, $p ) !== false ) return true;
        }
        return false;
    }

    protected static function ip_hash() {
        $ip = self::get_remote_addr();
        if ( ! $ip ) return '';
        // hash to avoid storing raw IP
        // return hash( 'sha256', $ip );
        return $ip;
    }

    protected static function get_remote_addr() {
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
        }
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $arr = explode( ',', wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
            return sanitize_text_field( trim( $arr[0] ) );
        }
        if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }
        return '';
    }

    public static function track_and_update_session() {
        if ( is_admin() ) return;

        // Exclude admin users
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            return;
        }

        $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
        if ( self::is_bot( $ua ) ) return;

        // First, update the session for any page view
        self::update_session( $ua );

        // Then, if it's a single post, track the view
        if ( ! is_singular( 'post' ) ) return;

        $post_id = get_queried_object_id();
        if ( ! $post_id ) return;

        global $wpdb;
        $prefix = $wpdb->prefix;
        $ip_hash = self::ip_hash();
        $now = current_time( 'mysql', 0 );

        // insert into history
        $wpdb->insert(
            $prefix . 'stats_post_views',
            [
                'post_id' => $post_id,
                'viewed_at' => $now,
                'ip_hash' => $ip_hash,
                'user_agent' => substr( $ua, 0, 65535 ),
            ],
            [ '%d', '%s', '%s', '%s' ]
        );

        $table_tot = $prefix . 'stats_post_totals';
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table_tot} (post_id, view_count) VALUES (%d, 1)
                ON DUPLICATE KEY UPDATE view_count = view_count + 1",
                $post_id
            )
        );
    }

    protected static function update_session( $ua ) {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $ip_hash = self::ip_hash();
        if ( empty( $ip_hash ) ) return;

        $now = current_time( 'mysql', 0 );
        $page = esc_url_raw( ( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/' ) );
        $post_id = ( is_singular( 'post' ) ? get_queried_object_id() : null );

        // try update existing
        $updated = $wpdb->update(
            $prefix . 'stats_sessions',
            [
                'last_activity' => $now,
                'page' => substr( $page, 0, 190 ),
                'post_id' => $post_id,
                'user_agent' => substr( $ua, 0, 65535 ),
            ],
            [ 'ip_hash' => $ip_hash ],
            [ '%s', '%s', '%d', '%s' ],
            [ '%s' ]
        );

        if ( $updated === false ) return;

        if ( $updated === 0 ) {
            // insert new
            $wpdb->insert(
                $prefix . 'stats_sessions',
                [
                    'ip_hash' => $ip_hash,
                    'last_activity' => $now,
                    'page' => substr( $page, 0, 190 ),
                    'post_id' => $post_id,
                    'user_agent' => substr( $ua, 0, 65535 ),
                ],
                [ '%s', '%s', '%s', '%d', '%s' ]
            );
        }
    }
}
