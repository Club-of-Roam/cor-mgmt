(function($){ // closure

$(document).ready(function() {
	//if( $('li.wp-has-current-submenu[.^=toplevel_page_] div.wp-menu-image img').length > 0) {
	//	var imgSrc = $('li.wp-has-current-submenu[.^=toplevel_page_] div.wp-menu-image img').attr("src").match(/.+(?=\.)/) + "-current.png";
	//	$('li.wp-has-current-submenu[.^=toplevel_page_] div.wp-menu-image img').attr("src", imgSrc);
	//} else if( $('li.current[.^=toplevel_page_] div.wp-menu-image img').length > 0) {
	//	var imgSrc = $('li.current[.^=toplevel_page_] div.wp-menu-image img').attr("src").match(/.+(?=\.)/) + "-current.png";
	//	$('li.current[.^=toplevel_page_] div.wp-menu-image img').attr("src", imgSrc);
	//}

	$('.no-js-hide').each(function() {
		$(this).show()
	});

	$('.js-hide').each(function() {
		if( $(this).is('input') ) {
			marker = $('<br class="marker" />').insertBefore(this);
			$(this).detach().attr('type', 'hidden').insertAfter(marker).focus();
			marker.remove();
		} else {
			$(this).hide()
		}
	});

	$('.js-remove').each(function() {
		$(this).remove();
	});


});

$('input.do-bulk-action').click(function(e) {
	var actionVal = $(this).siblings('select.bulk-action').first().val();
	if( actionVal == -1 || actionVal == 'please-select' ) {
		e.preventDefault();
		return false;
	}
	var checkedCount = $(this).parents('.blk-action-form').find('input:checked').length;
	if( checkedCount == 0 ) {
		e.preventDefault();
		return false;
	}
	return true;
});

$('.simul-select').change(function() {
	var newVal = $(this).val();
	$(this).parents('form').find('.simul-select').each(function() {
		$(this).val( newVal );
	});
});


$('form input.bulk-deselect').click( function() {
	var todo = $(this).attr( 'data' );
	if ( 'select' === todo ) {
		$(this).parent().find('input[type=checkbox]').prop( 'checked', true );
		$(this).val( genericParams.strings.btnDeselect );
		$(this).attr( 'data', 'deselect' );
	} else {
		$(this).parent().find('input[type=checkbox]').prop( 'checked', false );
		$(this).val( genericParams.strings.btnSelect );
		$(this).attr( 'data', 'select' );
	}
	return false;
});

})(jQuery); // closure
document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-confirm]');
    if (button && !window.confirm(button.dataset.confirm)) event.preventDefault();
});
