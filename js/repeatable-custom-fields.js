jQuery('.repeatable-cf-add').click(function() {
	field = jQuery(this).closest('td').find('.repeatable-cf li:last').clone(true);
	fieldLocation = jQuery(this).closest('td').find('.repeatable-cf li:last');
	jQuery('input', field).val('').attr('name', function(index, name) {
		return name.replace(/(\d+)/, function(fullMatch, n) {
			return Number(n) + 1;
		});
	})
	field.insertAfter(fieldLocation, jQuery(this).closest('td'));
	var count = jQuery('.repeatable-cf-remove').length;
	if( count != 1 ) {
		jQuery('.repeatable-cf-remove').css('display','inline');
	}
	return false;
});

jQuery('.repeatable-cf-remove').click(function(){
	jQuery(this).parent().remove();
	var count = jQuery('.repeatable-cf-remove').length;
	if( count == 1 ) {
		jQuery('.repeatable-cf-remove').css('display','none');
	}
	return false;
});

jQuery('.contact-cf-add').click(function() {
	field = jQuery(this).closest('table').find('tbody:last').prev().clone(true);
	fieldLocation = jQuery(this).closest('table').find('tbody:last').prev();
	jQuery('input', field).val('').attr('name', function(index, name) {
		return name.replace(/(\d+)/, function(fullMatch, n) {
			return Number(n) + 1;
		});
	})
	jQuery('label', field).attr('for', function(index, forcount) {
		return forcount.replace(/(\d+)/, function(fullMatch, n) {
			return Number(n) + 1;
		});
	})
	field.insertAfter(fieldLocation, jQuery(this).closest('table'));
	var count = jQuery('.contact-cf-remove').length;
	if( count != 1 ) {
		jQuery('.contact-cf-remove').css('display','inline');
	}
	return false;
});

jQuery('.contact-cf-remove').click(function(){
	jQuery(this).parents('tbody').remove();
	var count = jQuery('.contact-cf-remove').length;
	if( count == 1 ) {
		jQuery('.contact-cf-remove').css('display','none');
	}
	return false;
});

jQuery(document).ready(function() {
	var rcount = jQuery('.repeatable-cf-remove').length;
	var scount = jQuery('.slots-cf-remove').length;
	var ccount = jQuery('.contact-cf-remove').length;
	if( rcount == 1 ) {
		jQuery('.repeatable-cf-remove').css('display','none');
	}
	if( scount == 1 ) {
		jQuery('.slots-cf-remove').css('display','none');
	}
	if( ccount == 1 ) {
		jQuery('.contact-cf-remove').css('display','none');
	}
});