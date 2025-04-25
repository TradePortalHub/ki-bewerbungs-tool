<?php
// file: includes/class-rest-endpoints.php
defined( 'ABSPATH' ) || exit;

/**
 * Registriert alle WP‑REST‑Routes für das Bewerbungs‑Tool.
 */
add_action( 'rest_api_init', function() {
    // 1) /config → Firmen‑ und Posting‑Konfiguration
    register_rest_route( 'ki-bewerbung/v1', '/config', [
        'methods'             => 'GET',
        'callback'            => 'kibt_get_config',
        'permission_callback' => '__return_true',
    ] );

    // 2) /jobs → nur die Job‑Feeds (Array)
    register_rest_route( 'ki-bewerbung/v1', '/jobs', [
        'methods'             => 'GET',
        'callback'            => 'kibt_get_jobs',
        'permission_callback' => '__return_true',
    ] );

    // 3) /chat → Chat‑Endpoint (optional, wenn du KI‑Antworten greifen willst)
    register_rest_route( 'ki-bewerbung/v1', '/chat', [
        'methods'             => 'POST',
        'callback'            => 'kibt_handle_chat',
        'permission_callback' => '__return_true',
        'args'                => [
            'messages' => [ 'required' => true, 'type' => 'array' ],
            'jobId'    => [ 'required' => true, 'type' => 'string' ],
        ],
    ] );

    // 4) /applications → Speichert Bewerbungen
    register_rest_route( 'ki-bewerbung/v1', '/applications', [
        'methods'             => 'POST',
        'callback'            => 'kibt_save_application',
        'permission_callback' => '__return_true',
        'args'                => [
            'jobId'    => [ 'required' => true, 'type' => 'string' ],
            'messages' => [ 'required' => true, 'type' => 'array' ],
            // Dateien werden per FormData übertragen und vom Upload-Handler aufgenommen
        ],
    ] );
} );

/**
 * Holt die Option ‘kibt_json_source’ (URL oder roher JSON-Text) und decodiert sie.
 *
 * @return array
 */
function kibt_get_config() {
    // 1) Option-Inhalt lesen
    $json = get_option( 'kibt_json_source' );

    // 2) Fallback auf public/companies/config.json
    if ( empty( trim( $json ) ) ) {
        $file = KIBT_DIR . 'public/companies/config.json';
        if ( file_exists( $file ) ) {
            $json = file_get_contents( $file );
        }
    }

    // 3) URL oder roher JSON-Text?
    if ( filter_var( $json, FILTER_VALIDATE_URL ) ) {
        $resp = wp_remote_get( $json );
        if ( is_wp_error( $resp ) ) {
            return [];
        }
        $body = wp_remote_retrieve_body( $resp );
    } else {
        $body = $json;
    }

    // 4) Dekodieren
    return json_decode( $body, true ) ?: [];
}


/**
 * Gibt alle Job‑Einträge zurück.
 *
 * @return array
 */
function kibt_get_jobs() {
    $cfg  = kibt_get_config();
    $jobs = [];

    // a) erst Feld "postings"
    if ( isset( $cfg['postings'] ) && is_array( $cfg['postings'] ) ) {
        $jobs = $cfg['postings'];
    }
    // b) dann Feld "jobs"
    elseif ( isset( $cfg['jobs'] ) && is_array( $cfg['jobs'] ) ) {
        $jobs = $cfg['jobs'];
    }

    // c) Fallback aus public/companies/*.json
    if ( empty( $jobs ) ) {
        $dir = KIBT_DIR . 'public/companies/';
        if ( is_dir( $dir ) ) {
            foreach ( glob( $dir . '*.json' ) as $file ) {
                $data = json_decode( file_get_contents( $file ), true );
                if ( isset( $data['postings'] ) && is_array( $data['postings'] ) ) {
                    $jobs = array_merge( $jobs, $data['postings'] );
                } elseif ( isset( $data['jobs'] ) && is_array( $data['jobs'] ) ) {
                    $jobs = array_merge( $jobs, $data['jobs'] );
                } elseif ( isset( $data['id'], $data['title'] ) ) {
                    $jobs[] = $data;
                }
            }
        }
    }

    return $jobs;
}

/**
 * Platzhalter für KI‑Chat (falls du ChatGPT‑Integration brauchst).
 * Hier müsstest du deine OpenAI‑Logik implementieren.
 */
function kibt_handle_chat( \WP_REST_Request $request ) {
    $params   = $request->get_json_params();
    $messages = $params['messages'] ?? [];
    $jobId    = sanitize_text_field( $params['jobId'] ?? '' );

    // Hier könntest du z.B. OpenAI anrufen:
    // $apiKey = get_option('kibt_api_key');
    // … Anfrage zusammenbauen …

    // Platzhalter‑Antwort:
    return rest_ensure_response( [
        'message' => [
            'role'    => 'assistant',
            'content' => 'Dies ist eine Beispiel‑Antwort der KI.'
        ]
    ] );
}

/**
 * Speichert die Bewerbung (delegiert an Deinen Uploads‑Handler).
 */
function kibt_save_application( \WP_REST_Request $request ) {
    // Wenn Du class-uploads-handler.php korrekt hast, ruft diese
    // Funktion wp_handle_upload usw. auf.
    if ( function_exists( 'kibt_handle_save_application' ) ) {
        return kibt_handle_save_application( $request );
    }
    return rest_ensure_response( [
        'success' => false,
        'error'   => 'Uploads‑Handler nicht gefunden'
    ] );
}
