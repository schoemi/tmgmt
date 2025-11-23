<?php

class TMGMT_Settings_Menu {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_menu'));
        add_filter('parent_file', array($this, 'highlight_settings_menu'));
        add_filter('submenu_file', array($this, 'highlight_submenu_item'));
    }

    public function add_settings_menu() {
        add_submenu_page(
            'edit.php?post_type=event',
            'Einstellungen',
            'Einstellungen',
            'manage_options',
            'tmgmt-settings',
            array($this, 'render_settings_page')
        );
    }

    public function highlight_settings_menu($parent_file) {
        global $current_screen;
        if (!is_object($current_screen)) return $parent_file;
        
        $cpts = array('tmgmt_status_def', 'tmgmt_action', 'tmgmt_email_template', 'tmgmt_webhook', 'tmgmt_kanban_col');
        if (in_array($current_screen->post_type, $cpts)) {
            return 'edit.php?post_type=event';
        }
        return $parent_file;
    }

    public function highlight_submenu_item($submenu_file) {
        global $current_screen;
        if (!is_object($current_screen)) return $submenu_file;

        $cpts = array('tmgmt_status_def', 'tmgmt_action', 'tmgmt_email_template', 'tmgmt_webhook', 'tmgmt_kanban_col');
        if (in_array($current_screen->post_type, $cpts)) {
            return 'tmgmt-settings';
        }
        return $submenu_file;
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>TMGMT Einstellungen</h1>
            <p>Verwalten Sie hier die Konfiguration für das Event Management.</p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
                
                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 15px; background: #f0f0f1; border-bottom: 1px solid #c3c4c7;">
                        <h2 style="margin:0; font-size: 16px;">Status Definitionen</h2>
                    </div>
                    <div style="padding: 15px;">
                        <p>Definieren Sie die Status für den Workflow (z.B. Option, Fest, Abgesagt) und deren Regeln.</p>
                        <a href="edit.php?post_type=tmgmt_status_def" class="button button-primary">Verwalten</a>
                    </div>
                </div>

                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 15px; background: #f0f0f1; border-bottom: 1px solid #c3c4c7;">
                        <h2 style="margin:0; font-size: 16px;">Aktionen</h2>
                    </div>
                    <div style="padding: 15px;">
                        <p>Erstellen Sie wiederverwendbare Aktionen (E-Mails, Webhooks), die bei Statuswechseln ausgelöst werden.</p>
                        <a href="edit.php?post_type=tmgmt_action" class="button button-primary">Verwalten</a>
                    </div>
                </div>

                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 15px; background: #f0f0f1; border-bottom: 1px solid #c3c4c7;">
                        <h2 style="margin:0; font-size: 16px;">E-Mail Vorlagen</h2>
                    </div>
                    <div style="padding: 15px;">
                        <p>Verwalten Sie Textvorlagen für E-Mails mit Platzhaltern.</p>
                        <a href="edit.php?post_type=tmgmt_email_template" class="button button-primary">Verwalten</a>
                    </div>
                </div>

                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 15px; background: #f0f0f1; border-bottom: 1px solid #c3c4c7;">
                        <h2 style="margin:0; font-size: 16px;">Webhooks</h2>
                    </div>
                    <div style="padding: 15px;">
                        <p>Konfigurieren Sie externe Endpunkte für Automatisierungen.</p>
                        <a href="edit.php?post_type=tmgmt_webhook" class="button button-primary">Verwalten</a>
                    </div>
                </div>

                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 15px; background: #f0f0f1; border-bottom: 1px solid #c3c4c7;">
                        <h2 style="margin:0; font-size: 16px;">Kanban Spalten</h2>
                    </div>
                    <div style="padding: 15px;">
                        <p>Konfigurieren Sie die Spalten des Dashboards und ordnen Sie Status zu.</p>
                        <a href="edit.php?post_type=tmgmt_kanban_col" class="button button-primary">Verwalten</a>
                    </div>
                </div>

            </div>
        </div>
        <?php
    }
}
