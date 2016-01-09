jQuery(document).ready(function() {
	var curVal = parseFloat( jQuery('#thumbs').val() );
	if ( curVal >= 20 ) {
		jQuery('div#no-donation-receipt-wrap').hide( 0 );
		jQuery('div#donation-receipt-wrap').show( 0 );
	}
});

jQuery('.less-arrow').click(function() {
	var prevVal = parseFloat( jQuery('#thumbs').val() );
	var minVal = parseFloat( jQuery('#min').val() );
	if( prevVal > 10 ) {
		var newVal = prevVal - 2;
	} else if( prevVal > 5 ) {
		var newVal = prevVal - 1;
	} else {
		var newVal = prevVal - .5;
	}
	if( newVal < minVal ) {
		newVal = prevVal;
	}
	var newDon = newVal * 10;
	jQuery('#thumbs').val( newVal );
	jQuery('.thumbs').text( newVal );
	jQuery('#donation').val( newDon );
	jQuery('.donation').text( newDon );
});

jQuery('.more-arrow').click(function() {
	var prevVal = parseFloat( jQuery('#thumbs').val() );
	if( prevVal < 5 ) {
		var newVal = prevVal + .5;
	} else if( prevVal < 10 ) {
		var newVal = prevVal + 1;
	} else {
		var newVal = prevVal + 2;
	}
	var newDon = newVal * 10;
	jQuery('#thumbs').val( newVal );
	jQuery('.thumbs').text( newVal );
	jQuery('#donation').val( newDon );
	jQuery('.donation').text( newDon );
	if ( newVal >= 20 ) {
		jQuery('div#donation-receipt-wrap').show( 400 );
		jQuery('div#no-donation-receipt-wrap').hide( 0 );
	}
});

jQuery('#receipt').change(function() {
	var receiptVal = jQuery('#receipt').val();
	if( receiptVal == 0 ) {
		jQuery('.address-row').hide( 400 );
	} else {
		jQuery('.address-row').show( 400 );
	}
});

function resizeIframe(obj) {
	obj.style.height = '1000px';
    //obj.style.height = obj.contentWindow.document.body.scrollHeight + '5000px';
}

//jQuery('#donation-submit-debit').click(function() {
//	var receiptVal = jQuery('#receipt').val();
//	if ( confirm( donationParams.debitConfirm + "\n\n" + donationParams.accountID + ": " + jQuery('input#account_id').val() + "\n" + donationParams. bankID + ": " + jQuery('input#bank_id').val() + "\n" + donationParams.donation + ": " + jQuery('input#donation').val() + " " + donationParams.euros ) ) {
//		return true;
//	}
//	return false;
//});