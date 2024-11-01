<?php
class TRACK718_WOCO_Depends {
	private static $actived_plugins;

	public static function init() {
		self::$actived_plugins = (array) get_option('active_plugins', array());

		if (is_multisite()) {
			self::$actived_plugins = array_merge(self::$actived_plugins, get_site_option('active_sitewide_plugins', array()));
		}
	}

	public static function wc_active_check() {
		if (!self::$actived_plugins) self::init();

		return in_array( 'woocommerce/woocommerce.php', self::$actived_plugins ) || array_key_exists('woocommerce/woocommerce.php', self::$actived_plugins);
	}
}


