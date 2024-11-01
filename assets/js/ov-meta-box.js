// Order View Javascript
var is_running = false;

var track718_woocommerce_tracking_onload = function () {
	if (is_running) {
		return true;
	}

	is_running = true;

	var providers;

	function set_track718_tracking_provider() {
		jQuery('#track718_tracking_provider').on('change', function () {
			var key = jQuery(this).val();

			if (key) {
				var provider = providers[key];
				jQuery('#track718_tracking_provider_name').val(provider);
			}else{
				jQuery('#track718_tracking_provider_name').val('');
			}
		});
	}

	function fill_meta_box(couriers_selected) {
		var couriers = [];
		var all_couriers = track718_sort_couriers(get_track718_couriers());

		jQuery.each(all_couriers, function (i, courier) {
			if (couriers_selected.indexOf(courier.slug) != -1) {
				couriers.push(courier);
			}
		});

		var selected_provider = jQuery('#track718_tracking_provider_hidden').val();
		var find_selected_provider = couriers_selected.indexOf(selected_provider) != -1;

		if (!find_selected_provider && selected_provider) {
			couriers.push({
				key: selected_provider,
				_name: jQuery("#track718_tracking_provider_name").val()
			});
		}

		couriers = track718_sort_couriers(couriers);

		jQuery.each(couriers, function (i, courier) {
			var str = '<option ';

			if (!find_selected_provider && courier['slug'] == selected_provider) {
				str += 'style="display:none;" ';
			}

			str += 'value="' + courier['slug'] + '" ';

			if (courier['slug'] == selected_provider) {
				str += 'selected="selected"';
			}

			str += '>' + courier['name'] + '</option>';

			jQuery('#track718_tracking_provider').append(str);
		});

		jQuery('#track718_tracking_provider').trigger("chosen:updated");
		jQuery('#track718_tracking_provider_chosen').css({width: '100%'});

		providers = {};

		jQuery.each(couriers, function (i, courier) {
			providers[courier.slug] = courier.name;
		});

		set_track718_tracking_provider();

		jQuery('#track718_tracking_provider').trigger('change');
	}

	if (jQuery('#track718_tracking_provider').length > 0) {
		var selected_couriers = jQuery('#track718_couriers_selected').val();
		var selected_couriers_arr = (selected_couriers) ? selected_couriers.split(',') : [];

		fill_meta_box(selected_couriers_arr);
	}

	return is_running;
};

jQuery(function($) {
	var track718_woocommerce_tracking_items = {
		init: function() {
			$('#woocommerce-track718')
				.on('click', 'a.delete-tracking', this.delete_tracking)	
				.on('click', 'button.button-save-form', this.save_form);
		},

		save_form: function () {
			var error;	
			var tracking_number = jQuery("#track718_tracking_number");
			var tracking_provider = jQuery("#track718_tracking_provider_name");

			if(tracking_number.val() === ''){
				show_error(tracking_number);
				error = true;
			} else {
				var pattern = /^[0-9a-zA-Z \b]+$/;

				if(!pattern.test(tracking_number.val())){
					show_error( tracking_number );
					error = true;
				} else{
					hide_error(tracking_number);
				}
			}

			if(tracking_provider.val() === ''){
				jQuery("#track718_tracking_provider").siblings('.select2-container').find('.select2-selection').css('border-color','#a00');
				error = true;
			} else{
				jQuery("#track718_tracking_provider").siblings('.select2-container').find('.select2-selection').css('border-color','#ddd');
			}

			if(error == true){
				return false;
			}

			if (!$('input#track718_tracking_number').val()) {
				return false;
			}					

			var data = {
				action: 'track718_tracking_save_form',
				order_id: woocommerce_admin_meta_boxes.post_id,
				tracking_provider: $('#track718_tracking_provider').val(),
				tracking_provider_name: $('#track718_tracking_provider_name').val(),
				tracking_shipdate: $('input#track718_tracking_shipdate').val(),
				tracking_number: $('input#track718_tracking_number').val()
			};

			$.post(woocommerce_admin_meta_boxes.ajax_url, data, function(res) {
				jQuery("#post").submit();
			});

			return false;
		},

		delete_tracking: function() {
			var tracking_number = $(this).attr('rel');

			var data = {
				action: 'track718_tracking_delete_item',
				order_id: woocommerce_admin_meta_boxes.post_id,
				tracking_number: tracking_number
			};

			$.post(woocommerce_admin_meta_boxes.ajax_url, data, function(res) {
				$('#tracking-item-' + tracking_number).unblock();

				if (res != '-1') {
					$('#tracking-item-' + tracking_number).remove();
				}
			});

			return false;
		}
	}

	track718_woocommerce_tracking_items.init();
});

function show_error(element){
	element.css("border","1px solid #a00");
}

function hide_error(element){
	element.css("border","1px solid #ddd");
}