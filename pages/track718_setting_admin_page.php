<?php
// Prevent direct file access
if( ! defined('ABSPATH' )){
	exit;
}

if (!current_user_can('manage_options')) {
	wp_die(__( 'You do not have permissions to visit this page.'));
}
?>

<div class="wrap">
	<h2>Couriers Settings</h2>
	<form method="post" action="options.php">
		<?php
			settings_fields('track718_option_group');
			do_settings_sections('track718-setting-admin');
			submit_button();
		?>
	</form>
</div>