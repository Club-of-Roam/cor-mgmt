var coordinates = document.getElementById("coordinates");
var coordinates_loading = document.getElementById("coordinates_loading");
var coordinates_send = document.getElementById("coordinates_send");
var coordinates_hidden = document.getElementById("coordinates_hidden");
var coordinates_position = document.getElementById("coordinates_position");

function getLocation() {
	if (navigator.geolocation) {
		coordinates_loading.innerHTML = "Loading...";
		navigator.geolocation.getCurrentPosition(showPosition);
	} else { 
		coordinates.innerHTML = "Geolocation is not supported by this browser.";
	}
}

function showPosition(position) {
	currentPos = position.coords.latitude+","+position.coords.longitude;
	coordinates_loading.innerHTML = "";
	coordinates_position.innerHTML = "Position is: " + currentPos;
	coordinates.innerHTML = "<iframe style=\"height:400px;width:100%;border:0;\" frameborder=\"0\" src=\"https://www.google.com/maps/embed/v1/place?q=" + currentPos + "&zoom=14&key=AIzaSyAN0om9mFmy1QN6Wf54tXAowK4eT0ZUPrU\"></iframe>";
	coordinates_hidden.innerHTML =  "<input type=\"hidden\" name=\"coordinates\" value=\"" + currentPos + "\">";
	coordinates_send.innerHTML =  "<div class=\"form-row\"> <input type=\"submit\" id=\"submit_form\" name=\"submit_form\" value=\"Send coordinates\"/></div>";
}					