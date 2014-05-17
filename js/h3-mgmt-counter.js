(function($){ // closure

function countUp( currentVal, maxVal ) {
	var increment = ( maxVal - 10 ) / 62;
	if ( increment < 1 ) {
		increment = 1;
	}
    setInterval( function() {
			if( currentVal < maxVal ) {
				if( ( currentVal + increment ) < ( maxVal - 10 ) ) {
					currentVal = Math.floor( currentVal + increment );
				} else if( ( currentVal + 1 ) < maxVal ) {
					currentVal++;
				} else {
					currentVal = maxVal;
				}
				$('span#counter-value').text(currentVal);
			}
		},
		50
	);
}

$(document).ready(function() {
	setTimeout( function() {
			var minVal = counterParams.min,
				curVal = counterParams.current;
			countUp( minVal, curVal );
		},
		975
	);
});

})(jQuery); // closure