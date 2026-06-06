<?php
/**
 * Plugin Name:  MultiLingo – Language Pair Checker
 * Description:  Çoklu dil desteği. Sayfalar arası dil eşleşmesi, otomatik parent ayarı, hreflang inject, dashboard widget. Settings sayfasından diller yönetilir.
 * Version:      2.2
 * Author:       Uğur KOTBAŞ
 * License:      GPL-2.0
 * Text Domain:  multilingo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MLLC_VERSION',      '2.2' );
define( 'MLLC_OPT_LANGS',   'mllc_languages' );
define( 'MLLC_OPT_DEFAULT', 'mllc_default_lang' );
define( 'MLLC_OPT_LABEL',   'mllc_plugin_label' );
define( 'MLLC_OPT_FLAGS',   'mllc_lang_flags' );
define( 'MLLC_OPT_TYPES',   'mllc_post_types' );
define( 'MLLC_NONCE_KEY',   'mllc_lang_pair_nonce' );
define( 'MLLC_NONCE_FIELD', 'mllc_nonce' );
define( 'MLLC_TRANSIENT',   'mllc_no_pair_' );

class MultiLingo_Lang_Checker {

    private $doing_parent_update = false;

    public function __construct() {
        add_action( 'admin_menu',          [ $this, 'register_settings_page' ] );
        add_action( 'admin_init',          [ $this, 'register_settings' ] );
        add_action( 'add_meta_boxes',      [ $this, 'add_meta_box' ] );
        add_action( 'save_post',           [ $this, 'save_meta' ], 10, 2 );
        add_action( 'admin_notices',       [ $this, 'admin_notice' ] );
        add_action( 'wp_dashboard_setup',  [ $this, 'dashboard_widget' ] );
        add_action( 'admin_head',          [ $this, 'quick_fill_new_page' ] );
        add_action( 'wp_head',             [ $this, 'inject_lang_globals' ], 1 );
        add_filter( 'redirect_canonical',  [ $this, 'stop_lang_root_canonical' ], 10, 2 );
        add_action( 'wp_ajax_mllc_search', [ $this, 'ajax_search_posts' ] );
        add_shortcode( 'mllc_menu',         [ $this, 'shortcode_menu' ] );
        add_shortcode( 'mllc_lang_switch',  [ $this, 'shortcode_lang_switch' ] );
        add_action( 'wp_ajax_mllc_get_menu_json',       [ $this, 'ajax_get_menu_json' ] );
        add_action( 'wp_ajax_nopriv_mllc_get_menu_json',[ $this, 'ajax_get_menu_json' ] );
    }

    /* ================================================================
       HELPERS — aktif dil listesi ve etiketler
    ================================================================ */

    public function get_langs(): array {
        $raw = get_option( MLLC_OPT_LANGS, 'en,tr' );
        return array_filter( array_map( 'trim', explode( ',', $raw ) ) );
    }

    public function get_default_lang(): string {
        return get_option( MLLC_OPT_DEFAULT, 'en' );
    }

    public function get_label(): string {
        return get_option( MLLC_OPT_LABEL, 'MultiLingo' );
    }

    public function get_flags(): array {
        $raw = get_option( MLLC_OPT_FLAGS, '{"en":"🇬🇧 English","tr":"🇹🇷 Türkçe"}' );
        $decoded = json_decode( $raw, true );
        return is_array( $decoded ) ? $decoded : [];
    }

    public function get_supported_post_types(): array {
        $raw = get_option( MLLC_OPT_TYPES, 'page,post' );
        $types = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
        $valid = [];
        foreach ( $types as $t ) {
            if ( post_type_exists( $t ) ) $valid[] = $t;
        }
        return $valid ?: [ 'page', 'post' ];
    }

    public function lang_label( string $lang ): string {
        $flags = $this->get_flags();
        return $flags[ $lang ] ?? strtoupper( $lang );
    }

    private function pair_meta_key( string $lang ): string {
        return '_mllc_pair_' . $lang;
    }

    private function get_pair_id( int $post_id, string $lang ): int {
        $new = (int) get_post_meta( $post_id, $this->pair_meta_key( $lang ), true );
        if ( $new ) return $new;

        $stored_lang = get_post_meta( $post_id, '_mllc_this_lang', true )
                    ?: get_post_meta( $post_id, '_ll_this_lang',   true );

        if ( ! $stored_lang ) return 0;

        $langs = $this->get_langs();
        if ( count( $langs ) === 2 && in_array( $lang, $langs, true ) && $lang !== $stored_lang ) {
            $legacy = (int) get_post_meta( $post_id, '_ll_alt_page_id', true );
            if ( $legacy ) return $legacy;
        }

        return 0;
    }

    private function get_this_lang( int $post_id ): string {
        return get_post_meta( $post_id, '_mllc_this_lang', true )
            ?: get_post_meta( $post_id, '_ll_this_lang',   true )
            ?: '';
    }

    /* ================================================================
       SETTINGS PAGE
    ================================================================ */

    public function register_settings_page() {
        add_options_page(
            'MultiLingo Ayarları',
            '🌐 MultiLingo',
            'manage_options',
            'multilingo-settings',
            [ $this, 'settings_page_html' ]
        );
    }

    public function register_settings() {
        register_setting( 'mllc_settings_group', MLLC_OPT_LABEL,   [ 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'mllc_settings_group', MLLC_OPT_LANGS,   [ 'sanitize_callback' => [ $this, 'sanitize_langs' ] ] );
        register_setting( 'mllc_settings_group', MLLC_OPT_DEFAULT, [ 'sanitize_callback' => 'sanitize_key' ] );
        register_setting( 'mllc_settings_group', MLLC_OPT_FLAGS,   [ 'sanitize_callback' => [ $this, 'sanitize_flags' ] ] );
        register_setting( 'mllc_settings_group', MLLC_OPT_TYPES,   [ 'sanitize_callback' => [ $this, 'sanitize_types' ] ] );
    }

    public function sanitize_types( $value ): string {
        if ( is_array( $value ) ) {
            $parts = $value;
        } else {
            $parts = explode( ',', (string) $value );
        }
        $parts = array_filter( array_map( function( $t ) {
            return sanitize_key( trim( $t ) );
        }, $parts ) );
        $valid = [];
        foreach ( array_unique( $parts ) as $t ) {
            if ( post_type_exists( $t ) ) $valid[] = $t;
        }
        return implode( ',', $valid ?: [ 'page', 'post' ] );
    }

    public function sanitize_langs( $value ): string {
        $parts = array_filter( array_map( function( $l ) {
            return preg_replace( '/[^a-z]/', '', strtolower( trim( $l ) ) );
        }, explode( ',', $value ) ) );
        return implode( ',', array_unique( $parts ) );
    }

    public function sanitize_flags( $value ): string {
        $decoded = json_decode( $value, true );
        if ( ! is_array( $decoded ) ) return get_option( MLLC_OPT_FLAGS, '{}' );
        $clean = [];
        foreach ( $decoded as $k => $v ) {
            $clean[ sanitize_key( $k ) ] = sanitize_text_field( $v );
        }
        return wp_json_encode( $clean );
    }

    public function settings_page_html() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $label   = esc_attr( $this->get_label() );
        $langs   = esc_attr( get_option( MLLC_OPT_LANGS, 'en,tr' ) );
        $default = $this->get_default_lang();
        $flags   = esc_textarea( get_option( MLLC_OPT_FLAGS, '{"en":"🇬🇧 English","tr":"🇹🇷 Türkçe"}' ) );
        $types   = esc_attr( get_option( MLLC_OPT_TYPES, 'page,post' ) );
        $active  = $this->get_langs();
        $available_types = get_post_types( [ 'public' => true ], 'objects' );
        unset( $available_types['attachment'] );
        ?>
        <div class="wrap">
            <h1>🌐 MultiLingo — Ayarlar</h1>
            <style>
                .mllc-settings-card { background:#fff; border:1px solid #c3c4c7; border-radius:8px; padding:24px 28px; max-width:780px; margin-top:20px; }
                .mllc-settings-card h2 { margin-top:0; font-size:15px; border-bottom:1px solid #f0f0f0; padding-bottom:10px; }
                .mllc-field { margin-bottom:20px; }
                .mllc-field label { display:block; font-weight:600; font-size:13px; margin-bottom:6px; color:#1d2327; }
                .mllc-field input[type=text], .mllc-field select, .mllc-field textarea {
                    width:100%; max-width:500px; padding:8px 12px; border-radius:6px;
                    border:1px solid #c3c4c7; font-size:13px; box-sizing:border-box;
                }
                .mllc-field textarea { height:90px; font-family:monospace; }
                .mllc-field .desc { font-size:12px; color:#666; margin-top:4px; }
                .mllc-field input:focus, .mllc-field select:focus, .mllc-field textarea:focus {
                    border-color:#2271b1; box-shadow:0 0 0 1px #2271b1; outline:none;
                }
                .mllc-submit { margin-top:10px; }
                .nav-tab-wrapper { margin-top:14px; }
                .mllc-menu-row { display:grid; grid-template-columns: 24px 1fr 2fr 100px 40px; gap:8px; align-items:center; margin-bottom:6px; }
                .mllc-menu-row input[type=text], .mllc-menu-row input[type=url] { width:100%; padding:6px 10px; border:1px solid #ddd; border-radius:5px; font-size:13px; }
                .mllc-menu-row .mllc-handle { color:#999; cursor:move; user-select:none; text-align:center; }
                .mllc-menu-lang-tabs { display:flex; gap:6px; margin-bottom:14px; flex-wrap:wrap; }
                .mllc-menu-lang-tab { padding:6px 14px; background:#f0f0f1; border:1px solid #ddd; border-radius:5px; cursor:pointer; font-size:12px; font-weight:600; }
                .mllc-menu-lang-tab.active { background:#2271b1; color:#fff; border-color:#2271b1; }
                .mllc-menu-pane { display:none; }
                .mllc-menu-pane.active { display:block; }
                .mllc-menu-remove { background:#E91E8C; color:#fff; border:none; border-radius:5px; width:30px; height:30px; cursor:pointer; font-weight:700; }
                .mllc-shortcode-hint { background:#f0f6fc; border:1px solid #c5d9ed; border-radius:5px; padding:10px 12px; font-size:12px; font-family:monospace; color:#1d4f7a; margin-top:10px; }
            </style>

            <?php
            $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
            $base_url     = admin_url( 'admin.php?page=multilingo-settings' );
            ?>

            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url( $base_url . '&tab=general' ); ?>" class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">⚙️ Genel</a>
                <a href="<?php echo esc_url( $base_url . '&tab=menus' ); ?>" class="nav-tab <?php echo $current_tab === 'menus' ? 'nav-tab-active' : ''; ?>">📋 Menüler</a>
                <a href="<?php echo esc_url( $base_url . '&tab=status' ); ?>" class="nav-tab <?php echo $current_tab === 'status' ? 'nav-tab-active' : ''; ?>">📊 Dil Durumu</a>
            </nav>

            <?php if ( $current_tab === 'menus' ) : ?>
                <?php $this->render_menus_tab(); return; ?>
            <?php endif; ?>

            <?php if ( $current_tab === 'status' ) : ?>
                <?php $this->render_status_tab(); return; ?>
            <?php endif; ?>

            <div class="mllc-settings-card">
                <h2>Genel Ayarlar</h2>
                <form method="post" action="options.php">
                    <?php settings_fields( 'mllc_settings_group' ); ?>

                    <div class="mllc-field">
                        <label for="mllc_plugin_label">Plugin Etiketi (Admin UI başlıklarında görünür)</label>
                        <input type="text" id="mllc_plugin_label" name="<?php echo MLLC_OPT_LABEL; ?>" value="<?php echo $label; ?>">
                    </div>

                    <div class="mllc-field">
                        <label for="mllc_languages">Aktif Diller (virgülle ayrılmış, küçük harf)</label>
                        <input type="text" id="mllc_languages" name="<?php echo MLLC_OPT_LANGS; ?>" value="<?php echo $langs; ?>" placeholder="en,ru,de,ro">
                        <div class="desc">Örnek: <code>en,ru,de,ro</code> — Her dil için /<em>lang</em>/ parent sayfa otomatik oluşturulur.</div>
                    </div>

                    <div class="mllc-field">
                        <label for="mllc_default_lang">x-default Dili (hreflang)</label>
                        <select id="mllc_default_lang" name="<?php echo MLLC_OPT_DEFAULT; ?>">
                            <?php foreach ( $active as $l ) : ?>
                                <option value="<?php echo esc_attr( $l ); ?>" <?php selected( $default, $l ); ?>><?php echo esc_html( strtoupper( $l ) ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="desc">Google'ın dil eşleşmesi yapamadığı kullanıcılara gösterilecek sürüm.</div>
                    </div>

                    <div class="mllc-field">
                        <label for="mllc_lang_flags">Dil Etiketleri (JSON)</label>
                        <textarea id="mllc_lang_flags" name="<?php echo MLLC_OPT_FLAGS; ?>"><?php echo $flags; ?></textarea>
                        <div class="desc">Örnek: <code>{"en":"🇬🇧 English","ru":"🇷🇺 Русский","de":"🇩🇪 Deutsch","ro":"🇷🇴 Română"}</code></div>
                    </div>

                    <div class="mllc-field">
                        <label for="mllc_post_types">Çeviri Desteklenen İçerik Türleri</label>
                        <select id="mllc_post_types" name="<?php echo MLLC_OPT_TYPES; ?>[]" multiple size="6" style="height:auto;">
                            <?php foreach ( $available_types as $pt ) :
                                $sel = in_array( $pt->name, explode( ',', $types ), true ); ?>
                                <option value="<?php echo esc_attr( $pt->name ); ?>" <?php selected( $sel ); ?>>
                                    <?php echo esc_html( $pt->labels->singular_name . ' (' . $pt->name . ')' ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="desc">Birden fazla seçim için <strong>Ctrl/Cmd</strong> tuşuna basılı tutun. UAE Header/Footer, Elementor Template gibi CPT'leri ekleyebilirsiniz.</div>
                    </div>

                    <div class="mllc-submit">
                        <?php submit_button( 'Kaydet', 'primary', 'submit', false ); ?>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /* ================================================================
       CANONICAL REDIRECT ENGELLEYİCİ
    ================================================================ */

    public function stop_lang_root_canonical( $redirect_url, $requested_url ) {
        $path  = rtrim( parse_url( $requested_url, PHP_URL_PATH ), '/' );
        $slugs = array_map( fn( $l ) => '/' . $l, $this->get_langs() );
        if ( in_array( $path, $slugs, true ) ) {
            return false;
        }
        return $redirect_url;
    }

    /* ================================================================
       WP_HEAD → hreflang inject + lang globals + data-ll-href
    ================================================================ */

    public function inject_lang_globals() {
        if ( ! is_singular() ) return;

        $post_id   = get_the_ID();
        $this_lang = $this->get_this_lang( $post_id );
        if ( ! $this_lang ) return;

        $langs     = $this->get_langs();
        $default   = $this->get_default_lang();
        $home      = home_url();

        $cur_permalink = get_permalink( $post_id );
        $cur_path      = parse_url( $cur_permalink, PHP_URL_PATH )
                         ?: '/' . $this_lang . '/' . get_post_field( 'post_name', $post_id ) . '/';

        $hreflang_map = [ $this_lang => $home . $cur_path ];
        $alt_urls     = [];

        foreach ( $langs as $lang ) {
            if ( $lang === $this_lang ) continue;
            $alt_id = $this->get_pair_id( $post_id, $lang );
            if ( ! $alt_id ) continue;

            clean_post_cache( $alt_id );
            $raw = get_permalink( $alt_id );
            if ( ! $raw ) continue;

            $path = parse_url( $raw, PHP_URL_PATH ) ?? '';

            if ( strpos( $path, '/' . $lang . '/' ) !== false ) {
                $alt_path = $path;
            } else {
                $alt_slug = get_post_field( 'post_name', $alt_id );
                $alt_path = '/' . $lang . '/' . $alt_slug . '/';
            }

            $hreflang_map[ $lang ] = $home . $alt_path;
            $alt_urls[ $lang ]     = $alt_path;
        }

        echo '<script>/* MultiLingo v' . MLLC_VERSION . " */\n";
        echo 'window.mllcCurrentLang = ' . wp_json_encode( $this_lang ) . ";\n";
        if ( $alt_urls ) {
            echo 'window.mllcAlternateLangs = ' . wp_json_encode( $alt_urls ) . ";\n";
        }
        echo '/* mllc_debug: post=' . (int) $post_id . ' lang=' . esc_js( $this_lang ) . " */\n";

        if ( $alt_urls ) {
            echo 'document.addEventListener("DOMContentLoaded",function(){';
            foreach ( $alt_urls as $lang => $alt_path ) {
                echo 'var _a=document.querySelector("#llLangSwitch a[data-lang=\"' . esc_js( $lang ) . '\"]");';
                echo 'if(_a)_a.setAttribute("data-ll-href",' . wp_json_encode( $alt_path ) . ');';
            }
            echo 'var _c=document.querySelector("#llLangSwitch a[data-lang=\"' . esc_js( $this_lang ) . '\"]");';
            echo 'if(_c)_c.setAttribute("data-ll-href",' . wp_json_encode( $cur_path ) . ');';
            echo "});\n";
        }
        echo "</script>\n";

        foreach ( $hreflang_map as $lang => $url ) {
            echo '<link rel="alternate" hreflang="' . esc_attr( $lang ) . '" href="' . esc_url( $url ) . '">' . "\n";
        }
        $default_url = $hreflang_map[ $default ] ?? reset( $hreflang_map );
        if ( $default_url ) {
            echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $default_url ) . '">' . "\n";
        }
    }

    /* ================================================================
       AJAX — Sayfa / Yazı Arama (meta box)
    ================================================================ */

    public function ajax_search_posts() {
        check_ajax_referer( MLLC_NONCE_KEY, 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) wp_die( 'Unauthorized', 403 );

        $q         = sanitize_text_field( $_GET['q']         ?? '' );
        $exclude   = (int)           ( $_GET['exclude']   ?? 0 );
        $post_type = sanitize_key(     $_GET['post_type'] ?? '' );

        $supported = $this->get_supported_post_types();
        if ( ! in_array( $post_type, $supported, true ) ) $post_type = $supported[0] ?? 'page';

        $results = get_posts( [
            'post_type'      => $post_type,
            'post_status'    => [ 'publish', 'draft' ],
            'posts_per_page' => 20,
            's'              => $q,
            'orderby'        => 'relevance',
            'post__not_in'   => $exclude ? [ $exclude ] : [],
        ] );

        $out = [];
        foreach ( $results as $p ) {
            $type  = $p->post_type === 'post' ? '📝' : '📄';
            $draft = $p->post_status === 'draft' ? ' — draft' : '';
            $out[] = [
                'id'    => $p->ID,
                'label' => $type . ' ' . $p->post_title . ' (' . $p->post_name . $draft . ')',
            ];
        }

        wp_send_json_success( $out );
    }

    /* ================================================================
       META BOX
    ================================================================ */

    public function get_suggested_pairs( int $post_id, string $this_lang ): array {
        $suggestions = [];
        $langs       = $this->get_langs();

        $current = get_post( $post_id );
        if ( ! $current ) return [];

        $parent_id = (int) $current->post_parent;
        $current_slug = $current->post_name;

        foreach ( $langs as $lang ) {
            if ( $lang === $this_lang ) continue;
            if ( $this->get_pair_id( $post_id, $lang ) ) continue;

            $candidates = [];

            if ( $parent_id ) {
                $siblings = get_posts( [
                    'post_type'      => $current->post_type,
                    'post_status'    => [ 'publish', 'draft' ],
                    'posts_per_page' => 50,
                    'post_parent'    => $parent_id,
                    'post__not_in'   => [ $post_id ],
                ] );
                foreach ( $siblings as $s ) {
                    if ( $this->get_this_lang( $s->ID ) === $lang ) {
                        $candidates[] = $s;
                    }
                }
            }

            if ( ! $candidates ) {
                $same_type = get_posts( [
                    'post_type'      => $current->post_type,
                    'post_status'    => [ 'publish', 'draft' ],
                    'posts_per_page' => 20,
                    'post__not_in'   => [ $post_id ],
                    'meta_query'     => [
                        [
                            'key'     => '_mllc_this_lang',
                            'value'   => $lang,
                            'compare' => '=',
                        ],
                    ],
                ] );
                $candidates = $same_type;
            }

            $best = null;
            $best_score = 0;
            foreach ( $candidates as $c ) {
                $score = 0;
                similar_text( strtolower( $c->post_title ), strtolower( $current->post_title ), $percent );
                $score += (int) $percent;
                if ( $c->post_name === $current_slug ) $score += 50;
                if ( $parent_id && (int) $c->post_parent === $parent_id ) $score += 30;
                if ( $score > $best_score ) {
                    $best_score = $score;
                    $best = $c;
                }
            }

            if ( $best ) {
                $suggestions[ $lang ] = [
                    'id'    => $best->ID,
                    'title' => $best->post_title,
                    'slug'  => $best->post_name,
                    'score' => $best_score,
                ];
            }
        }

        return $suggestions;
    }

    public function add_meta_box() {
        $label = $this->get_label();
        $langs = $this->get_langs();
        $codes = implode( ' / ', array_map( 'strtoupper', $langs ) );

        $screens = $this->get_supported_post_types();
        foreach ( $screens as $screen ) {
            add_meta_box(
                'mllc_lang_pair',
                '🌐 ' . esc_html( $label ) . ' — ' . esc_html( $codes ),
                [ $this, 'meta_box_html' ],
                $screen,
                'side',
                'high'
            );
        }
    }

    public function meta_box_html( $post ) {
        $nonce     = wp_create_nonce( MLLC_NONCE_KEY );
        wp_nonce_field( MLLC_NONCE_KEY, MLLC_NONCE_FIELD );

        $this_lang = $this->get_this_lang( $post->ID );
        $langs     = $this->get_langs();
        $label     = $this->get_label();

        $paired_langs  = [];
        $missing_langs = [];

        foreach ( $langs as $lang ) {
            if ( $lang === $this_lang ) continue;
            $pid = $this->get_pair_id( $post->ID, $lang );
            if ( $pid ) {
                $paired_langs[ $lang ] = $pid;
            } elseif ( $this_lang ) {
                $missing_langs[] = $lang;
            }
        }

        $total_pairs   = count( $langs ) - 1;
        $current_pairs = count( $paired_langs );

        if ( ! $this_lang ) {
            $status_color = '#999';
            $status_icon  = '— Dil Tanımlanmadı';
        } elseif ( $current_pairs === $total_pairs ) {
            $status_color = '#46b450';
            $status_icon  = '✅ Tüm diller eşleştirildi';
        } elseif ( $current_pairs > 0 ) {
            $status_color = '#f0a500';
            $status_icon  = '⚠️ ' . $current_pairs . '/' . $total_pairs . ' dil eşleştirildi';
        } else {
            $status_color = '#E91E8C';
            $status_icon  = '⚠️ Hiç eşleşme yok';
        }
        ?>
        <style>
            .mllc-meta a.mllc-btn {
                display:block; width:100%; box-sizing:border-box;
                padding:7px 10px; border-radius:5px; font-size:13px;
                background:#2271b1; color:#fff; text-align:center;
                text-decoration:none; font-weight:600; margin-bottom:8px;
                transition:background .2s;
            }
            .mllc-meta a.mllc-btn:hover { background:#135e96; }
            .mllc-meta .mllc-status {
                font-size:12px; font-weight:600; margin-bottom:10px;
                padding:5px 8px; border-radius:4px; background:#f9f9f9;
                border-left:3px solid <?php echo esc_attr( $status_color ); ?>;
                color: <?php echo esc_attr( $status_color ); ?>;
            }
            .mllc-meta label { font-weight:600; font-size:12px; display:block; margin-bottom:4px; color:#555; }
            .mllc-meta select { width:100%; padding:7px 10px; border-radius:5px; font-size:13px; border:1px solid #ddd; margin-bottom:10px; box-sizing:border-box; }
            .mllc-lang-block { border:1px solid #e5e5e5; border-radius:6px; padding:10px; margin-bottom:10px; background:#fafafa; }
            .mllc-lang-block-title { font-size:11px; font-weight:700; color:#666; text-transform:uppercase; letter-spacing:.5px; margin-bottom:8px; }
            .mllc-search-wrap { position:relative; margin-bottom:6px; }
            .mllc-search-wrap input {
                width:100%; padding:6px 10px; border-radius:5px; font-size:12px;
                border:1px solid #ddd; box-sizing:border-box;
            }
            .mllc-search-wrap input:focus { border-color:#2271b1; outline:none; box-shadow:0 0 0 1px #2271b1; }
            .mllc-search-results {
                position:absolute; top:100%; left:0; right:0;
                background:#fff; border:1px solid #ddd; border-top:none;
                border-radius:0 0 5px 5px; max-height:180px; overflow-y:auto;
                z-index:9999; display:none;
            }
            .mllc-result-item { padding:7px 10px; font-size:12px; cursor:pointer; border-bottom:1px solid #f0f0f0; transition:background .15s; }
            .mllc-result-item:hover { background:#e8f0fe; color:#1a73e8; }
            .mllc-selected {
                background:#f0faf0; border:1px solid #c3e6cb; border-radius:5px;
                padding:7px 10px; font-size:12px; margin-bottom:6px;
                display:flex; align-items:center; justify-content:space-between;
            }
            .mllc-selected .mllc-clear { color:#E91E8C; cursor:pointer; font-weight:700; font-size:14px; line-height:1; }
            .mllc-pair-links { font-size:11px; margin-bottom:2px; }
            .mllc-pair-links a { color:#2271b1; text-decoration:none; margin-right:8px; }
            .mllc-suggestions, .mllc-suggestions-empty {
                border:1px solid #e5e5e5; border-radius:6px; margin-bottom:10px; background:#fafafa; overflow:hidden;
            }
            .mllc-suggestions-title {
                display:flex; justify-content:space-between; align-items:center;
                padding:8px 10px; background:#f0f0f0; border-bottom:1px solid #e5e5e5;
                font-size:11px; font-weight:700; color:#444; text-transform:uppercase; letter-spacing:.5px;
                cursor:pointer; user-select:none;
            }
            .mllc-suggestions-toggle { font-size:14px; color:#777; transition:transform .2s; }
            .mllc-suggestions.collapsed .mllc-suggestions-toggle { transform:rotate(-90deg); }
            .mllc-suggestions-body { padding:10px; }
            .mllc-suggestions.collapsed .mllc-suggestions-body { display:none; }
            .mllc-suggestions-desc { font-size:11px; color:#666; margin:0 0 8px; font-style:italic; }
            .mllc-suggestions-desc-empty { font-size:11px; color:#888; margin:0; font-style:italic; line-height:1.4; }
            .mllc-suggestions-list { list-style:none; margin:0; padding:0; }
            .mllc-suggestion-item {
                display:flex; align-items:center; gap:6px; padding:6px 0;
                border-bottom:1px solid #ececec; font-size:12px;
            }
            .mllc-suggestion-item:last-child { border-bottom:none; }
            .mllc-suggestion-lang {
                font-size:10px; font-weight:700; background:#2271b1; color:#fff;
                padding:2px 6px; border-radius:3px; flex-shrink:0;
            }
            .mllc-suggestion-title { flex:1; color:#1d2327; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
            .mllc-suggestion-apply {
                background:#46b450; color:#fff; border:none; padding:4px 10px;
                border-radius:4px; font-size:11px; font-weight:600; cursor:pointer;
                transition:background .2s; flex-shrink:0;
            }
            .mllc-suggestion-apply:hover { background:#2e8540; }
            .mllc-suggestion-apply:disabled { background:#999; cursor:wait; }
        </style>

        <div class="mllc-meta">
            <div class="mllc-status"><?php echo esc_html( $status_icon ); ?></div>

            <label>Bu sayfanın dili:</label>
            <select name="mllc_this_lang" id="mllc_this_lang">
                <option value="">— Seçiniz —</option>
                <?php foreach ( $langs as $lang ) : ?>
                    <option value="<?php echo esc_attr( $lang ); ?>" <?php selected( $this_lang, $lang ); ?>>
                        <?php echo esc_html( $this->lang_label( $lang ) ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php if ( $this_lang && $missing_langs ) :
                $suggestions = $this->get_suggested_pairs( $post->ID, $this_lang );
                if ( $suggestions ) : ?>
                    <div class="mllc-suggestions" id="mllc_suggestions">
                        <div class="mllc-suggestions-title">
                            <span>🔗 Bağlantı Önerileri</span>
                            <span class="mllc-suggestions-toggle" data-target="mllc_suggestions_body">▾</span>
                        </div>
                        <div class="mllc-suggestions-body" id="mllc_suggestions_body">
                            <p class="mllc-suggestions-desc">Bu yayın için olası dil eşleşmeleri (başlık / üst öğe benzerliğine göre):</p>
                            <ul class="mllc-suggestions-list">
                                <?php foreach ( $suggestions as $lang => $s ) : ?>
                                    <li class="mllc-suggestion-item" data-lang="<?php echo esc_attr( $lang ); ?>" data-id="<?php echo (int) $s['id']; ?>">
                                        <span class="mllc-suggestion-lang"><?php echo esc_html( strtoupper( $lang ) ); ?></span>
                                        <span class="mllc-suggestion-title"><?php echo esc_html( $s['title'] ); ?></span>
                                        <button type="button" class="mllc-suggestion-apply">Eşleştir</button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="mllc-suggestions-empty" id="mllc_suggestions">
                        <div class="mllc-suggestions-title">
                            <span>🔗 Bağlantı Önerileri</span>
                            <span class="mllc-suggestions-toggle" data-target="mllc_suggestions_body">▾</span>
                        </div>
                        <div class="mllc-suggestions-body" id="mllc_suggestions_body">
                            <p class="mllc-suggestions-desc-empty">Bu yayın için herhangi bir bağlantı önereisi gösteremiyoruz. Bu yayın için kategori ve etiket seçmeyi deneyin ve diğer yayınları burada görünmelerini sağlamak için sütun içeriği olarak işaretleyin.</p>
                        </div>
                    </div>
                <?php endif; ?>
            <?php elseif ( $this_lang && ! $missing_langs ) : ?>
                <div class="mllc-suggestions-empty" id="mllc_suggestions">
                    <div class="mllc-suggestions-title">
                        <span>🔗 Bağlantı Önerileri</span>
                        <span class="mllc-suggestions-toggle" data-target="mllc_suggestions_body">▾</span>
                    </div>
                    <div class="mllc-suggestions-body" id="mllc_suggestions_body">
                        <p class="mllc-suggestions-desc-empty" style="color:#46b450;">✅ Tüm diller eşleştirildi.</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php foreach ( $langs as $lang ) :
                if ( $lang === $this_lang && $this_lang ) continue;
                if ( ! $this_lang ) continue;

                $pair_id    = $this->get_pair_id( $post->ID, $lang );
                $pair_title = $pair_id ? get_the_title( $pair_id ) : '';
                $pair_slug  = $pair_id ? get_post_field( 'post_name', $pair_id ) : '';
                $create_url = admin_url( 'post-new.php?post_type=' . $post->post_type . '&mllc_pair_with=' . $post->ID . '&mllc_lang=' . $lang );
                ?>
                <div class="mllc-lang-block" id="mllc_block_<?php echo esc_attr( $lang ); ?>">
                    <div class="mllc-lang-block-title"><?php echo esc_html( $this->lang_label( $lang ) ); ?> eşleşmesi</div>

                    <div class="mllc-selected" id="mllc_selected_<?php echo esc_attr( $lang ); ?>" style="<?php echo $pair_id ? '' : 'display:none'; ?>">
                        <span id="mllc_pair_title_<?php echo esc_attr( $lang ); ?>"><?php echo esc_html( $pair_title ? $pair_title . ' (' . $pair_slug . ')' : '' ); ?></span>
                        <span class="mllc-clear" data-lang="<?php echo esc_attr( $lang ); ?>" title="Temizle">&times;</span>
                    </div>

                    <div class="mllc-search-wrap" id="mllc_search_wrap_<?php echo esc_attr( $lang ); ?>" style="<?php echo $pair_id ? 'display:none' : ''; ?>">
                        <input type="text" class="mllc-search-input" data-lang="<?php echo esc_attr( $lang ); ?>" placeholder="<?php echo esc_attr( $this->lang_label( $lang ) ); ?> — başlık ile ara..." autocomplete="off">
                        <div class="mllc-search-results" id="mllc_results_<?php echo esc_attr( $lang ); ?>"></div>
                    </div>

                    <input type="hidden" name="mllc_pair[<?php echo esc_attr( $lang ); ?>]" id="mllc_pair_<?php echo esc_attr( $lang ); ?>" value="<?php echo (int) $pair_id; ?>">

                    <?php if ( $pair_id ) : ?>
                    <div class="mllc-pair-links" id="mllc_pair_links_<?php echo esc_attr( $lang ); ?>">
                        <a href="<?php echo esc_url( get_edit_post_link( $pair_id ) ); ?>">✏️ Düzenle</a>
                        <a href="<?php echo esc_url( get_permalink( $pair_id ) ); ?>" target="_blank">🔗 Görüntüle</a>
                    </div>
                    <?php endif; ?>

                    <?php if ( ! $pair_id ) : ?>
                    <a href="<?php echo esc_url( $create_url ); ?>" class="mllc-btn" id="mllc_create_<?php echo esc_attr( $lang ); ?>">
                        + <?php echo esc_html( strtoupper( $lang ) ); ?> sayfası oluştur
                    </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <?php if ( ! $this_lang ) : ?>
                <p style="font-size:12px;color:#999;margin:0;">Önce sayfanın dilini seçin.</p>
            <?php endif; ?>
        </div>

        <script>
        (function(){
            var nonce    = <?php echo wp_json_encode( $nonce ); ?>;
            var postId   = <?php echo (int) $post->ID; ?>;
            var postType = <?php echo wp_json_encode( $post->post_type ); ?>;

            document.querySelectorAll('.mllc-search-input').forEach(function(input){
                var lang    = input.dataset.lang;
                var results = document.getElementById('mllc_results_' + lang);
                var wrap    = document.getElementById('mllc_search_wrap_' + lang);
                var selected= document.getElementById('mllc_selected_' + lang);
                var hidden  = document.getElementById('mllc_pair_' + lang);
                var title   = document.getElementById('mllc_pair_title_' + lang);
                var debounce;

                input.addEventListener('input', function(){
                    clearTimeout(debounce);
                    var q = this.value.trim();
                    if(q.length < 2){ results.style.display='none'; return; }
                    debounce = setTimeout(function(){
                        var url = ajaxurl + '?action=mllc_search'
                            + '&nonce='     + encodeURIComponent(nonce)
                            + '&q='         + encodeURIComponent(q)
                            + '&exclude='   + postId
                            + '&post_type=' + encodeURIComponent(postType);
                        fetch(url).then(function(r){return r.json();}).then(function(data){
                            results.innerHTML = '';
                            if(!data.success || !data.data.length){
                                results.innerHTML = '<div class="mllc-result-item" style="color:#999">Sonuç bulunamadı</div>';
                            } else {
                                data.data.forEach(function(item){
                                    var d = document.createElement('div');
                                    d.className = 'mllc-result-item';
                                    d.textContent = item.label;
                                    d.addEventListener('click', function(){
                                        hidden.value = item.id;
                                        title.textContent = item.label;
                                        selected.style.display = 'flex';
                                        wrap.style.display = 'none';
                                        results.style.display = 'none';
                                        input.value = '';
                                        var btn = document.getElementById('mllc_create_' + lang);
                                        if(btn) btn.style.display = 'none';
                                    });
                                    results.appendChild(d);
                                });
                            }
                            results.style.display = 'block';
                        });
                    }, 300);
                });

                document.addEventListener('click', function(e){
                    if(wrap && !wrap.contains(e.target)) results.style.display = 'none';
                });
            });

            document.querySelectorAll('.mllc-clear').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var lang    = this.dataset.lang;
                    var hidden  = document.getElementById('mllc_pair_' + lang);
                    var selected= document.getElementById('mllc_selected_' + lang);
                    var wrap    = document.getElementById('mllc_search_wrap_' + lang);
                    var links   = document.getElementById('mllc_pair_links_' + lang);
                    var create  = document.getElementById('mllc_create_' + lang);
                    hidden.value = '0';
                    selected.style.display = 'none';
                    wrap.style.display = 'block';
                    if(links) links.style.display = 'none';
                    if(create) create.style.display = 'block';
                });
            });

            document.querySelectorAll('.mllc-suggestions-title').forEach(function(title){
                title.addEventListener('click', function(){
                    var box = this.parentElement;
                    box.classList.toggle('collapsed');
                });
            });

            document.querySelectorAll('.mllc-suggestion-apply').forEach(function(btn){
                btn.addEventListener('click', function(e){
                    e.preventDefault();
                    var item = this.closest('.mllc-suggestion-item');
                    var lang = item.dataset.lang;
                    var id   = item.dataset.id;
                    var title = item.querySelector('.mllc-suggestion-title').textContent;

                    var hidden  = document.getElementById('mllc_pair_' + lang);
                    var selected= document.getElementById('mllc_selected_' + lang);
                    var selTitle= document.getElementById('mllc_pair_title_' + lang);
                    var wrap    = document.getElementById('mllc_search_wrap_' + lang);
                    var create  = document.getElementById('mllc_create_' + lang);

                    hidden.value = id;
                    selTitle.textContent = title;
                    selected.style.display = 'flex';
                    wrap.style.display = 'none';
                    if(create) create.style.display = 'none';

                    item.style.opacity = '0.4';
                    this.disabled = true;
                    this.textContent = '✓';
                });
            });
        })();
        </script>
        <?php
    }

    /* ================================================================
       SAVE META
    ================================================================ */

    public function save_meta( $post_id, $post ) {
        if ( ! isset( $_POST[ MLLC_NONCE_FIELD ] ) ) return;
        if ( ! wp_verify_nonce( $_POST[ MLLC_NONCE_FIELD ], MLLC_NONCE_KEY ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $this_lang = sanitize_text_field( $_POST['mllc_this_lang'] ?? '' );
        update_post_meta( $post_id, '_mllc_this_lang', $this_lang );
        update_post_meta( $post_id, '_ll_this_lang',   $this_lang );

        $pairs = $_POST['mllc_pair'] ?? [];
        if ( ! is_array( $pairs ) ) $pairs = [];

        $langs = $this->get_langs();

        foreach ( $langs as $lang ) {
            if ( $lang === $this_lang ) continue;
            $alt_id = (int) ( $pairs[ $lang ] ?? 0 );

            update_post_meta( $post_id, $this->pair_meta_key( $lang ), $alt_id );

            if ( count( $langs ) === 2 ) {
                update_post_meta( $post_id, '_ll_alt_page_id', $alt_id );
            }

            if ( $alt_id ) {
                update_post_meta( $alt_id, '_mllc_this_lang',             $lang );
                update_post_meta( $alt_id, '_ll_this_lang',                $lang );
                update_post_meta( $alt_id, $this->pair_meta_key( $this_lang ), $post_id );

                if ( count( $langs ) === 2 ) {
                    update_post_meta( $alt_id, '_ll_alt_page_id', $post_id );
                }

                if ( ! $this->doing_parent_update ) {
                    $this->doing_parent_update = true;
                    $this->auto_set_parent( $alt_id, $lang );
                    $this->doing_parent_update = false;
                }
            }
        }

        if ( $this_lang && ! $this->doing_parent_update ) {
            $this->doing_parent_update = true;
            $this->auto_set_parent( $post_id, $this_lang );
            $this->doing_parent_update = false;
        }

        $has_all_pairs = ! empty( $pairs ) && count( array_filter( array_map( 'intval', $pairs ) ) ) > 0;
        if ( $post->post_status === 'publish' && ! $has_all_pairs && $this_lang ) {
            set_transient( MLLC_TRANSIENT . $post_id, true, 30 );
        }
    }

    /* ================================================================
       AUTO SET PARENT
    ================================================================ */

    private function auto_set_parent( int $post_id, string $lang ) {
        $active = $this->get_langs();
        if ( ! in_array( $lang, $active, true ) ) return;

        $parent = get_page_by_path( $lang );
        if ( ! $parent ) {
            $parent_id = wp_insert_post( [
                'post_title'   => strtoupper( $lang ),
                'post_name'    => $lang,
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => '',
            ] );
            if ( is_wp_error( $parent_id ) ) return;
            $parent = get_post( $parent_id );
        }

        if ( ! $parent || $parent->ID === $post_id ) return;

        $current = get_post( $post_id );
        if ( ! $current ) return;
        if ( (int) $current->post_parent === (int) $parent->ID ) return;

        remove_action( 'save_post', [ $this, 'save_meta' ], 10 );
        wp_update_post( [
            'ID'          => $post_id,
            'post_parent' => $parent->ID,
        ] );
        add_action( 'save_post', [ $this, 'save_meta' ], 10, 2 );

        flush_rewrite_rules( false );
    }

    /* ================================================================
       ADMIN NOTICE
    ================================================================ */

    public function admin_notice() {
        $screen = get_current_screen();
        if ( ! in_array( $screen->base, [ 'post', 'page' ], true ) ) return;

        global $post;
        if ( ! $post ) return;

        if ( get_transient( MLLC_TRANSIENT . $post->ID ) ) {
            delete_transient( MLLC_TRANSIENT . $post->ID );
            $label     = esc_html( $this->get_label() );
            $this_lang = $this->get_this_lang( $post->ID );
            $langs     = $this->get_langs();
            $missing   = [];
            foreach ( $langs as $l ) {
                if ( $l === $this_lang ) continue;
                if ( ! $this->get_pair_id( $post->ID, $l ) ) $missing[] = strtoupper( $l );
            }
            if ( $missing ) {
                echo '<div class="notice notice-warning is-dismissible" style="border-left-color:#2271b1;padding:12px 16px;">
                    <strong>⚠️ ' . $label . ' — ' . esc_html( strtoupper( $this_lang ) ) . ' sayfası yayınlandı ama şu dillerde eşleşme yok: ' . esc_html( implode( ', ', $missing ) ) . '</strong>
                </div>';
            }
        }
    }

    /* ================================================================
       DASHBOARD WIDGET
    ================================================================ */

    public function dashboard_widget() {
        wp_add_dashboard_widget(
            'mllc_lang_status',
            '🌐 ' . $this->get_label() . ' — Dil Çifti Durumu',
            [ $this, 'dashboard_widget_html' ]
        );
    }

    public function dashboard_widget_html() {
        $langs     = $this->get_langs();
        $all_posts = get_posts( [
            'post_type'   => $this->get_supported_post_types(),
            'post_status' => 'publish',
            'numberposts' => -1,
        ] );

        $fully_paired = 0;
        $partial      = [];
        $orphans      = [];
        $no_lang      = [];

        foreach ( $all_posts as $p ) {
            $lang = $this->get_this_lang( $p->ID );
            if ( ! $lang ) { $no_lang[] = $p; continue; }

            $total   = count( $langs ) - 1;
            $paired  = 0;
            $missing = [];

            foreach ( $langs as $l ) {
                if ( $l === $lang ) continue;
                if ( $this->get_pair_id( $p->ID, $l ) ) {
                    $paired++;
                } else {
                    $missing[] = strtoupper( $l );
                }
            }

            if ( $paired === $total ) {
                $fully_paired++;
            } elseif ( $paired > 0 ) {
                $partial[] = [ 'post' => $p, 'lang' => $lang, 'missing' => $missing ];
            } else {
                $orphans[] = [ 'post' => $p, 'lang' => $lang, 'missing' => $missing ];
            }
        }

        $pair_count = (int) ( $fully_paired / count( $langs ) );
        ?>
        <style>
            .mllc-dash-row { display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px solid #f0f0f0; }
            .mllc-dash-row:last-child { border-bottom:none; }
            .mllc-dash-title a { color:#1d2327; text-decoration:none; font-size:13px; }
            .mllc-dash-title a:hover { color:#2271b1; }
            .mllc-dash-badge { font-size:11px; font-weight:700; padding:2px 8px; border-radius:10px; background:#e8f0fe; color:#1a73e8; }
            .mllc-dash-missing { font-size:11px; color:#E91E8C; font-weight:600; }
        </style>
        <div style="display:flex;gap:12px;margin-bottom:14px;">
            <div style="flex:1;background:#f0faf0;border-radius:8px;padding:10px;text-align:center;">
                <div style="font-size:22px;font-weight:800;color:#46b450;"><?php echo $pair_count; ?></div>
                <div style="font-size:11px;color:#555;">Tam çift</div>
            </div>
            <div style="flex:1;background:<?php echo $partial ? '#fff8e8' : '#f0faf0'; ?>;border-radius:8px;padding:10px;text-align:center;">
                <div style="font-size:22px;font-weight:800;color:<?php echo $partial ? '#f0a500' : '#46b450'; ?>;"><?php echo count( $partial ); ?></div>
                <div style="font-size:11px;color:#555;">Kısmi</div>
            </div>
            <div style="flex:1;background:<?php echo $orphans ? '#fff5f8' : '#f0faf0'; ?>;border-radius:8px;padding:10px;text-align:center;">
                <div style="font-size:22px;font-weight:800;color:<?php echo $orphans ? '#E91E8C' : '#46b450'; ?>;"><?php echo count( $orphans ); ?></div>
                <div style="font-size:11px;color:#555;">Eşsiz</div>
            </div>
            <div style="flex:1;background:<?php echo $no_lang ? '#fff8e8' : '#f0faf0'; ?>;border-radius:8px;padding:10px;text-align:center;">
                <div style="font-size:22px;font-weight:800;color:<?php echo $no_lang ? '#f0a500' : '#46b450'; ?>;"><?php echo count( $no_lang ); ?></div>
                <div style="font-size:11px;color:#555;">Dil yok</div>
            </div>
        </div>

        <?php if ( $orphans || $partial ) : ?>
            <p style="font-weight:700;font-size:12px;color:#E91E8C;margin:0 0 8px;">⚠️ Eksik çeviriler:</p>
            <?php foreach ( array_merge( $orphans, $partial ) as $item ) :
                $p = $item['post']; ?>
                <div class="mllc-dash-row">
                    <div class="mllc-dash-title">
                        <a href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>"><?php echo esc_html( $p->post_title ); ?></a>
                    </div>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <span class="mllc-dash-badge"><?php echo esc_html( strtoupper( $item['lang'] ) ); ?></span>
                        <span class="mllc-dash-missing">-<?php echo esc_html( implode( ',-', $item['missing'] ) ); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ( $no_lang ) : ?>
            <p style="font-weight:700;font-size:12px;color:#f0a500;margin:12px 0 8px;">— Dil tanımlanmamış (<?php echo count( $no_lang ); ?>):</p>
            <?php foreach ( array_slice( $no_lang, 0, 5 ) as $p ) : ?>
                <div class="mllc-dash-row">
                    <div class="mllc-dash-title">
                        <a href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>"><?php echo esc_html( $p->post_title ); ?></a>
                    </div>
                    <span style="font-size:11px;color:#999;">Dil seçilmemiş</span>
                </div>
            <?php endforeach; ?>
            <?php if ( count( $no_lang ) > 5 ) : ?>
                <p style="font-size:11px;color:#999;margin:6px 0 0;">...ve <?php echo count( $no_lang ) - 5; ?> sayfa daha.</p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ( ! $orphans && ! $partial && ! $no_lang ) : ?>
            <p style="color:#46b450;font-weight:600;text-align:center;padding:10px 0;">✅ Tüm sayfalar tam eşleştirilmiş!</p>
        <?php endif; ?>
        <?php
    }

    /* ================================================================
       YENİ SAYFA OTO DOLDUR (+ Lang sayfası oluştur butonundan)
    ================================================================ */

    public function quick_fill_new_page() {
        if ( ! isset( $_GET['mllc_pair_with'] ) ) return;

        $pair_with = (int) $_GET['mllc_pair_with'];
        $lang      = sanitize_key( $_GET['mllc_lang'] ?? '' );
        $active    = $this->get_langs();

        if ( ! $pair_with || ! in_array( $lang, $active, true ) ) return;

        $pair_title = get_the_title( $pair_with );
        $lang_label = $this->lang_label( $lang );
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            var langSel = document.querySelector('select[name="mllc_this_lang"]');
            if(langSel) langSel.value = '<?php echo esc_js( $lang ); ?>';

            var wrap = document.getElementById('wpbody-content');
            if(wrap){
                var note = document.createElement('div');
                note.className = 'notice notice-info';
                note.style.borderLeftColor = '#2271b1';
                note.innerHTML = '<p>💡 Bu sayfa <strong><?php echo esc_js( $pair_title ); ?></strong> sayfasının <strong><?php echo esc_js( $lang_label ); ?></strong> karşılığı olarak oluşturuluyor. Dil eşleşmesi otomatik ayarlandı.</p>';
                wrap.insertBefore(note, wrap.firstChild);
            }
        });
        </script>
        <?php

        add_action( 'save_post', function( $post_id, $post ) use ( $pair_with, $lang ) {
            if ( get_post_meta( $post_id, '_mllc_this_lang', true ) ) return;
            if ( $post->post_status === 'auto-draft' ) return;

            $pair_lang = $this->get_this_lang( $pair_with );

            update_post_meta( $post_id, '_mllc_this_lang',                   $lang );
            update_post_meta( $post_id, '_ll_this_lang',                      $lang );
            update_post_meta( $post_id, $this->pair_meta_key( $pair_lang ),   $pair_with );

            update_post_meta( $pair_with, $this->pair_meta_key( $lang ), $post_id );
            update_post_meta( $pair_with, '_ll_alt_page_id', $post_id );
        }, 5, 2 );
    }

    /* ================================================================
       MENU YÖNETİMİ — WordPress Native Menü Kullanımı
       Her dil için ayrı bir WP menüsü oluşturulur.
       İsim formatı: "Header RU", "Header EN", "Header DE" vb.
       Plugin: belirtilen dilin menüsünü bulur, yoksa default'a düşer.
    ================================================================ */

    public function get_wp_menu_for_lang( string $lang ): ?WP_Post {
        $candidates = [
            'Header ' . strtoupper( $lang ),
            'header-' . $lang,
            'header ' . $lang,
            'Header ' . strtoupper( $lang ) . ' Menu',
            'Menu '  . strtoupper( $lang ),
            'menu-'  . $lang,
            'menu '  . $lang,
        ];

        $all_menus = wp_get_nav_menus();
        if ( empty( $all_menus ) ) return null;

        foreach ( $all_menus as $m ) {
            foreach ( $candidates as $needle ) {
                if ( strcasecmp( $m->name, $needle ) === 0 ) return $m;
            }
        }

        $slug_match = null;
        foreach ( $all_menus as $m ) {
            if ( strcasecmp( $m->slug, 'header-' . $lang ) === 0 || strcasecmp( $m->slug, 'menu-' . $lang ) === 0 ) {
                $slug_match = $m;
                break;
            }
        }
        if ( $slug_match ) return $slug_match;

        return $all_menus[0];
    }

    public function get_wp_menu_items_for_lang( string $lang ): array {
        $menu = $this->get_wp_menu_for_lang( $lang );
        if ( ! $menu ) return [];

        $items = wp_get_nav_menu_items( $menu->term_id );
        if ( ! is_array( $items ) ) return [];

        return $this->treeify_menu_items( $items );
    }

    private function treeify_menu_items( array $items ): array {
        $by_id    = [];
        $children = [];
        foreach ( $items as $it ) {
            $node = [
                'title'    => $it->title,
                'url'      => $it->url,
                'target'   => ( ! empty( $it->target ) && $it->target === '_blank' ) ? '_blank' : '',
                'classes'  => implode( ' ', array_filter( array_map( 'trim', (array) ( $it->classes ?? [] ) ) ) ),
                'children' => [],
            ];
            $by_id[ $it->ID ] = $node;
            $children[ (int) $it->menu_item_parent ][] = $it->ID;
        }

        $build = function( $parent_id ) use ( &$build, &$by_id, $children ) {
            $out = [];
            foreach ( $children[ $parent_id ] ?? [] as $id ) {
                if ( ! isset( $by_id[ $id ] ) ) continue;
                $by_id[ $id ]['children'] = $build( $id );
                $out[] = &$by_id[ $id ];
            }
            return $out;
        };

        return $build( 0 );
    }

    public function render_menus_tab() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $langs   = $this->get_langs();
        $default = $this->get_default_lang();
        ?>
        <div class="mllc-settings-card">
            <h2>📋 Dil Bazlı Menü Yönetimi (WordPress Native)</h2>
            <p class="mllc-field" style="color:#555;">
                Her dil için WordPress'te ayrı bir menü oluşturmanız yeterli. Plugin otomatik olarak doğru menüyü seçer. İsimlendirme: <code>Header RU</code>, <code>Header EN</code>, <code>Header DE</code>…
            </p>

            <h3 style="font-size:13px; margin:18px 0 8px;">📍 Aktif Diller İçin WP Menü Eşleşmesi</h3>
            <table class="widefat striped" style="max-width:720px;">
                <thead>
                    <tr>
                        <th>Dil</th>
                        <th>Beklenen Menü Adı</th>
                        <th>WP Menüsü</th>
                        <th>Öğe Sayısı</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $langs as $l ) :
                    $expected = 'Header ' . strtoupper( $l );
                    $menu     = $this->get_wp_menu_for_lang( $l );
                    $count    = $menu ? count( wp_get_nav_menu_items( $menu->term_id ) ?: [] ) : 0;
                    $is_fallback = $menu && strcasecmp( $menu->name, $expected ) !== 0;
                    ?>
                    <tr>
                        <td><code><?php echo esc_html( $l ); ?></code> — <?php echo esc_html( $this->lang_label( $l ) ); ?></td>
                        <td><code><?php echo esc_html( $expected ); ?></code></td>
                        <td>
                            <?php if ( $menu ) : ?>
                                <strong style="color:#46b450;"><?php echo esc_html( $menu->name ); ?></strong>
                                <?php if ( $is_fallback ) : ?>
                                    <br><span style="color:#f0a500; font-size:11px;">⚠️ Fallback menü kullanılıyor</span>
                                <?php endif; ?>
                            <?php else : ?>
                                <span style="color:#E91E8C;">❌ Menü bulunamadı</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo (int) $count; ?></td>
                        <td>
                            <?php if ( $menu ) : ?>
                                <a href="<?php echo esc_url( admin_url( 'nav-menus.php?action=edit&menu=' . $menu->term_id ) ); ?>" class="button button-small">✏️ Düzenle</a>
                            <?php else : ?>
                                <a href="<?php echo esc_url( admin_url( 'nav-menus.php?action=edit&menu=0' ) ); ?>" class="button button-small button-primary">+ Yeni Oluştur</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div class="mllc-shortcode-hint" style="margin-top:18px;">
                <strong>Nasıl Çalışır:</strong><br>
                1. <a href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>">Görünüm → Menüler</a>'e gidin<br>
                2. Yukarıdaki tabloda listelenen isimlerle (<code>Header RU</code>, <code>Header EN</code>…) menüler oluşturun<br>
                3. Menü öğelerini (sayfa, özel link, kategori) ekleyin ve sıralayın<br>
                4. Plugin <code>header.html</code> / <code>header-en.html</code>'in MENU_API'sine otomatik doğru menüyü döndürür
            </div>

            <hr style="margin:24px 0; border:none; border-top:1px solid #eee;">

            <h3 style="font-size:13px; margin:0 0 10px;">📡 JSON Endpoint (header'ın MENU_API'si için)</h3>
            <p style="font-size:12px; color:#666; margin:0 0 8px;">
                <code>header.html</code> / <code>header-en.html</code> zaten <code>?lang=ru</code> parametresiyle bu endpoint'i çağırıyor. WordPress menüsündeki öğeleri JSON formatında döndürür.
            </p>
            <code style="display:block; background:#1d2327; color:#9ed1ff; padding:10px 12px; border-radius:5px; font-size:12px; word-break:break-all; margin-bottom:8px;">
                <?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>?action=mllc_get_menu_json&lang=ru
            </code>
            <p style="font-size:12px; color:#666; margin:0 0 6px;">
                🔍 <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=mllc_get_menu_json&lang=ru' ) ); ?>" target="_blank">Test et (yeni sekme)</a> — Tarayıcıda JSON çıktısını gör.
            </p>
        </div>
        <?php
    }

    public function render_status_tab() {
        $langs   = $this->get_langs();
        $default = $this->get_default_lang();
        ?>
        <div class="mllc-settings-card">
            <h2>📊 Aktif Dil Durumu</h2>
            <table class="widefat striped" style="max-width:500px;">
                <thead><tr><th>Kod</th><th>Etiket</th><th>x-default</th></tr></thead>
                <tbody>
                <?php foreach ( $langs as $l ) : ?>
                    <tr>
                        <td><code><?php echo esc_html( $l ); ?></code></td>
                        <td><?php echo esc_html( $this->lang_label( $l ) ); ?></td>
                        <td><?php echo $l === $default ? '✅' : '—'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function shortcode_menu( $atts ) {
        $atts = shortcode_atts( [
            'lang'       => 'auto',
            'wrap'       => 'ul',
            'wrap_class' => 'mllc-menu',
            'item_wrap'  => 'li',
        ], $atts, 'mllc_menu' );

        $lang = $atts['lang'];
        if ( $lang === 'auto' ) {
            if ( is_singular() ) {
                $pid  = get_the_ID();
                $lang = $this->get_this_lang( $pid );
            }
            if ( ! $lang ) {
                $path  = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ), '/' );
                $first = explode( '/', $path )[0] ?? '';
                $lang  = in_array( $first, $this->get_langs(), true ) ? $first : $this->get_default_lang();
            }
        }
        $lang = sanitize_key( $lang );

        $items = $this->get_wp_menu_items_for_lang( $lang );
        if ( empty( $items ) ) return '';

        $out  = '<' . esc_attr( $atts['wrap'] ) . ' class="' . esc_attr( $atts['wrap_class'] ) . '">';
        $out .= $this->render_menu_tree( $items, $atts['item_wrap'] );
        $out .= '</' . esc_attr( $atts['wrap'] ) . '>';
        return $out;
    }

    private function render_menu_tree( array $items, string $item_wrap ): string {
        $out = '';
        foreach ( $items as $it ) {
            $title  = esc_html( $it['title'] );
            $url    = esc_url( $it['url'] );
            $target = $it['target'] === '_blank' ? ' target="_blank" rel="noopener"' : '';
            $class  = $it['classes'] ? ' class="' . esc_attr( $it['classes'] ) . '"' : '';
            $has_kids = ! empty( $it['children'] );
            $out .= '<' . esc_attr( $item_wrap ) . '>';
            $out .= '<a href="' . $url . '"' . $target . $class . '>' . $title . '</a>';
            if ( $has_kids ) {
                $out .= '<' . esc_attr( $item_wrap ) . ' class="sub-menu">' . $this->render_menu_tree( $it['children'], $item_wrap ) . '</' . esc_attr( $item_wrap ) . '>';
            }
            $out .= '</' . esc_attr( $item_wrap ) . '>';
        }
        return $out;
    }

    public function shortcode_lang_switch( $atts ) {
        $atts = shortcode_atts( [
            'class'      => 'mllc-lang-switch',
            'show_flags' => '1',
        ], $atts, 'mllc_lang_switch' );

        $langs    = $this->get_langs();
        $current  = $this->get_default_lang();
        if ( is_singular() ) {
            $cur = $this->get_this_lang( get_the_ID() );
            if ( $cur ) $current = $cur;
        } else {
            $path  = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ), '/' );
            $first = explode( '/', $path )[0] ?? '';
            if ( in_array( $first, $langs, true ) ) $current = $first;
        }

        $out = '<ul class="' . esc_attr( $atts['class'] ) . '">';
        foreach ( $langs as $l ) {
            $label  = $atts['show_flags'] === '1' ? $this->lang_label( $l ) : strtoupper( $l );
            $active = $l === $current ? ' class="active"' : '';
            $url    = home_url( '/' . $l . '/' );
            $out   .= '<li' . $active . '><a href="' . esc_url( $url ) . '" hreflang="' . esc_attr( $l ) . '">' . esc_html( $label ) . '</a></li>';
        }
        $out .= '</ul>';
        return $out;
    }

    public function ajax_get_menu_json() {
        $lang    = isset( $_GET['lang'] ) ? sanitize_key( $_GET['lang'] ) : '';
        $menu    = $lang ? $this->get_wp_menu_for_lang( $lang ) : null;
        $items   = $lang ? $this->get_wp_menu_items_for_lang( $lang ) : [];

        nocache_headers();
        header( 'Access-Control-Allow-Origin: *' );
        header( 'Content-Type: application/json; charset=utf-8' );
        status_header( 200 );

        $debug = [
            'lang_requested' => $lang,
            'menu_found'     => $menu ? true : false,
            'menu_name'      => $menu ? $menu->name : null,
            'menu_id'        => $menu ? (int) $menu->term_id : 0,
            'items_count'    => count( $items ),
            'all_menus'      => array_map( fn( $m ) => $m->name . ' (slug: ' . $m->slug . ')', wp_get_nav_menus() ?: [] ),
        ];

        $response = $debug;
        $response['items'] = $items;
        $response['count'] = count( $items );

        echo wp_json_encode( $response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[MultiLingo] Menu JSON for lang=' . $lang . ' menu=' . ( $menu ? $menu->name : 'NONE' ) . ' items=' . count( $items ) );
        }

        wp_die();
    }
}

new MultiLingo_Lang_Checker();
