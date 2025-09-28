<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Stats_Admin {
    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
    }

    public static function add_menu() {
        // top-level menu "Statistiques"
        add_menu_page(
            __( 'Statistiques', 'stats-visites' ),
            __( 'Statistiques', 'stats-visites' ),
            'manage_options',
            'stats-visites',
            [ __CLASS__, 'page_stats' ],
            'dashicons-chart-area',
            3
        );
    }

    public static function enqueue( $hook ) {
        if ( strpos( $hook, 'stats-visites' ) === false ) return;
        wp_enqueue_style( 'stats-visites-admin', STATS_VISITES_URL . 'assets/admin.css', [], '1.0' );
    }

    protected static function sanitize_range( $r ) {
        $allowed = [ 'all', 'today', '30', '7', '1' ];
        return in_array( $r, $allowed, true ) ? $r : 'all';
    }

    private function is_post_article($url) {
        // Reconstituer l'URL complète
        $full_url = home_url($url);

        // Récupérer l'ID WordPress de la ressource
        $post_id = url_to_postid($full_url);

        if ($post_id) {
            // Vérifier que c'est bien un article
            $post_type = get_post_type($post_id);
            return $post_type === 'post';
        }

        return false;
    }

    private static function parseUserAgent($ua) {
        $browser = 'Inconnu';
        $version = '';
        $os = 'Inconnu';
        $arch = '';

        // Détecter le navigateur et sa version
        if (preg_match('/Firefox\/([0-9\.]+)/i', $ua, $matches)) {
            $browser = 'Firefox';
            $version = $matches[1];
        } elseif (preg_match('/Chrome\/([0-9\.]+)/i', $ua, $matches)) {
            $browser = 'Chrome';
            $version = $matches[1];
        } elseif (preg_match('/Edg\/([0-9\.]+)/i', $ua, $matches)) {
            $browser = 'Edge';
            $version = $matches[1];
        } elseif (preg_match('/Safari\/([0-9\.]+)/i', $ua, $matches) && !preg_match('/Chrome/i', $ua)) {
            $browser = 'Safari';
            if (preg_match('/Version\/([0-9\.]+)/i', $ua, $m)) {
                $version = $m[1];
            }
        }

        // Détecter OS et architecture
        if (preg_match('/Windows NT/i', $ua)) {
            $os = 'Windows';
            if (preg_match('/WOW64|Win64|x64/i', $ua)) $arch = '64 bits';
            else $arch = '32 bits';
        } elseif (preg_match('/Linux/i', $ua)) {
            $os = 'Linux';
            if (preg_match('/x86_64|amd64/i', $ua)) $arch = '64 bits';
            else $arch = '32 bits';
        } elseif (preg_match('/Mac OS X/i', $ua)) {
            $os = 'Mac OS X';
            if (preg_match('/x86_64|arm64/i', $ua)) $arch = '64 bits';
            else $arch = '32 bits';
        }

        return "$browser, $version, $os, $arch";
    }

    public static function page_stats() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // --- Handle Reset Action ---
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'reset_stats' && isset( $_GET['_wpnonce'] ) ) {
            if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'stats_reset_nonce' ) ) {
                wp_die( 'La vérification de sécurité a échoué.' );
            }

            global $wpdb;
            $prefix = $wpdb->prefix;

            $tables_to_truncate = [
                $prefix . 'stats_post_views',
                $prefix . 'stats_post_totals',
                $prefix . 'stats_sessions',
            ];

            foreach ( $tables_to_truncate as $table ) {
                $wpdb->query( "TRUNCATE TABLE {$table}" );
            }

            wp_safe_redirect( admin_url( 'admin.php?page=stats-visites&stats_message=reset_success' ) );
            exit;
        }

        // --- Handle Clear Sessions Action ---
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'clear_sessions' && isset( $_GET['_wpnonce_sessions'] ) ) {
            if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce_sessions'] ), 'stats_clear_sessions_nonce' ) ) {
                wp_die( 'La vérification de sécurité a échoué.' );
            }

            global $wpdb;
            $prefix = $wpdb->prefix;

            $wpdb->query( "TRUNCATE TABLE {$prefix}stats_sessions" );

            wp_safe_redirect( admin_url( 'admin.php?page=stats-visites&stats_message=clear_sessions_success' ) );
            exit;
        }

        global $wpdb;
        $prefix = $wpdb->prefix;

        // params
        $range = isset( $_GET['range'] ) ? sanitize_text_field( wp_unslash( $_GET['range'] ) ) : 'all';
        $range = self::sanitize_range( $range );
        $paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $per_page = 50;
        $offset = ( $paged - 1 ) * $per_page;

        // active visitors: last_activity within 5 minutes
        $active_window = date( 'Y-m-d H:i:s', strtotime( '-5 minutes', current_time( 'timestamp' ) ) );
        $active_count = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$prefix}stats_sessions WHERE last_activity >= %s", $active_window )
        );
        $active_rows = $wpdb->get_results(
            $wpdb->prepare( "SELECT page, ip_hash, post_id, last_activity, user_agent FROM {$prefix}stats_sessions WHERE last_activity >= %s ORDER BY last_activity DESC LIMIT 200", $active_window )
        );
        $active_visitors = 0;
        foreach ( $active_rows as $r ) {
            $post_id = $r->post_id;
            if ( ! $post_id ) {
                // Check for specific API URL pattern from other plugins
                if ( preg_match( '#/wp-json/wordpress-popular-posts/v2/views/(\d+)#', $r->page, $matches ) ) {
                    $post_id = (int) $matches[1];
                } else {
                    $post_id = url_to_postid( home_url( $r->page ) );
                }
            }
            if ( $post_id && get_post_type( $post_id ) == 'post' ) {
                $active_visitors++;
            }
        }

        // Get total sessions count for the new button
        $total_sessions = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}stats_sessions" );

        // total views all time
        $total_views_all_time = (int) $wpdb->get_var( "SELECT SUM(view_count) FROM {$prefix}stats_post_totals" );

        // build posts stats depending on range
        if ( $range === 'all' ) {
            // use totals table (fast)
            $sql_total = "SELECT t.post_id, t.view_count, p.post_title
                FROM {$prefix}stats_post_totals t
                LEFT JOIN {$wpdb->posts} p ON p.ID = t.post_id
                WHERE p.post_type = 'post'
                ORDER BY t.view_count DESC
                LIMIT %d OFFSET %d";
            $rows = $wpdb->get_results( $wpdb->prepare( $sql_total, $per_page, $offset ) );
            $total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}stats_post_totals" );
        } else {
            // range in days: '30','7','1' (1 = yesterday)
            if ( $range === 'today' ) {
                // today
                $start = date( 'Y-m-d 00:00:00', current_time( 'timestamp' ) );
                $where = $wpdb->prepare( "WHERE sv.viewed_at >= %s", $start );
            } elseif ( $range === '1' ) {
                // yesterday full day
                $start = date( 'Y-m-d 00:00:00', strtotime( '-1 day', current_time( 'timestamp' ) ) );
                $end = date( 'Y-m-d 23:59:59', strtotime( '-1 day', current_time( 'timestamp' ) ) );
                $where = $wpdb->prepare( "WHERE sv.viewed_at BETWEEN %s AND %s", $start, $end );
            } else { // '30', '7'
                $days = intval( $range );
                $start = date( 'Y-m-d H:i:s', strtotime( "-{$days} days", current_time( 'timestamp' ) ) );
                $where = $wpdb->prepare( "WHERE sv.viewed_at >= %s", $start );
            }

            $sql = "SELECT sv.post_id, COUNT(*) AS views, p.post_title
                FROM {$prefix}stats_post_views sv
                LEFT JOIN {$wpdb->posts} p ON p.ID = sv.post_id
                {$where}
                AND p.post_type = 'post'
                GROUP BY sv.post_id
                ORDER BY views DESC
                LIMIT %d OFFSET %d";
            $rows = $wpdb->get_results( $wpdb->prepare( $sql, $per_page, $offset ) );

            // total items for pagination
            $sql_count = "SELECT COUNT(DISTINCT sv.post_id) FROM {$prefix}stats_post_views sv
                LEFT JOIN {$wpdb->posts} p ON p.ID = sv.post_id
                {$where}
                AND p.post_type = 'post'";
            $total_items = (int) $wpdb->get_var( $sql_count );
        }

        // pagination math
        $total_pages = (int) ceil( $total_items / $per_page );

        // render (minimal)
        ?>
        <div class="wrap stats-visites-wrap">
            <h1><?php esc_html_e( 'Statistiques des articles', 'stats-visites' ); ?></h1>

            <?php if ( isset( $_GET['stats_message'] ) && $_GET['stats_message'] === 'reset_success' ): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Toutes les données de statistiques ont été remises à zéro.', 'stats-visites' ); ?></p>
                </div>
            <?php elseif ( isset( $_GET['stats_message'] ) && $_GET['stats_message'] === 'clear_sessions_success' ): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'La table des sessions a été vidée avec succès.', 'stats-visites' ); ?></p>
                </div>
            <?php endif; ?>

            <div class="stats-overview">

                <div class="stat-card">
                    <h2><?php echo intval( $total_views_all_time ); ?></h2>
                    <p><?php esc_html_e( 'Total vues', 'stats-visites' ); ?></p>
                </div>

                <div class="stat-card">
                    <h2><?php echo intval( $active_count ); ?></h2>
                    <p><?php esc_html_e( 'Visiteurs actifs (5 min)', 'stats-visites' ); ?></p>
                </div>

                <div class="stat-card">
                    <h2><?php echo $active_visitors; ?></h2>
                    <p><?php esc_html_e( 'Actifs sur un article (5 min)', 'stats-visites' ); ?></p>
                </div>

                <div class="stat-card">
                    <form method="get" class="stats-filter-form">
                        <input type="hidden" name="page" value="stats-visites">
                        <select id="range" name="range" onchange="this.form.submit()">
                            <option value="all" <?php selected( $range, 'all' ); ?>><?php esc_html_e( 'Depuis toujours', 'stats-visites' ); ?></option>
                            <option value="30" <?php selected( $range, '30' ); ?>><?php esc_html_e( '30 derniers jours', 'stats-visites' ); ?></option>
                            <option value="7" <?php selected( $range, '7' ); ?>><?php esc_html_e( '7 derniers jours', 'stats-visites' ); ?></option>
                            <option value="1" <?php selected( $range, '1' ); ?>><?php esc_html_e( 'Hier', 'stats-visites' ); ?></option>
                            <option value="today" <?php selected( $range, 'today' ); ?>><?php esc_html_e( 'Aujourd\'hui', 'stats-visites' ); ?></option>
                        </select>
                    </form>
                </div>
            </div>

            <div class="active-list">
                <h2><?php esc_html_e( 'Actifs sur les articles', 'stats-visites' ); ?></h2>
                <table class="widefat">
                    <thead><tr><th><?php esc_html_e( 'Page', 'stats-visites' ); ?></th><th><?php esc_html_e( 'Post_ID', 'stats-visites' ); ?></th><th><?php esc_html_e( 'Dernière_activité', 'stats-visites' ); ?></th><th><?php esc_html_e( 'IP', 'stats-visites' ); ?></th><th><?php esc_html_e( 'User Agent', 'stats-visites' ); ?></th></tr></thead>
                    <tbody>
                        <?php if ( $active_rows ): ?>
                            <?php foreach ( $active_rows as $r ): ?>
                                <?php
                                $post_id = $r->post_id;

                                // Si l'ID est vide, on tente de le déduire de l'URL
                                if ( preg_match( '#/wp-json/wordpress-popular-posts/v2/views/(\d+)#', $r->page, $matches ) ) {
                                    $post_id = (int) $matches[1];
                                } else {
                                    $post_id = url_to_postid( home_url( $r->page ) );
                                }

                                // Vérifier si c'est bien un article
                                if ( $post_id && get_post_type( $post_id ) === 'post' ):
                                ?>
                                    <tr>
                                        <td><?php echo esc_html( $r->page ); ?></td>
                                        <td><?php echo esc_html( $post_id ); ?></td>
                                        <td><?php echo esc_html( (new DateTime($r->last_activity))->format('H:i:s') ); ?><br><small><?php echo esc_html( (new DateTime($r->last_activity))->format('d/m/Y') ); ?></small></td>
                                        <td><?php echo esc_html( $r->ip_hash ); ?></td>
                                        <td><?php echo esc_html( self::parseUserAgent( $r->user_agent ) ); ?><br><small><?php echo esc_html( $r->user_agent ); ?></small></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3"><?php esc_html_e( 'Aucun visiteur actif', 'stats-visites' ); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="posts-list">
                <h2><?php esc_html_e( 'Classement des articles', 'stats-visites' ); ?></h2>
                <table class="widefat">
                    <thead><tr><th><?php esc_html_e( 'Titre', 'stats-visites' ); ?></th><th><?php esc_html_e( 'ID', 'stats-visites' ); ?></th><th><?php esc_html_e( 'Vues', 'stats-visites' ); ?></th></tr></thead>
                    <tbody>
                        <?php if ( $rows ): foreach ( $rows as $row ): ?>
                            <tr>
                                <td><?php echo esc_html( $row->post_title ?: '(no title)' ); ?></td>
                                <td><?php echo esc_html( $row->post_id ); ?></td>
                                <td><?php echo esc_html( isset( $row->view_count ) ? $row->view_count : ( isset( $row->views ) ? $row->views : 0 ) ); ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="3"><?php esc_html_e( 'Aucun résultat', 'stats-visites' ); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="pagination">
                    <?php
                    $base_url = esc_url_raw( add_query_arg( array( 'page' => 'stats-visites', 'range' => $range ), admin_url( 'admin.php' ) ) );
                    for ( $i = 1; $i <= max(1,$total_pages); $i++ ) {
                        $url = add_query_arg( 'paged', $i, $base_url );
                        $class = ( $i === $paged ) ? 'page-number current' : 'page-number';
                        echo '<a class="'.esc_attr($class).'" href="'.esc_url($url).'">'.intval($i).'</a> ';
                    }
                    ?>
                </div>

                <div class="tools-section" style="margin-top: 40px;">
                    <hr>
                    <h2><?php esc_html_e( 'Outils de maintenance', 'stats-visites' ); ?></h2>
                    <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                        <div class="stat-card">
                            <p><?php esc_html_e( 'Effacer toutes les données de statistiques. Cette action est irréversible.', 'stats-visites' ); ?></p>
                            <form method="GET" style="margin-top: 10px;">
                                <input type="hidden" name="page" value="stats-visites">
                                <input type="hidden" name="action" value="reset_stats">
                                <?php wp_nonce_field( 'stats_reset_nonce' ); ?>
                                <input type="submit" class="button button-secondary" style="color: #d63638; border-color: #d63638;" value="<?php esc_attr_e( 'Remettre à zéro', 'stats-visites' ); ?>"
                                    onclick="return confirm('<?php esc_attr_e( 'Êtes-vous sûr de vouloir effacer TOUTES les statistiques ? Cette action est irréversible.', 'stats-visites' ); ?>');">
                            </form>
                        </div>
                        <div class="stat-card">
                            <p><?php esc_html_e( 'Vider la table des sessions de visiteurs. Utile pour la maintenance.', 'stats-visites' ); ?></p>
                            <form method="GET" style="margin-top: 10px;">
                                <input type="hidden" name="page" value="stats-visites">
                                <input type="hidden" name="action" value="clear_sessions">
                                <?php wp_nonce_field( 'stats_clear_sessions_nonce', '_wpnonce_sessions' ); ?>
                                <input type="submit" class="button button-secondary" value="<?php printf( esc_attr__( 'Vider les sessions (%s entrées)', 'stats-visites' ), number_format_i18n( $total_sessions ) ); ?>"
                                       onclick="return confirm('<?php esc_attr_e( 'Êtes-vous sûr de vouloir vider la table des sessions ?', 'stats-visites' ); ?>');">
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
