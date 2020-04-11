document.addEventListener('DOMContentLoaded', function (event) {
		
	'use strict';

	// Initialise resize library
	var resize = new window.resize();
	resize.init();
	
	var image = document.getElementById("image");
	var image_loading = document.getElementById("image_loading");
	var image_ready = document.getElementById("image_ready");
	var image_send = document.getElementById("image_send");
	var image_hidden = document.getElementById("image_hidden");
	
	// Upload photo
	var upload = function (photo, callback) {
		var formData = new FormData();
		formData.append('photo', photo);
		
		if (window.XMLHttpRequest)
		  {// code for IE7+, Firefox, Chrome, Opera, Safari
		  var request=new XMLHttpRequest();
		  }
		else
		  {// code for IE6, IE5
		  var request=new ActiveXObject("Microsoft.XMLHTTP");
		  }
  
		//var request = new XMLHttpRequest();
		request.onreadystatechange = function() {
			if (request.readyState === 4) {
				callback(request.response);
				// alert(request.response);
				// alert(request.response.url);
			}
		}
		//var process_url = app_vars.url_base + "/wp-content/uploads/ticker_images/process.php";
		//var process_url = "https://tramprennen.org/wp-content/uploads/ticker_images/process.php";
		var process_url = "https://" + window.location.hostname + "/wp-content/uploads/ticker_images/process.php";
		//alert(process_url);
		request.open('POST', process_url );
		// request.responseType = 'json';
		request.send(formData);
	};

	var fileSize = function (size) {
		var i = Math.floor(Math.log(size) / Math.log(1024));
		return (size / Math.pow(1024, i)).toFixed(2) * 1 + ' ' + ['B', 'kB', 'MB', 'GB', 'TB'][i];
	};

	document.querySelector('form input[type=file]').addEventListener('change', function (event) {
		event.preventDefault();
		
		image.innerHTML = "";
		image_loading.innerHTML = "Loading...";
		var files = event.target.files;
		for (var i in files) {

			if (typeof files[i] !== 'object') return false;

			(function () {

				var initialSize = files[i].size;

				resize.photo(files[i], 1200, 'file', function (resizedFile) {

					var resizedSize = resizedFile.size;

					upload(resizedFile, function (response) {
						//This is not used in the demo, but an example which returns a data URL so yan can show the user a thumbnail before uploading th image.
						//resize.photo(resizedFile, 400, 'dataURL', function (thumbnail) {
							//image.innerHTML = "<img src=\"" + thumbnail + "\" alt=\"\" >";
							image_loading.innerHTML = "";
							image_ready.innerHTML = "Upload finish! Send picture with or without a message.";
							//image_ready.innerHTML = "Upload finish! Send picture with or without a message. <br> size=" + resizedSize + "Byte.  <br>URL:" + response ;
							//image_send.innerHTML =  response.url;
							// alert(response);
							image_hidden.innerHTML =  "<input type=\"hidden\" name=\"image_name\" value=\"" + response + "\">";
							// image_hidden2.innerHTML =  "<input type=\"hidden\" name=\"image\" value=\"" + resizedFile + "\">"; 
							image_send.innerHTML =  "<div class=\"form-row\"> <input type=\"submit\" id=\"submit_form\" name=\"submit_form\" value=\"Send picture\"/></div>"
							//image_send.innerHTML = app_vars.url_base + app_vars.team_id;
							//image_send.innerHTML = "<button onclick=" + upload(resizedFile, function (response) + ">Get coordinates</button>";
							//image_send.innerHTML = "<button onclick=\"upload(resizedFile, function (response)\">Send picture + message</button>";
						//});
						
						// image.innerHTML = "Image is: " + response.url; // + response.url
						
						// var rowElement = document.createElement('tr');
						// rowElement.innerHTML = '<td>'+new Date().getHours()+':'+new Date().getMinutes()+':'+new Date().getSeconds()+'</td><td>'+fileSize(initialSize)+'</td><td>'+fileSize(resizedSize)+'</td><td>'+Math.round((initialSize - resizedSize) / initialSize * 100)+'%</td><td><a href="'+response.url+'">view image</a></td>';
						// document.querySelector('table.images tbody').appendChild(rowElement);
					});

				});

			}());

		}

	});

});
