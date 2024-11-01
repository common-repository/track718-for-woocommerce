<?php
if (!class_exists('TRACK718_WOCO_Depends')) {
    require_once 'wc-dpds.php';
}

if (!function_exists('track718_chk_woocommerce_active')) {
	function track718_chk_woocommerce_active() {
		return TRACK718_WOCO_Depends::wc_active_check();
	}
}