<?php
if (! defined('ABSPATH')) {
    exit;
}

if (!class_exists('TRACK718_Admin_Settings')) {
    final class TRACK718_Admin_Settings {
        public function __construct() {
            add_action('admin_init', array($this, 'page_init'));
            add_action('admin_print_styles', array($this, 'admin_styles'));
            add_action('admin_print_scripts', array($this, 'library_scripts'));
        }
    
        public function admin_styles() {
            $plugin_url = track718()->plugin_url;
            wp_enqueue_style('track718_styles_chosen', $plugin_url . '/assets/plugin/chosen/chosen.min.css');
            wp_enqueue_style('track718_styles', $plugin_url . '/assets/css/admin.css');
        }
    
        public function library_scripts() {
            $plugin_url = track718()->plugin_url;
            wp_enqueue_script('track718_styles_chosen_jquery', $plugin_url. '/assets/plugin/chosen/chosen.jquery.min.js');
            wp_enqueue_script('track718_styles_chosen_proto', $plugin_url . '/assets/plugin/chosen/chosen.proto.min.js');
            wp_enqueue_script('track718_script_util', $plugin_url . '/assets/js/util.js');
            wp_enqueue_script('track718_script_couriers', $plugin_url . '/assets/js/couriers.js');
            wp_enqueue_script('track718_script_setting', $plugin_url . '/assets/js/setting.js');
        }
    
        public function page_init() {
            register_setting(
                'track718_option_group',
                'track718_option_name', 
                array($this, 'sanitize')
            );
    
            add_settings_section(
                'track718_setting_section_id', 
                '', 
                array($this, 'show_section_info'), 
                'track718-setting-admin'
            );
    
            add_settings_field(
                'track718_couriers',
                'Couriers',
                array($this, 'couriers_callback'),
                'track718-setting-admin',
                'track718_setting_section_id'
            );
        }
    
        public function sanitize($input) {
            $other_input = array();
    
            if (isset($input['couriers'])) {
                $other_input['couriers'] = sanitize_text_field($input['couriers']);
            }
    
            return $other_input;
        }
    
        public function show_section_info() {
           
        }
    
        public function couriers_callback() {
            $couriers = array();
            $options = get_option('track718_option_name');
    
            if (isset($options['couriers'])) {
                $couriers = explode(',', $options['couriers']);
            }
    
            $selected_couriers = implode(",", $couriers);
    
            _e('<select data-placeholder="Please Select Couriers" id="track718_couriers_select" class="chosen-select" multiple style="width:100%"></select>'
                .'<div><input type="hidden" id="track718_couriers" name="track718_option_name[couriers]" value="'.sanitize_text_field($selected_couriers).'"/></div>', 'track718-for-woocommerce');
        }
    }
    
    if (is_admin()) {
        $admin_settings = new TRACK718_Admin_Settings();
    }
}