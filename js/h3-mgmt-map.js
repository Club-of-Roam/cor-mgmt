var coordinates = app_vars.coordinates;
var messages = app_vars.messages;
var coord_center_lat = parseFloat(app_vars.coord_center_lat);
var coord_center_lng = parseFloat(app_vars.coord_center_lng);
var marker = [];

	var dummyPath = [
		new google.maps.LatLng(0, 0),
		new google.maps.LatLng(0, 0)
	];

	var linePath = new google.maps.Polyline({
		path: dummyPath,
		geodesic: true,
		strokeColor: '#000000',
		strokeOpacity: 1.0,
		strokeWeight: 3
		});
		
function initialize() {
	var mapOptions = {
	  center: { lat: coord_center_lat, lng: coord_center_lng},				//lat: 46.3682855, lng: 14.4170272
	  zoom: 5
	};
	var map = new google.maps.Map(document.getElementById('map-canvas'),
		mapOptions);
		
	var bounds = new google.maps.LatLngBounds();
		
	linePath.setMap(map);

	for (var i = 0; i < coordinates.length; ++i) {
		var loc = coordinates[i].split(",");
		var lat = parseFloat(loc[0]);
		var lng = parseFloat(loc[1]);
		var myLatlng = new google.maps.LatLng(lat, lng);
		
		bounds.extend(myLatlng);
		var pinIcon = new google.maps.MarkerImage(
			// messages[i].route_image,
			"http://chart.apis.google.com/chart?chst=d_map_pin_letter&chld=%E2%80%A2|" + messages[i].hex_color,
			null, /* size is determined at runtime */
			null, /* origin is 0,0 */
			null, /* anchor is bottom center of the scaled image */
			// new google.maps.Size(30, 30)
			null
		);
		
		var contentString = '<div >' +
						'<img class="no-bsl-adjust team-qi-route-logo" alt="Route Logo" src="' +
						messages[i].route_image +
						'" style="width:33px;float:left;padding:0;margin-right:10px;"/>' + 
						// '<br>' +
						'<p class="ticker-message-title" style="padding-bottom: 1em;">' +
						messages[i].team_name_url +
						// '<br>' +
						'</p>' +
						'<p class="ticker-message-mates" style="padding-bottom: 0.01em; font-size: 120%;">' +
						messages[i].mates +
						// '<br>' +
						'</p>' +
						'<p class="ticker-message-time" style="padding-bottom: 1em; font-size: 120%;">' +
						messages[i].date +
						'</p>' +
						// '<br>' +
						// '<br>' +
						'<p style="font-weight: bold; font-size: 120%;">' +
						messages[i].message +
						'</p>' +
						'</div>';
			
		marker[i] = new google.maps.Marker({
			  position: myLatlng,
			  map: map,
			  title: messages[i].team_name,
			  id: messages[i].team_id,
			  icon:  pinIcon
		  });
		  
		marker[i].infowindow = new google.maps.InfoWindow({
			// content: contentString
			content: ""
		});
		
		map.fitBounds(bounds);
		map.panToBounds(bounds); 
		bindInfoWindow(marker[i], marker, map, contentString);
	}	
  }

google.maps.event.addDomListener(window, 'load', initialize);

function bindInfoWindow(marker, marker_all, map, contentString) {
    google.maps.event.addListener(marker, 'click', function() {
		
		var lines = [];

		for (index = 0; index < marker_all.length; ++index) {
			marker_all[index].infowindow.close();
			marker_all[index].setAnimation(null);
			
			if ( marker_all[index].id == marker.id ) {
				lines.push(marker_all[index].position);
				marker_all[index].setAnimation(google.maps.Animation.BOUNCE);
			}
		}
		
		linePath.setMap(null);
		
		linePath = new google.maps.Polyline({
		path: lines,
		geodesic: true,
		strokeColor: '#000000',
		strokeOpacity: 1.0,
		strokeWeight: 3
		});

		linePath.setMap(map);
		
        marker.infowindow.setContent(contentString);
        marker.infowindow.open(map, marker);
    });
}

function contains(a, obj) {
    for (var i = 0; i < a.length; i++) {
        if (a[i] === obj) {
            return i;
        }
    }
    return false;
}