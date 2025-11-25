<?php

class TMGMT_General_Settings {

    public function __construct() {
        add_action('init', array($this, 'handle_admin_bar'));
    }

    public function handle_admin_bar() {
        if (is_admin()) return;
        if (current_user_can('administrator')) return;

        $hide_desktop = get_option('tmgmt_hide_admin_bar_desktop');
        $hide_mobile = get_option('tmgmt_hide_admin_bar_mobile');

        if (wp_is_mobile()) {
            if ($hide_mobile) {
                show_admin_bar(false);
            }
        } else {
            if ($hide_desktop) {
                show_admin_bar(false);
            }
        }
    }
}
