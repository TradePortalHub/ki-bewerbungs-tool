<?php
// file: includes/class-uploads-handler.php
defined( 'ABSPATH' ) || exit;

class KIBT_Uploads_Handler {
    const UPLOAD_DIR      = 'kibt-applications';
    const APPLICATIONS_FILE = 'applications.json';

    public function __construct() {
        // REST‑Route für applications
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
        // Stelle sicher, dass WP‐Uploads‑Verzeichnis vorhanden ist
        add_action( 'init', [ $this, 'ensure_upload_dir' ] );
    }

    /**
     * Legt das Upload‑Verzeichnis an, falls nicht existent.
     */
    public function ensure_upload_dir() {
        $upload = wp_upload_dir();
        $dir = trailingslashit( $upload['basedir'] ) . self::UPLOAD_DIR;
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
        }
    }

    /**
     * Registriert die REST‑Routen fürs Speichern einer Bewerbung.
     */
    public function register_rest_routes() {
        register_rest_route( 'ki-bewerbung/v1', '/applications', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_save_application' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'jobId'    => [ 'required' => true, 'type' => 'string' ],
                'messages' => [ 'required' => true, 'type' => 'array' ],
                // fileUploads-Objekte werden separat per FormData erwartet
            ],
        ] );
    }

    /**
     * Callback zum Speichern einer Bewerbung.
     * Erwartet JSON‑Body mit { jobId, messages } und multipart/form-data mit Dateien.
     */
    public function handle_save_application( \WP_REST_Request $request ) {
        $params    = $request->get_json_params();
        $job_id    = sanitize_text_field( $params['jobId'] );
        $messages  = $params['messages'];
        $files     = $request->get_file_params();

        $upload_dir = wp_upload_dir();
        $base_dir   = trailingslashit( $upload_dir['basedir'] ) . self::UPLOAD_DIR;
        $timestamp  = time();
        $app_id     = $job_id . '-' . $timestamp;
        $app_dir    = "{$base_dir}/{$app_id}";

        // 1) Bewerbung‑Unterordner anlegen
        if ( ! file_exists( $app_dir ) ) {
            wp_mkdir_p( $app_dir );
        }

        // 2) Dateien speichern (Lebenslauf, Zertifikate, …)
        $saved_files = [];
        foreach ( $files as $field_name => $file_array ) {
            $move = wp_handle_upload( $file_array, [ 'test_form' => false ] );
            if ( isset( $move['file'] ) ) {
                // in Unterordner verschieben
                $dest = "{$app_dir}/" . basename( $move['file'] );
                rename( $move['file'], $dest );
                $saved_files[ $field_name ] = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $dest );
            }
        }

        // 3) Bewerber‑Daten zusammenstellen
        $entry = [
            'applicationId' => $app_id,
            'jobId'         => $job_id,
            'messages'      => $messages,
            'files'         => $saved_files,
            'submittedAt'   => date( DATE_ISO8601, $timestamp ),
        ];

        // 4) In zentrale JSON‑Datei schreiben
        $apps_file = "{$base_dir}/" . self::APPLICATIONS_FILE;
        $all_apps   = [];
        if ( file_exists( $apps_file ) ) {
            $all_apps = json_decode( file_get_contents( $apps_file ), true ) ?: [];
        }
        $all_apps[] = $entry;
        file_put_contents( $apps_file, wp_json_encode( $all_apps, JSON_PRETTY_PRINT ) );

        // 5) Response
        return rest_ensure_response( [
            'success'       => true,
            'applicationId' => $app_id,
            'files'         => $saved_files,
        ] );
    }
}

// Initialisierung
new KIBT_Uploads_Handler();
