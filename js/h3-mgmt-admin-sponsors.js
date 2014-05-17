(function($){ // closure

$(document).ready(function() {
	debitSectionToggle();
	teamsSelectPopulate();
});

$('select#method').change(function() {
	debitSectionToggle();
});

$('select#type').change(function() {
	teamsSelectPopulate();
});

$('select#race_id').change(function() {
	teamsSelectPopulate();
});

function debitSectionToggle() {
	var curVal = $('select#method').val();

	if ( 'debit' === curVal ) {
		$('input#account_id').closest('tr').show(400);
		$('input#bank_id').closest('tr').show(400);
		$('input#bank_name').closest('tr').show(400);
		$('input#debit_confirmation').closest('tr').show(400);
	} else {
		$('input#account_id').closest('tr').hide(400);
		$('input#bank_id').closest('tr').hide(400);
		$('input#bank_name').closest('tr').hide(400);
		$('input#debit_confirmation').closest('tr').hide(400);
	}
}

function teamsSelectPopulate() {
	var typeString = $('select#type').val(),
		raceID = $('select#race_id').val();

	if ( 'owner' === typeString ) {
		var teams = sponsorsParams.teamsWithoutOwner[raceID];
	} else {
		var teams = sponsorsParams.teams[raceID];
	}

	$('select#team_id').find('option[value!="please_select"]').remove();

	for ( var i = 0; i < teams.length; i++ ) {
		$('select#team_id').append(
			'<option value="' + teams[i].value + '">' + teams[i].label + '</option>'
		);
	}

	if ( null != sponsorsParams.sponsoredTeamID ) {
		$('select#team_id').find('option[value="' + sponsorsParams.sponsoredTeamID + '"]').prop( 'selected', true );
	}
}

})(jQuery); // closure