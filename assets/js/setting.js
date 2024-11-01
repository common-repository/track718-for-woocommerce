jQuery(function () {
    // Couriers selection
	var track718_couriers_select = jQuery('#track718_couriers_select');
	var track718_couriers = jQuery('#track718_couriers');
    function set_track718_tracking_provider(selected_couriers) {
        var couriers = track718_sort_couriers(get_track718_couriers());
        jQuery.each(couriers, function (key, courier) {
            var str = '<option ';
            str += 'value="' + courier['slug'] + '" ';
            if (selected_couriers.hasOwnProperty(courier['slug'])) {
                str += 'selected="selected"';
            }
            str += '>' + courier['name'] + '</option>';
			track718_couriers_select.append(str);
        });

		track718_couriers_select.val(selected_couriers);
		track718_couriers_select.chosen();
		track718_couriers_select.trigger('chosen:updated');
    }

	track718_couriers_select.change(function () {
        var couriers_select = track718_couriers_select.val();
        var value = (couriers_select) ? couriers_select.join(',') : '';
		track718_couriers.val(value);
    });

    if (track718_couriers) {
        var couriers_select = track718_couriers.val();
        var couriers_select_array = (couriers_select) ? couriers_select.split(',') : [];
        set_track718_tracking_provider(couriers_select_array);
    }
});
