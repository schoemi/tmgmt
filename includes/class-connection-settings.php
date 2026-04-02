<?php
/**
 * IMAP/SMTP Connection Settings Page
 * 
 * Provides the settings page for IMAP and SMTP configuration,
 * password encryption/decryption, and static config helpers.
 */
if (!defined('ABSPATH')) exit;

class TMGMT_Connection_Settings {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Register the settings page as a submenu in the TMGMT settings menu.
     */
    public function add_settings_page() {
        add_submenu_page(
            'tmgmt-settings-hidden',
            'E-Mail Verbindung',
            'E-Mail Verbindung',
            'tmgmt_manage_settings',
            'tmgmt-connection-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register all WordPress options for IMAP/SMTP fields.
     */
    public function register_settings() {
        // IMAP Settings
        register_setting('tmgmt_connection_options', 'tmgmt_imap_host', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('tmgmt_connection_options', 'tmgmt_imap_port', array(
            'sanitize_callback' => 'absint',
            'default' => 993,
        ));
        register_setting('tmgmt_connection_options', 'tmgmt_imap_username', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('tmgmt_connection_options', 'tmgmt_imap_password', array(
            'sanitize_callback' => array($this, 'sanitize_imap_password'),
        ));
        register_setting('tmgmt_connection_options', 'tmgmt_imap_encryption', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'ssl',
        ));
        register_setting('tmgmt_connection_options', 'tmgmt_imap_folder', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'INBOX',
        ));
        register_setting('tmgmt_connection_options', 'tmgmt_imap_sent_folder', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'Sent',
        ));

        // SMTP Settings
        register_setting('tmgmt_connection_options', 'tmgmt_smtp_host', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('tmgmt_connection_options', 'tmgmt_smtp_port', array(
            'sanitize_callback' => 'absint',
            'default' => 587,
        ));
        register_setting('tmgmt_connection_options', 'tmgmt_smtp_username', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('tmgmt_connection_options', 'tmgmt_smtp_password', array(
            'sanitize_callback' => array($this, 'sanitize_smtp_password'),
        ));
        register_setting('tmgmt_connection_options', 'tmgmt_smtp_encryption', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'starttls',
        ));
        register_setting('tmgmt_connection_options', 'tmgmt_smtp_from_email', array(
            'sanitize_callback' => 'sanitize_email',
        ));
        register_setting('tmgmt_connection_options', 'tmgmt_smtp_from_name', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));

        // Assignment Rules
        register_setting('tmgmt_connection_options', 'tmgmt_event_id_search', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'both',
        ));
        register_setting('tmgmt_connection_options', 'tmgmt_event_id_pattern', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '[TMGMT-{EVENT_ID}]',
        ));
        register_setting('tmgmt_connection_options', 'tmgmt_imap_fetch_interval', array(
            'sanitize_callback' => 'absint',
            'default' => 5,
        ));
    }

    /**
     * Sanitize callback that encrypts the IMAP password before saving.
     * If empty, keeps the existing value.
     */
    public function sanitize_imap_password($value) {
        if (empty($value)) {
            return get_option('tmgmt_imap_password', '');
        }
        return self::encrypt($value);
    }

    /**
     * Sanitize callback that encrypts the SMTP password before saving.
     * If empty, keeps the existing value.
     */
    public function sanitize_smtp_password($value) {
        if (empty($value)) {
            return get_option('tmgmt_smtp_password', '');
        }
        return self::encrypt($value);
    }

    /**
     * Encrypt a value using AES-256-CBC with LOGGED_IN_KEY.
     */
    public static function encrypt(string $value): string {
        if (empty($value)) {
            return '';
        }

        $key = self::get_encryption_key();
        $cipher = 'aes-256-cbc';
        $iv_length = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($iv_length);

        $encrypted = openssl_encrypt($value, $cipher, $key, 0, $iv);
        if ($encrypted === false) {
            return $value; // Fallback to plaintext on encryption failure
        }

        return base64_encode($iv . '::' . $encrypted);
    }

    /**
     * Decrypt a value using AES-256-CBC with LOGGED_IN_KEY.
     */
    public static function decrypt(string $value): string {
        if (empty($value)) {
            return '';
        }

        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return $value; // Not encrypted, return as-is
        }

        $parts = explode('::', $decoded, 2);
        if (count($parts) !== 2) {
            return $value; // Not in expected format
        }

        $key = self::get_encryption_key();
        $cipher = 'aes-256-cbc';

        $decrypted = openssl_decrypt($parts[1], $cipher, $key, 0, $parts[0]);
        if ($decrypted === false) {
            return $value; // Decryption failed, return as-is
        }

        return $decrypted;
    }

    /**
     * Get the encryption key from WordPress config.
     */
    private static function get_encryption_key(): string {
        if (defined('LOGGED_IN_KEY') && LOGGED_IN_KEY) {
            return hash('sha256', LOGGED_IN_KEY, true);
        }
        return hash('sha256', 'tmgmt-fallback-key', true);
    }

    /**
     * Get decrypted IMAP configuration as an associative array.
     */
    public static function get_imap_config(): array {
        return array(
            'host'        => get_option('tmgmt_imap_host', ''),
            'port'        => (int) get_option('tmgmt_imap_port', 993),
            'username'    => get_option('tmgmt_imap_username', ''),
            'password'    => self::decrypt(get_option('tmgmt_imap_password', '')),
            'encryption'  => get_option('tmgmt_imap_encryption', 'ssl'),
            'folder'      => get_option('tmgmt_imap_folder', 'INBOX'),
            'sent_folder' => get_option('tmgmt_imap_sent_folder', 'Sent'),
        );
    }

    /**
     * Get decrypted SMTP configuration as an associative array.
     */
    public static function get_smtp_config(): array {
        return array(
            'host'       => get_option('tmgmt_smtp_host', ''),
            'port'       => (int) get_option('tmgmt_smtp_port', 587),
            'username'   => get_option('tmgmt_smtp_username', ''),
            'password'   => self::decrypt(get_option('tmgmt_smtp_password', '')),
            'encryption' => get_option('tmgmt_smtp_encryption', 'starttls'),
            'from_email' => get_option('tmgmt_smtp_from_email', ''),
            'from_name'  => get_option('tmgmt_smtp_from_name', ''),
        );
    }

    /**
     * Validate required fields for IMAP and SMTP configuration.
     * Returns an array of missing field labels, or empty array if valid.
     */
    public static function validate_settings(): array {
        $missing = array();

        $imap_required = array(
            'tmgmt_imap_host'     => 'IMAP-Host',
            'tmgmt_imap_username' => 'IMAP-Benutzername',
            'tmgmt_imap_password' => 'IMAP-Passwort',
        );

        $smtp_required = array(
            'tmgmt_smtp_host'       => 'SMTP-Host',
            'tmgmt_smtp_username'   => 'SMTP-Benutzername',
            'tmgmt_smtp_password'   => 'SMTP-Passwort',
            'tmgmt_smtp_from_email' => 'Absender-Adresse',
        );

        // Check text fields
        foreach (array_merge($imap_required, $smtp_required) as $key => $label) {
            $value = get_option($key, '');
            if (empty($value)) {
                $missing[] = $label;
            }
        }

        // Check port fields separately - they have defaults so only check if explicitly set to 0
        $imap_port = (int) get_option('tmgmt_imap_port', 993);
        if ($imap_port <= 0) {
            $missing[] = 'IMAP-Port';
        }

        $smtp_port = (int) get_option('tmgmt_smtp_port', 587);
        if ($smtp_port <= 0) {
            $missing[] = 'SMTP-Port';
        }

        return $missing;
    }

    /**
     * Render the connection settings page.
     */
    public function render_settings_page() {
        if (isset($_GET['settings-updated'])) {
            $missing = self::validate_settings();
            if (!empty($missing)) {
                add_settings_error(
                    'tmgmt_connection_messages',
                    'tmgmt_connection_incomplete',
                    sprintf('Fehlende Pflichtfelder: %s', implode(', ', $missing)),
                    'error'
                );
            } else {
                add_settings_error(
                    'tmgmt_connection_messages',
                    'tmgmt_connection_saved',
                    'Einstellungen gespeichert.',
                    'updated'
                );
            }
        }
        settings_errors('tmgmt_connection_messages');

        $imap_config = self::get_imap_config();
        $smtp_config = self::get_smtp_config();
        ?>
        <div class="wrap">
            <h1>E-Mail Verbindungseinstellungen</h1>
            <form method="post" action="options.php">
                <?php settings_fields('tmgmt_connection_options'); ?>

                <h2 class="title">IMAP-Einstellungen</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="tmgmt_imap_host">Host <span style="color:red;">*</span></label></th>
                        <td><input type="text" name="tmgmt_imap_host" id="tmgmt_imap_host" value="<?php echo esc_attr($imap_config['host']); ?>" class="regular-text" placeholder="imap.example.com"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_imap_port">Port <span style="color:red;">*</span></label></th>
                        <td><input type="number" name="tmgmt_imap_port" id="tmgmt_imap_port" value="<?php echo esc_attr($imap_config['port']); ?>" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_imap_username">Benutzername <span style="color:red;">*</span></label></th>
                        <td><input type="text" name="tmgmt_imap_username" id="tmgmt_imap_username" value="<?php echo esc_attr($imap_config['username']); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_imap_password">Passwort <span style="color:red;">*</span></label></th>
                        <td><input type="password" name="tmgmt_imap_password" id="tmgmt_imap_password" value="" class="regular-text" placeholder="<?php echo !empty($imap_config['password']) ? '••••••••' : ''; ?>">
                        <p class="description">Leer lassen, um das aktuelle Passwort beizubehalten.</p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_imap_encryption">Verschlüsselung</label></th>
                        <td>
                            <select name="tmgmt_imap_encryption" id="tmgmt_imap_encryption">
                                <option value="ssl" <?php selected($imap_config['encryption'], 'ssl'); ?>>SSL</option>
                                <option value="tls" <?php selected($imap_config['encryption'], 'tls'); ?>>TLS</option>
                                <option value="none" <?php selected($imap_config['encryption'], 'none'); ?>>Keine</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_imap_folder">Postfach-Ordner</label></th>
                        <td><input type="text" name="tmgmt_imap_folder" id="tmgmt_imap_folder" value="<?php echo esc_attr($imap_config['folder']); ?>" class="regular-text" placeholder="INBOX"></td>
                    </tr>
                </table>

                <div style="margin: 10px 0;">
                    <button type="button" class="button" id="tmgmt-test-imap">IMAP-Verbindung testen</button>
                    <span id="tmgmt-imap-test-result" style="margin-left: 10px;"></span>
                </div>

                <h2 class="title">SMTP-Einstellungen</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="tmgmt_smtp_host">Host <span style="color:red;">*</span></label></th>
                        <td><input type="text" name="tmgmt_smtp_host" id="tmgmt_smtp_host" value="<?php echo esc_attr($smtp_config['host']); ?>" class="regular-text" placeholder="smtp.example.com"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_smtp_port">Port <span style="color:red;">*</span></label></th>
                        <td><input type="number" name="tmgmt_smtp_port" id="tmgmt_smtp_port" value="<?php echo esc_attr($smtp_config['port']); ?>" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_smtp_username">Benutzername <span style="color:red;">*</span></label></th>
                        <td><input type="text" name="tmgmt_smtp_username" id="tmgmt_smtp_username" value="<?php echo esc_attr($smtp_config['username']); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_smtp_password">Passwort <span style="color:red;">*</span></label></th>
                        <td><input type="password" name="tmgmt_smtp_password" id="tmgmt_smtp_password" value="" class="regular-text" placeholder="<?php echo !empty($smtp_config['password']) ? '••••••••' : ''; ?>">
                        <p class="description">Leer lassen, um das aktuelle Passwort beizubehalten.</p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_smtp_encryption">Verschlüsselung</label></th>
                        <td>
                            <select name="tmgmt_smtp_encryption" id="tmgmt_smtp_encryption">
                                <option value="ssl" <?php selected($smtp_config['encryption'], 'ssl'); ?>>SSL</option>
                                <option value="tls" <?php selected($smtp_config['encryption'], 'tls'); ?>>TLS</option>
                                <option value="starttls" <?php selected($smtp_config['encryption'], 'starttls'); ?>>STARTTLS</option>
                                <option value="none" <?php selected($smtp_config['encryption'], 'none'); ?>>Keine</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_smtp_from_email">Absender-Adresse <span style="color:red;">*</span></label></th>
                        <td><input type="email" name="tmgmt_smtp_from_email" id="tmgmt_smtp_from_email" value="<?php echo esc_attr($smtp_config['from_email']); ?>" class="regular-text" placeholder="booking@example.com"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_smtp_from_name">Absender-Name</label></th>
                        <td><input type="text" name="tmgmt_smtp_from_name" id="tmgmt_smtp_from_name" value="<?php echo esc_attr($smtp_config['from_name']); ?>" class="regular-text"></td>
                    </tr>
                </table>

                <div style="margin: 10px 0;">
                    <button type="button" class="button" id="tmgmt-test-smtp">SMTP-Verbindung testen</button>
                    <span id="tmgmt-smtp-test-result" style="margin-left: 10px;"></span>
                </div>

                <h2 class="title">Zuordnungsregeln</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="tmgmt_event_id_search">Event-ID suchen in</label></th>
                        <td>
                            <select name="tmgmt_event_id_search" id="tmgmt_event_id_search">
                                <?php $search = get_option('tmgmt_event_id_search', 'both'); ?>
                                <option value="subject" <?php selected($search, 'subject'); ?>>Nur Betreff</option>
                                <option value="body" <?php selected($search, 'body'); ?>>Nur Body</option>
                                <option value="both" <?php selected($search, 'both'); ?>>Betreff und Body</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_event_id_pattern">Event-ID Muster</label></th>
                        <td>
                            <input type="text" name="tmgmt_event_id_pattern" id="tmgmt_event_id_pattern" value="<?php echo esc_attr(get_option('tmgmt_event_id_pattern', '[TMGMT-{EVENT_ID}]')); ?>" class="regular-text">
                            <p class="description">Verwenden Sie <code>{EVENT_ID}</code> als Platzhalter für die 8-stellige Event-ID. Standard: <code>[TMGMT-{EVENT_ID}]</code></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_imap_fetch_interval">Abrufintervall (Minuten)</label></th>
                        <td>
                            <input type="number" name="tmgmt_imap_fetch_interval" id="tmgmt_imap_fetch_interval" value="<?php echo esc_attr(get_option('tmgmt_imap_fetch_interval', 5)); ?>" class="small-text" min="1">
                            <p class="description">Wie oft sollen neue E-Mails abgerufen werden?</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_imap_sent_folder">Gesendet-Ordner</label></th>
                        <td>
                            <input type="text" name="tmgmt_imap_sent_folder" id="tmgmt_imap_sent_folder" value="<?php echo esc_attr($imap_config['sent_folder']); ?>" class="regular-text" placeholder="Sent">
                            <p class="description">IMAP-Ordner für gesendete E-Mails (z.B. "Sent", "INBOX.Sent").</p>
                        </td>
                    </tr>
                </table>

                <div style="margin: 10px 0;">
                    <button type="button" class="button" id="tmgmt-fetch-now">Jetzt abrufen</button>
                    <span id="tmgmt-fetch-result" style="margin-left: 10px;"></span>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>
        <script>
        (function() {
            var restUrl = '<?php echo esc_url_raw(rest_url('tmgmt/v1/')); ?>';
            var nonce = '<?php echo wp_create_nonce('wp_rest'); ?>';

            // IMAP Test Button
            document.getElementById('tmgmt-test-imap').addEventListener('click', function() {
                var btn = this;
                var result = document.getElementById('tmgmt-imap-test-result');
                btn.disabled = true;
                result.textContent = 'Teste Verbindung…';
                result.style.color = '#666';

                fetch(restUrl + 'imap/test', {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': nonce }
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    result.style.color = data.success ? '#00a32a' : '#d63638';
                    result.textContent = data.message;
                    btn.disabled = false;
                })
                .catch(function() {
                    result.style.color = '#d63638';
                    result.textContent = 'Netzwerkfehler.';
                    btn.disabled = false;
                });
            });

            // SMTP Test Button
            document.getElementById('tmgmt-test-smtp').addEventListener('click', function() {
                var btn = this;
                var result = document.getElementById('tmgmt-smtp-test-result');
                btn.disabled = true;
                result.textContent = 'Teste Verbindung…';
                result.style.color = '#666';

                fetch(restUrl + 'smtp/test', {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': nonce }
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    result.style.color = data.success ? '#00a32a' : '#d63638';
                    result.textContent = data.message;
                    btn.disabled = false;
                })
                .catch(function() {
                    result.style.color = '#d63638';
                    result.textContent = 'Netzwerkfehler.';
                    btn.disabled = false;
                });
            });

            // Fetch Now Button
            document.getElementById('tmgmt-fetch-now').addEventListener('click', function() {
                var btn = this;
                var result = document.getElementById('tmgmt-fetch-result');
                btn.disabled = true;
                result.textContent = 'Rufe E-Mails ab…';
                result.style.color = '#666';

                fetch(restUrl + 'imap/fetch-now', {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': nonce }
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    result.style.color = data.success ? '#00a32a' : '#d63638';
                    result.textContent = data.message || 'Abruf abgeschlossen.';
                    btn.disabled = false;
                })
                .catch(function() {
                    result.style.color = '#d63638';
                    result.textContent = 'Netzwerkfehler.';
                    btn.disabled = false;
                });
            });
        })();
        </script>
        <?php
    }
}
