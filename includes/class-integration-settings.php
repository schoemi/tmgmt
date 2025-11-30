<?php
/**
 * Integration Settings Page for API Keys
 */
if (!defined('ABSPATH')) exit;

class TMGMT_Integration_Settings {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_settings_page() {
        add_submenu_page(
            'tmgmt-settings-hidden', // Parent: Hidden, wird als Unterpunkt von Einstellungen angezeigt
            'Integrationen',
            'Integrationen',
            'tmgmt_manage_settings',
            'tmgmt-integration-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('tmgmt_integration_settings', 'tmgmt_api_keys');
    }

    public function render_settings_page() {
        $api_keys = get_option('tmgmt_api_keys', array());
        ?>
        <div class="wrap">
            <h1>Integrationen - API Keys</h1>
            <form method="post" action="options.php">
                <?php settings_fields('tmgmt_integration_settings'); ?>
                <?php do_settings_sections('tmgmt_integration_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="easyverein_api_key">easyVerein API Key</label></th>
                        <td><input type="text" name="tmgmt_api_keys[easyverein]" id="easyverein_api_key" value="<?php echo esc_attr($api_keys['easyverein'] ?? ''); ?>" class="regular-text"></td>
                    </tr>
                    <!-- Weitere Integrationen hier -->
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

new TMGMT_Integration_Settings();
