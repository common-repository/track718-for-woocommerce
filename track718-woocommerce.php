<?php
/**
 * Plugin Name: TRACK718 for WooCommerce
 * Plugin URI: https://www.track718.com
 * Description: Add courier and tracking number to WooCommerce orders and track them on TRACK718.
 * Requires at least: 5.8
 * Version: 1.0.1
 * Author: TRACK718
 * Author URI: https://www.track718.com/contactus/suggest
 * Text Domain: track718-for-woocommerce
 * Domain Path: /languages
 * Copyright: © TRACK718
 */
if (!defined('ABSPATH')) {
    exit;
}

require_once('includes/functions.php');

define('TRACK718_PATH', dirname(__FILE__ ));
define('TRACK718_ASSETS_URL', plugins_url() . '/' . basename(TRACK718_PATH));

if (track718_chk_woocommerce_active()) {
    if (!class_exists('TRACK718')) {
        final class TRACK718 {
            public $options = array();
            public $plugin_dir;
            public $plugin_url;
            public $couriers = array();
            public $api;

            private $order_tracking_fields = array(
                'tracking_number',
                'tracking_provider',
                'tracking_provider_name',
                'tracking_shipdate'
            );

            protected static $_instance = null;

            // 单例
            public static function instance() {
                if (is_null(self::$_instance)) {
                    self::$_instance = new self();
                }

                return self::$_instance;
            }

            public function __construct() {
                $this->plugin_file = __FILE__;
				$this->plugin_dir  = untrailingslashit(plugin_dir_path(__FILE__));
                $this->plugin_url  = untrailingslashit(plugin_dir_url(__FILE__));

                $this->includes();
                $this->options = get_option('track718_option_name') ? get_option('track718_option_name') : array();
                $this->couriers = $this->options['couriers'];

                add_action('admin_print_scripts', array($this, 'library_scripts'));
                add_action('in_admin_footer', array($this, 'include_footer_script'));
                add_action('admin_menu', array($this, 'automizely_admin_menu'));
                add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));

                // Admin Order View Meta Box
                add_action('add_meta_boxes', array($this, 'add_meta_box'));
                add_action('woocommerce_process_shop_order_meta', array($this, 'save_meta_box'), 0, 2);
                add_action('wp_ajax_track718_tracking_save_form', array($this, 'save_meta_box_ajax'));
                add_action('wp_ajax_track718_tracking_delete_item', array($this, 'meta_box_delete_tracking'));

                // StoreFront User Account
                add_action('woocommerce_view_order', array($this, 'display_tracking_info'));

                // Admin User Profile
                add_action('show_user_profile', array($this, 'add_api_key_field'));
                add_action('edit_user_profile', array($this, 'add_api_key_field'));
                add_action('personal_options_update', array($this, 'generate_api_key'));
                add_action('edit_user_profile_update', array($this, 'generate_api_key'));

                register_activation_hook(__FILE__, array($this, 'install'));
            }

            // 添加插件到左侧菜单
            public function automizely_admin_menu() {
				add_menu_page(
					'TRACK718',
					'TRACK718',
					'manage_options',
					'track718-setting-admin',
					array($this, 'track718_setting_admin_page'),
					TRACK718_ASSETS_URL . '/assets/images/favicon-track718.png'
				);
			}

            // 多语言
            public function load_plugin_textdomain() {
                load_plugin_textdomain('TRACK718', false, dirname(plugin_basename(__FILE__)) . '/languages/');
            }

            public function admin_styles() {
                wp_enqueue_style('track718_styles_chosen', TRACK718_ASSETS_URL . '/assets/plugin/chosen/chosen.min.css');
                wp_enqueue_style('track718_styles', TRACK718_ASSETS_URL . '/assets/css/admin.css');
            }

            // 加载js
            public function library_scripts() {
                wp_enqueue_script('track718_styles_chosen_jquery', TRACK718_ASSETS_URL . '/assets/plugin/chosen/chosen.jquery.min.js');
                wp_enqueue_script('track718_styles_chosen_proto', TRACK718_ASSETS_URL . '/assets/plugin/chosen/chosen.proto.min.js');
                wp_enqueue_script('track718_script_util', TRACK718_ASSETS_URL . '/assets/js/util.js');
                wp_enqueue_script('track718_script_couriers', TRACK718_ASSETS_URL . '/assets/js/couriers.js');
                wp_enqueue_script('track718_script_ov_meta_box', TRACK718_ASSETS_URL . '/assets/js/ov-meta-box.js');
            }

            public function track718_setting_admin_page() {
				include TRACK718_PATH . '/pages/track718_setting_admin_page.php';
			}

            private function includes() {
                require_once($this->plugin_dir . '/includes/track718-admin-settings.php');
            }

            public function add_api_key_field($user) {
                if (!current_user_can('manage_track718')) {
                    return;
                }

                if (current_user_can('edit_user', $user->ID)) {
                    ?>
                    <h3>TRACK718</h3>
                    <table class="form-table">
                        <tbody>
                        <tr>
                            <th><label for="track718_wp_api_key"><?php _e('TRACK718\'s WordPress API Key', 'track718-for-woocommerce'); ?></label>
                            </th>
                            <td>
                                <?php if (empty($user->track718_wp_api_key)) : ?>
                                    <input name="track718_wp_generate_api_key" type="checkbox" id="track718_wp_generate_api_key" value="0"/>
                                    <span class="description"><?php _e('Generate API Key', 'track718-for-woocommerce'); ?></span>
                                <?php else : ?>
                                    <code id="track718_wp_api_key"><?php esc_html_e($user->track718_wp_api_key, 'track718-for-woocommerce') ?></code>
                                    <br/>
                                    <input name="track718_wp_generate_api_key" type="checkbox" id="track718_wp_generate_api_key" value="0"/>
                                    <span class="description"><?php _e('Revoke API Key', 'track718-for-woocommerce'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                <?php
                }
            }

            public function generate_api_key($user_id) {
                if (current_user_can('edit_user', $user_id)) {
                    $user = get_userdata($user_id);

                    if (isset($_POST['track718_wp_generate_api_key'])) {
                        if (empty($user->track718_wp_api_key)) {
                            $api_key = 'ck_' . hash('md5', $user->user_login . date('U') . mt_rand());
                            update_user_meta($user_id, 'track718_wp_api_key', $api_key);
                        } else {
                            delete_user_meta($user_id, 'track718_wp_api_key');
                        }
                    }
                }
            }

            public function display_tracking_info($orderId){
                $this->display_order_track718($orderId);
            }

            private function display_order_track718($orderId) {
                    $tracking_rows = $this->qry_tracking_items($orderId);

                    if ($tracking_rows) {
                        ?>
                        <section>
                        <h2><?php _e('Tracking Information', 'track718-for-woocommerce'); ?></h2>
                        <table class="shop_table shop_table_responsive track718_tracking" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th style="border-right:1px solid rgba(0,0,0,.1);"><?php _e('Courier', 'track718-for-woocommerce'); ?></th>
                                    <th><?php _e('Tracking Number', 'track718-for-woocommerce'); ?></th>
                                </tr>
                            </thead>
                            <tbody><?php
                            foreach ($tracking_rows as $item) {
                                    ?><tr>
                                        <td style="text-align:left;border-right:1px solid rgba(0,0,0,.1);">
                                        <?php esc_html_e($item['tracking_provider_name'], 'track718-for-woocommerce'); ?>
                                        </td>
                                        <td style="text-align:left;">
                                            <?php
                                                $url = 'https://www.track718.com/detail?nums='.$item['tracking_number'];

                                                if (strlen($item['tracking_provider']) > 0) {
                                                    $url .= '&cb='.$item['tracking_provider'].'&plg=wp-front';
                                                }
                                            ?>
                                            <a href="<?php esc_attr_e($url, 'track718-for-woocommerce'); ?>" target="_blank"><?php esc_html_e($item['tracking_number'], 'track718-for-woocommerce'); ?></a>
                                        </td>
                                    </tr><?php
                                }
                            ?></tbody>
                        </table>
                        </section>
                    <?php
                    }
            }

            // 根据订单ID获取发货信息
            public function qry_tracking_items($order_id) {
                $order_info = wc_get_order($order_id);

                if (empty($order_info)) {
                    return array();
                }

                $order_trackings = get_post_meta($order_id, '_track718_tracking_items', true);

                if (is_array($order_trackings)) {
                    return $order_trackings;
                } else {
                    $track_no = get_post_meta($order_id, '_track718_tracking_number', true);

                    if ($track_no) {
                        $tracking_row = array();

                        foreach($this->order_tracking_fields as $field) {
                            if ($field == 'tracking_number') {
                                $tracking_row[$field] = $track_no;
                            } else {
                                $tracking_row[$field] = get_post_meta($order_id, '_track718_'.$field, true);
                            }
                        }

                        $order_trackings[] = $tracking_row;
                    } else {
                        $order_trackings = array();
                    }

                    return $order_trackings;
                }
            }

            // 添加box到Order View页面
            public function add_meta_box() {
                add_meta_box('woocommerce-track718', __('TRACK718', 'track718-for-woocommerce'), array($this, 'meta_box'), 'shop_order', 'side', 'high');
            }

            public function meta_box() {
                global $post;

                $tracking_rows = $this->qry_tracking_items($post->ID);

                $date = (new DateTime())->format('Y-m-d\TH:i:s\Z');

                _e('<div id="track718-tracking-items">', 'track718-for-woocommerce');

                if (count($tracking_rows) > 0) {
                    _e('<ul>', 'track718-for-woocommerce');
                    foreach ($tracking_rows as $item) {
                        $this->show_tracking_items_for_meta_box($item);
                    }
                    _e('</ul>', 'track718-for-woocommerce');
                }

                _e('</div>
                    <p class="form-field"><label for="track718_tracking_provider">'.__('Courier:', 'track718-for-woocommerce').'</label>
                    <select id="track718_tracking_provider" name="track718_tracking_provider" class="chosen_select" style="width:100%">
                        <option selected="selected" value="">Please Select Courier</option>
                    </select></p>', 'track718-for-woocommerce');

                woocommerce_wp_text_input(array(
                    'id' => 'track718_tracking_number',
                    'label' => __('Tracking Number:', 'track718-for-woocommerce'),
                    'placeholder' => '',
                    'description' => '',
                    'class' => '',
                    'value' => '',
                    'style' => 'width:100%'
                ));

                _e('<input type="hidden" id="track718_tracking_provider_name" name="track718_tracking_provider_name" value="'.sanitize_text_field('').'" />
                    <input type="hidden" id="track718_couriers_selected" value="'.sanitize_text_field($this->couriers).'" />
                    <input type="hidden" id="track718_tracking_shipdate" name="track718_tracking_shipdate" value="'.sanitize_text_field($date).'" />
                    <button class="button button-primary button-save-form">'.__('Save', 'track718-for-woocommerce').'</button>
                ', 'track718-for-woocommerce');
            }

            protected function show_tracking_items_for_meta_box($item) {
                $formatted = $this->formatted_tracking_item($item);
                ?>
                <li>
                <div id="tracking-item-<?php esc_attr_e($item['tracking_number'], 'track718-for-woocommerce'); ?>">
                    <p style="padding:10px;background:#efefef;border-radius:4px;">
                        <strong><?php esc_html_e($formatted['formatted_tracking_provider'], 'track718-for-woocommerce'); ?></strong>
                        <br/>
                        <em>
                            <?php
                                if (strlen($formatted['formatted_tracking_link']) > 0) {
                                    _e('<a href="'.esc_url($formatted['formatted_tracking_link']). '" target="_blank" title="'.esc_attr(__( 'Click Me To Track In TRACK718', 'track718-for-woocommerce')).'" style="text-decoration:none;">'.esc_html($item['tracking_number']).'</a>', 'track718-for-woocommerce');
                                    _e('<a href="#" class="delete-tracking" rel="'.esc_attr($item['tracking_number']).'" style="margin-left:12px;color:#a00;text-decoration:none;">'.__('Delete', 'track718-for-woocommerce').'</a>', 'track718-for-woocommerce');
                                } else {
                                    esc_html_e($item['tracking_number'], 'track718-for-woocommerce');
                                }
                            ?>
                        </em> 
                    </p>
                </div>
            </li>
                <?php
            }

            protected function formatted_tracking_item($trackingItem) {
                $formatted = array();

                $formatted['formatted_tracking_provider'] = sanitize_text_field($trackingItem['tracking_provider_name']);
                $formatted['formatted_tracking_link']     = sanitize_url('https://www.track718.com/detail?nums='.sanitize_text_field($trackingItem['tracking_number']).'&cb='.sanitize_text_field($trackingItem['tracking_provider']).'&plg=wp');

                return $formatted;
            }

            // 加载footer.js，触发ov-meta-box
            public function include_footer_script() {
                wp_enqueue_script('track718_script_footer', TRACK718_ASSETS_URL . '/assets/js/footer.js', true);
            }

            // 查看订单页面-保存物流信息
            public function save_meta_box($post_id) {
                if (isset($_POST['track718_tracking_number']) && $_POST['track718_tracking_provider'] != '' && strlen($_POST['track718_tracking_number']) > 0) {
                    $args = array();

                    foreach($this->order_tracking_fields as $field) {
                        $args[$field] = wc_clean($_POST['track718_'.$field]);
                    }

                    $this->add_tracking_row($post_id, $args);
                }
            }

            public function add_tracking_row($order_id, $args) {
                $tracking_item = array();

                $tracking_items = $this->qry_tracking_items($order_id);

                foreach($this->order_tracking_fields as $field) {
                    if (!isset($args[$field])) {
                        continue;
                    }

                    $tracking_item[$field] = $args[$field]; // 先赋值

                    if ($field == 'tracking_number' && count($tracking_items) > 0) {
                        foreach ($tracking_items as $item) {
                            if ($item['tracking_number'] == $tracking_item['tracking_number']) {
                                return $tracking_item;
                            }
                        }
                    }
                }

                $tracking_items[] = $tracking_item;

                $this->save_tracking_items($order_id, $tracking_items);

                return $tracking_item;
            }

            public function save_tracking_items($order_id, $tracking_items, $is_update_date = false) {
                update_post_meta($order_id, '_track718_tracking_items', $tracking_items);

                if ($is_update_date) {
                    $date = new DateTime();

                    $my_post = array(
                        'ID'                => $order_id,
                        'post_modified'     => $date->format('Y-m-dH:i:s'),
                        'post_modified_gmt' => $date->format('Y-m-d\TH:i:s\Z')
                    );

                    wp_update_post($my_post);
                }
            }

            public function save_meta_box_ajax() {
                if (isset($_POST['tracking_number']) && strlen($_POST['tracking_number']) > 0 && isset($_POST['tracking_provider']) && $_POST['tracking_provider'] != '') {
                    $order_id = wc_clean($_POST['order_id']);
                    $args = array();

                    foreach($this->order_tracking_fields as $field) {
                        $args[$field] = wc_clean($_POST[$field]);
                    }

                    $tracking_item = $this->add_tracking_row($order_id, $args);

                    $this->show_tracking_items_for_meta_box($tracking_item);
                }

                exit();
            }

            public function meta_box_delete_tracking() {
                $order_id = wc_clean($_POST['order_id']);
                $tracking_no = wc_clean($_POST['tracking_number']);

                $this->del_tracking_item($order_id, $tracking_no);	
            }

            // 删除物流信息
            public function del_tracking_item($order_id, $tracking_no) {
                $tracking_items = $this->qry_tracking_items($order_id);
                $tracking_no_ori = get_post_meta($order_id, '_track718_tracking_number', true);

                if ($tracking_no == $tracking_no_ori) {
                    foreach ($this->order_tracking_fields as $field) {
                        delete_post_meta($order_id, '_track718_'.$field);
                    }
                }

                if (count($tracking_items) > 0) {
                    foreach ($tracking_items as $key => $item) {
                        if ($item['tracking_number'] == $tracking_no) {
                            unset($tracking_items[$key]);
                            break;
                        }
                    }

                    $this->save_tracking_items($order_id, $tracking_items, true);
                }

                return false;
            }

            public function install() {
                global $wp_roles;

                if (class_exists('WP_Roles')) {
                    if (!isset($wp_roles)) {
                        $wp_roles = new WP_Roles();
                    }
                }

                if (is_object($wp_roles)) {
                    $wp_roles->add_cap('administrator', 'manage_track718');
                }
            }
        }
    }

    function track718() {
		static $instance;

		if (!isset($instance)) {
			$instance = new TRACK718();
		}

		return $instance;
	}

    $GLOBALS['track718'] = track718();
}