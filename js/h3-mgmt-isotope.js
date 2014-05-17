jQuery('#stages-filters a').click(function(){
  var selector = jQuery(this).attr('data-filter');
  jQuery('#stages-container').isotope({ filter: selector });
  return false;
});

jQuery('#country-filters a').click(function(){
  var selector = jQuery(this).attr('data-filter');
  jQuery('#stages-container').isotope({ filter: selector });
  return false;
});

jQuery('#stages-container').isotope({
  getSortData : {
    route : function ( $elem ) {
      return $elem.find('.route').text();
    },
    stage : function ( $elem ) {
      return parseInt( $elem.find('.stage').text(), 10);
    },
    country : function ( $elem ) {
      return $elem.find('.country').text();
    }
  }
});

jQuery('#sort-by a').click(function(){
  var sortName = jQuery(this).attr('href').slice(1);
  jQuery('#stages-container').isotope({ sortBy : sortName });
  return false;
});

jQuery('#teams-route-filters a').click(function(){
  var selector = jQuery(this).attr('data-filter');
  jQuery('#teams-container').isotope({ filter: selector });
  return false;
});

jQuery('#teams-other-filters a').click(function(){
  var selector = jQuery(this).attr('data-filter');
  jQuery('#teams-container').isotope({ filter: selector });
  return false;
});

jQuery('#teams-container').isotope({
  getSortData : {
    route : function ( $elem ) {
      return $elem.find('.team-overview-route').text();
    },
    teamname : function ( $elem ) {
      return $elem.find('.team-name').text();
    },
    sponsorcount : function ( $elem ) {
      return parseInt( $elem.find('.sponsor-count').text(), 10);
    }
  }
});

jQuery('#teams-sort-by a').click(function(){
  var sortName = jQuery(this).attr('href').slice(1);
  jQuery('#teams-container').isotope({ sortBy : sortName });
  return false;
});

jQuery(window).load(function(){
	jQuery('#teams-container').isotope();
});

jQuery('.isotope-wrap .jumper').click(function(){
	var teamID = jQuery('.isotope-wrap .team-selector').val();
	window.location = IsotopeParams.redirect + '?id=' + teamID;
});