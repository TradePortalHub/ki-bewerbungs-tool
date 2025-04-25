<?php
// file: includes/class-settings.php
defined( 'ABSPATH' ) || exit;

class KIBT_Settings {
    const OPTION_API_KEY    = 'kibt_api_key';
    const OPTION_JSON_SRC   = 'kibt_json_source';

    public function __construct() {
        add_action( 'admin_menu',    [ $this, 'add_settings_page' ] );
        add_action( 'admin_init',    [ $this, 'register_settings' ] );
    }

    /**
     * Menüpunkt unter „Einstellungen“ anlegen
     */
    public function add_settings_page() {
        add_options_page(
            'KI BewerbungsTool Settings',
            'KI BewerbungsTool',
            'manage_options',
            'kibt-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Registriert die beiden Optionen
     */
    public function register_settings() {
        register_setting(
            'kibt_settings_group',
            self::OPTION_API_KEY,
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            ]
        );
        register_setting(
            'kibt_settings_group',
            self::OPTION_JSON_SRC,
            [
                'type'              => 'string',
                'sanitize_callback' => 'wp_kses_post',
                'default'           => '',
            ]
        );
    }

    /**
     * Rendert das Settings‑Formular
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>KI BewerbungsTool Einstellungen</h1>
            <form method="post" action="options.php">
                <?php
                    settings_fields( 'kibt_settings_group' );
                    do_settings_sections( 'kibt-settings' );
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="<?php echo self::OPTION_API_KEY; ?>">OpenAI API Key</label></th>
                        <td>
                            <input
                                type="text"
                                id="<?php echo self::OPTION_API_KEY; ?>"
                                name="<?php echo self::OPTION_API_KEY; ?>"
                                value="<?php echo esc_attr( get_option( self::OPTION_API_KEY ) ); ?>"
                                size="50"
                            />
                            <p class="description">Dein <code>sk-…</code> Key von OpenAI</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo self::OPTION_JSON_SRC; ?>">JSON‑Quelle</label></th>
                        <td>
                            <textarea
                                id="<?php echo self::OPTION_JSON_SRC; ?>"
                                name="<?php echo self::OPTION_JSON_SRC; ?>"
                                rows="8"
                                cols="50"
                            ><?php echo esc_textarea( get_option( self::OPTION_JSON_SRC ) ); ?></textarea>
                            <p class="description">
                                Entweder eine URL (http(s)://…/config.json) oder reiner JSON‑Text,<br>
                                der mindestens <code>{"postings":[…]}</code> enthält.
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

// Initialisierung
new KIBT_Settings();
