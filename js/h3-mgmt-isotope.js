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
      return this.find('.route').text();
    },
    stage : function ( $elem ) {
      return parseInt( this.find('.stage').text(), 10);
    },
    country : function ( $elem ) {
      return this.find('.country').text();
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
      return this.find('.team-overview-route').textContent;
    },
    teamname : function ( $elem ) {
      return this.find('.team-name').textContent;
   },
    sponsorcount : function ( $elem ) {
      return parseInt( this.find('.sponsor-count').textContent, 10);
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
