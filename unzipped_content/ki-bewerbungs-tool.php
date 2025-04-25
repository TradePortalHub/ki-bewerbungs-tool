<?php
/**
 * Plugin Name: KI BewerbungsTool
 * Description: Mehr-Firmen-Bewerbungssystem mit Chat-Frontend
 * Version:     2.0.0
 * Author:      CoreTech Software Solutions
 * Text Domain: ki-bewerbungstool
 */
if ( ! defined( 'ABSPATH' ) ) exit;

//-----------------------------------------
// 1. Konstanten
//-----------------------------------------
define( 'KIBT_DIR', plugin_dir_path( __FILE__ ) );
define( 'KIBT_URL', plugin_dir_url( __FILE__ ) );

//-----------------------------------------
// 2. Custom Post Type „Firmen“
//-----------------------------------------
add_action( 'init', function() {
    $labels = [
        'name'               => 'Firmen',
        'singular_name'      => 'Firma',
        'menu_name'          => 'KI BewerbungsTool → Firmen',
        'name_admin_bar'     => 'Firma',
        'add_new'            => 'Firma hinzufügen',
        'add_new_item'       => 'Neue Firma hinzufügen',
        'edit_item'          => 'Firma bearbeiten',
        'new_item'           => 'Neue Firma',
        'view_item'          => 'Firma ansehen',
        'all_items'          => 'Alle Firmen',
    ];
    register_post_type( 'kibt_company', [
        'labels'             => $labels,
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'menu_position'      => 58,
        'menu_icon'          => 'dashicons-building',
        'supports'           => [ 'title' ],
    ] );
});

//-----------------------------------------
// 3. Meta-Boxen für Firmen-Einstellungen
//-----------------------------------------
add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'kibt_company_settings',
        'Firmen-Einstellungen',
        function( $post ) {
            $opts = get_post_meta( $post->ID, '_kibt_company', true ) ?: [];
            $fields = [
                'company_name'  => [ 'Firmenname',       'text' ],
                'company_logo'  => [ 'Logo-URL',         'url'  ],
                'job_feed_url'  => [ 'Stellen-Feed-URL', 'url'  ],
                'storage_url'   => [ 'Speicher-URL',     'url'  ],
            ];
            wp_nonce_field( 'kibt_save_company', 'kibt_company_nonce' );
            echo '<table class="form-table"><tbody>';
            foreach ( $fields as $key => list( $label, $type ) ) {
                $val = esc_attr( $opts[ $key ] ?? '' );
                echo "<tr><th><label for='{$key}'>{$label}</label></th><td>";
                printf(
                    "<input type='%s' id='%s' name='kibt_company[%s]' value='%s' class='regular-text' />",
                    esc_attr( $type ),
                    esc_attr( $key ),
                    esc_attr( $key ),
                    $val
                );
                echo '</td></tr>';
            }
            echo '</tbody></table>';
        },
        'kibt_company',
        'normal',
        'high'
    );
});
add_action( 'save_post_kibt_company', function( $post_id ) {
    if ( ! isset( $_POST['kibt_company_nonce'] ) ||
         ! wp_verify_nonce( $_POST['kibt_company_nonce'], 'kibt_save_company' ) ) {
        return;
    }
    if ( isset( $_POST['kibt_company'] ) && is_array( $_POST['kibt_company'] ) ) {
        $clean = array_map( 'sanitize_text_field', $_POST['kibt_company'] );
        update_post_meta( $post_id, '_kibt_company', $clean );
    }
});

//-----------------------------------------
// 4. REST-API: config & apply
//-----------------------------------------
add_action( 'rest_api_init', function() {
    // config/{slug}
    register_rest_route( 'kibt/v1', '/config/(?P<slug>[a-z0-9_-]+)', [
        'methods'  => 'GET',
        'callback' => function( $req ) {
            $slug = $req['slug'];
            $company = get_page_by_path( $slug, OBJECT, 'kibt_company' );
            if ( ! $company ) {
                return new WP_Error( 'no_company', 'Firma nicht gefunden', [ 'status'=>404 ] );
            }
            $opts = get_post_meta( $company->ID, '_kibt_company', true );
            // load remote job feed
            $feed = wp_remote_get( $opts['job_feed_url'] );
            $jobs = is_wp_error( $feed ) ? [] : json_decode( wp_remote_retrieve_body( $feed ), true );
            return [
                'company_slug' => $slug,
                'company_opts' => $opts,
                'jobs'         => $jobs ?: [],
            ];
        },
        'permission_callback' => '__return_true'
    ] );
    // apply/{slug}
    register_rest_route( 'kibt/v1', '/apply/(?P<slug>[a-z0-9_-]+)', [
        'methods'  => 'POST',
        'callback' => function( $req ) {
            $slug = $req['slug'];
            $company = get_page_by_path( $slug, OBJECT, 'kibt_company' );
            if ( ! $company ) {
                return new WP_Error( 'no_company', 'Firma nicht gefunden', [ 'status'=>404 ] );
            }
            $opts = get_post_meta( $company->ID, '_kibt_company', true );
            $body = json_encode( $req->get_json_params(), JSON_PRETTY_PRINT );
            $timestamp = date( 'Ymd-His' );
            $filename = "{$slug}-{$timestamp}.json";
            // map storage_url to server path
            $url = untrailingslashit( $opts['storage_url'] );
            $rel = str_replace( KIBT_URL, '', $url );
            $path = KIBT_DIR . trim( $rel, '/' ) . '/' . $filename;
            wp_mkdir_p( dirname( $path ) );
            file_put_contents( $path, $body );
            return [ 'saved' => true, 'file' => $filename ];
        },
        'permission_callback' => function() {
            return current_user_can( 'edit_posts' );
        }
    ] );
});

//-----------------------------------------
// 5. Frontend: React-ESM & Settings
//-----------------------------------------
add_action( 'wp_enqueue_scripts', function () {

    // 1) WordPress-eigenes React abklemmen
    wp_dequeue_script( 'wp-element' );
    wp_deregister_script( 'wp-element' );

    // 2) ►► Hier kommt die Slug-Ermittlung + Settings-Injection
    if ( $slug = get_query_var( 'company' ) ?: (
             // Nur wenn die Seite den Shortcode enthält, sonst false
             ( has_shortcode( get_post()->post_content, 'ki_bewerbung' ) && isset( $_GET['company'] ) )
                 ? sanitize_title( $_GET['company'] )
                 : false
         ) ) {

        echo '<script>window.KIBT_SETTINGS = ' .
             wp_json_encode( [
                 'rest_base' => rest_url( 'kibt/v1/' ),
                 'nonce'     => wp_create_nonce( 'wp_rest' ),
                 'company'   => $slug,             // <-- Firmen-Slug
             ] ) .
             ';</script>';
    }

    // 3) React-Bundle & CSS laden
    wp_enqueue_script_module(
        'kibt-app',
        KIBT_URL . 'pages/_app.js',
        [],
        filemtime( KIBT_DIR . 'pages/_app.js' ),
        true
    );

    wp_enqueue_style(
        'kibt-globals',
        KIBT_URL . 'styles/globals.css',
        [],
        filemtime( KIBT_DIR . 'styles/globals.css' )
    );
}, 1 );

//-----------------------------------------
// 6. Shortcodes
//-----------------------------------------
// Chat-App: [ki_bewerbung company="slug"]
add_shortcode( 'ki_bewerbung', function( $atts ){
    $atts = shortcode_atts([ 'company'=> '' ], $atts );
    $slug = $atts['company'];
    $jobId = get_query_var( 'job_id' );
    if ( empty( $slug ) ) {
        $slug = get_query_var( 'company' );
    }
    if ( empty( $slug ) ) {
        $post = get_post();
        if ( $post && $post->post_type === 'kibt_company' ) {
            $slug = $post->post_name;
        }
    }
    return sprintf(
        '<div id="kibt-app" data-company="%s" data-job-id="%s"></div>',
        esc_attr( $slug ),
        esc_attr( $jobId )
      );
});

// ────────────────────────────────────────────────────────────
// 6. Shortcode: Job-Liste  [kibt_company_jobs slug="..."]
// ────────────────────────────────────────────────────────────
// Shortcode: Job-Liste [kibt_company_jobs]
add_shortcode( 'kibt_company_jobs', function ( $atts ) {
    /* ── Slug ─────────────────────────── */
    $atts = shortcode_atts( [ 'slug' => '' ], $atts, 'kibt_company_jobs' );
    $slug = $atts['slug'] ?: get_query_var( 'company' );
    if ( ! $slug && ( $p = get_post() ) && $p->post_type === 'kibt_company' ) {
        $slug = $p->post_name;
    }
    if ( ! $slug ) {
        return '<p>Kein Firmen-Slug.</p>';
    }

    /* ── REST intern ───────────────────── */
    $resp = rest_do_request( new WP_REST_Request( 'GET', "/kibt/v1/config/{$slug}" ) );
    if ( $resp->is_error() ) {
        return '<p>Firma oder Jobs nicht gefunden.</p>';
    }

    $data = $resp->get_data();
    
    // Sicherstellen, dass $data ein Array ist
    if (!is_array($data)) {
        return '<p>Ungültiges Datenformat erhalten.</p>';
    }
    
    // Jobs aus der richtigen Struktur extrahieren
    $jobs = [];
    if (isset($data['jobs']['postings']) && is_array($data['jobs']['postings'])) {
        $jobs = $data['jobs']['postings'];
    }

    if (empty($jobs)) {
        return '<p>Keine Stellenangebote verfügbar.</p>';
    }

    /* ── HTML Ausgabe ──────────────────── */
    $out = '<div class="kibt-job-list-container">';
    $out .= '<h3>Stellenangebote bei ' . esc_html($data['jobs']['companyName'] ?? '') . '</h3>';
    $out .= '<ul class="kibt-job-list">';
    
    foreach ($jobs as $job) {
        if (!is_array($job) || !isset($job['id']) || !isset($job['title'])) {
            continue;
        }
        
        $out .= sprintf(
            '<li><a href="?company=%s&amp;job_id=%s">%s</a><p>%s</p></li>',
            esc_attr($slug),
            esc_attr($job['id']),
            esc_html($job['title']),
            esc_html($job['description'] ?? '')
        );
    }
    
    $out .= '</ul>';
    $out .= '</div>';
    
    return $out;
});



//-----------------------------------------
// 7. Rewrite für company & job_id
//-----------------------------------------
add_action( 'init', function(){
    add_rewrite_tag( '%company%', '([^&]+)' );
    add_rewrite_tag( '%job_id%', '([^&]+)' );
    add_rewrite_rule( '^apply/([^/]+)/([^/]+)/?', 'index.php?company=$1&job_id=$2', 'top' );
    add_rewrite_rule( '^apply/([^/]+)/?', 'index.php?company=$1', 'top' );
});
//add_filter( 'query_vars', function( $v ){ $v[] = 'company'; return $v; });
add_filter( 'query_vars', function( $vars ){
    $vars[] = 'company';
    $vars[] = 'job_id';
    return $vars;
});

// disable canonical redirect for company and job_id
add_filter( 'redirect_canonical', function( $redirect_url, $requested_url ) {
    if ( get_query_var('company') || get_query_var('job_id') ) {
        return false;
    }
    return $redirect_url;
}, 10, 2 );


// flush on activate
register_activation_hook( __FILE__, function(){
    flush_rewrite_rules();
});
register_deactivation_hook( __FILE__, function(){
    flush_rewrite_rules();
});

// ────────────────────────────────────────────────────────────
// 8) Frontend-Settings-Formular für Firmen
//    Shortcode: [kibt_admin_settings company="slug"]
// ────────────────────────────────────────────────────────────
add_shortcode( 'kibt_admin_settings', function( $atts ) {
    // Nur Administratoren dürfen hier ran
    if ( ! current_user_can( 'manage_options' ) ) {
        return '<p>Zugriff verweigert.</p>';
    }

    // Attribute parsen & Fallback für slug
    $atts = shortcode_atts( [ 'company' => '' ], $atts, 'kibt_admin_settings' );
    $slug = sanitize_title( $atts['company'] );
    if ( empty( $slug ) ) {
        $slug = get_query_var( 'company' );
    }
    if ( empty( $slug ) ) {
        $post = get_post();
        if ( $post && $post->post_type === 'kibt_company' ) {
            $slug = $post->post_name;
        }
    }
    if ( empty( $slug ) ) {
        return '<p>Kein Firmen-Slug angegeben.</p>';
    }

    // Firma laden
    $company = get_page_by_path( $slug, OBJECT, 'kibt_company' );
    if ( ! $company ) {
        return '<p>Firma „' . esc_html( $slug ) . '“ nicht gefunden.</p>';
    }

    // Meta-Werte
    $meta   = get_post_meta( $company->ID, '_kibt_company', true );
    $fields = [
        'company_name' => [ 'Firmenname',       'text' ],
        'company_logo' => [ 'Logo-URL',         'url'  ],
        'job_feed_url' => [ 'Stellen-Feed-URL', 'url'  ],
        'storage_url'  => [ 'Speicher-URL',     'url'  ],
    ];

    $output = '<div class="kibt-admin-settings"><h2>Einstellungen für Firma: ' . esc_html( $company->post_title ) . '</h2>';
    $output .= '<form method="post">';
    wp_nonce_field( 'kibt_frontend_save', 'kibt_frontend_nonce' );

    // Ausgabe der Felder
    foreach ( $fields as $key => list( $label, $type ) ) {
        $value = isset( $meta[ $key ] ) ? esc_attr( $meta[ $key ] ) : '';
        $output .= '<p><label><strong>' . esc_html( $label ) . ':</strong><br>';
        $output .= '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $key ) . '" value="' . $value . '" class="regular-text" />';
        $output .= '</label></p>';
    }

    $output .= '<p><button type="submit" class="button button-primary">Speichern</button></p>';
    $output .= '</form>';

    // Speichern verarbeiten
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['kibt_frontend_nonce'] ) ) {
        if ( wp_verify_nonce( $_POST['kibt_frontend_nonce'], 'kibt_frontend_save' ) ) {
            $new = [];
            foreach ( array_keys( $fields ) as $key ) {
                if ( isset( $_POST[ $key ] ) ) {
                    $new[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
                }
            }
            update_post_meta( $company->ID, '_kibt_company', $new );
            $output .= '<div class="updated notice"><p>Einstellungen gespeichert.</p></div>';
        } else {
            $output .= '<div class="error notice"><p>Nonce-Fehler.</p></div>';
        }
    }

    // Tabelle der aktuellen Werte
    $meta = get_post_meta( $company->ID, '_kibt_company', true );
    $output .= '<h3>Derzeit gespeicherte Werte</h3><table class="widefat fixed striped"><thead><tr>';
    foreach ( $fields as $key => list( $label ) ) {
        $output .= '<th>' . esc_html( $label ) . '</th>';
    }
    $output .= '</tr></thead><tbody><tr>';
    foreach ( $fields as $key => $_ ) {
        $output .= '<td>' . esc_html( $meta[ $key ] ?? '' ) . '</td>';
    }
    $output .= '</tr></tbody></table></div>';

    return $output;
} );
