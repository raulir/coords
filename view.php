<?php

if (!file_exists('cache')){
	mkdir('cache');
}

$do = !empty($_POST['do']) ? $_POST['do'] : '';

if ($do == 'view_search'){
	
	// should use sql for better speed, gets slow when thousands of points to sort, not very optimised
	
	function dist($lat1, $lng1, $lat2, $lng2){
		
		$lat1 = deg2rad($lat1);
		$lng1 = deg2rad($lng1);
		$lat2 = deg2rad($lat2);
		$lng2 = deg2rad($lng2);
		
		$lngd = $lng2 - $lng1;
		
		$a = pow(cos($lat2) * sin($lngd), 2) + pow(cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($lngd), 2);
		$b = sin($lat1) * sin($lat2) + cos($lat1) * cos($lat2) * cos($lngd);

		return atan2(sqrt($a), $b) * 6371;
		
	}
	
	// input
	$lat = round($_POST['lat'], 3);
	$lng = round($_POST['lng'], 3);
	$search = mb_ereg_replace('[^\w\s\d\-_,\(\)\.]', '_', trim($_POST['search']));
	
	$cachefile = 'cache/s_'.number_format($lat, 3).'_'.number_format($lng, 3).(!empty($search) ? '_'.md5($search) : '').'.json';
	$filename = 'data.json';
	
	if (file_exists($cachefile) && file_exists($filename) && filemtime($filename) < filemtime($cachefile)){
		readfile($cachefile);
		die();
	}

	// do calculations
	if (!file_exists($filename)){
		$data = [];
	} else {
		$data = json_decode(file_get_contents($filename), true);
	}

	foreach($data as $key => $value){
		if ($search !== '' && !stristr($value['info'], $search)){
			unset($data[$key]);
		} else {
			$data[$key]['dist'] = round(dist($lat, $lng, $value['lat'], $value['lng']), 1);
		}
	}
	
	// sort
	usort($data, function($a, $b){return $a['dist'] <=> $b['dist']; });
	
	$data = array_slice($data, 0, 10);
	
	$data = json_encode($data, JSON_PRETTY_PRINT);
	
	file_put_contents($cachefile, $data);
	
	print($data);
	die();
	
}

?>
<!-- style -->

<style>

	.view_map {
		width: 600px;
		height: 600px;
		border: 1px solid black;
		display: inline-block;
		vertical-align: top;
	}
	
	.view_tools {
		width: 200px;
		display: inline-block;
		vertical-align: top;
	}
	
	.view_results {
		border: 1px solid black;
	}
	
	.view_result {
		border: 1px solid grey;
	}
	
	.view_result_active, .view_result:hover {
		background-color: pink;
	}

	.view_result_dist {
		border-top: 1px dotted grey;
	}
	
	.view_all_button {
		cursor: pointer;
	}
	
	.view_search_button {
		display: inline-block;
		cursor: pointer;
	}
	
	.view_marker {
		display: inline-block;
		width: 60px;
		line-height: 40px;
		color: white;
		background-color: black;
		text-align: center;
		border-radius: 10px;
	}

</style>

<!-- template -->

<div class="view_container">

	<div class="view_map">
		
	</div>
		
	<div class="view_tools">
		<div class="view_all_button">[all]</div>
		<input class="view_search_input" type="text"><div class="view_search_button">[search]</div>
		<div class="view_results"></div>
	</div>
	
</div>

<!-- javascript -->

<script src="https://code.jquery.com/jquery-3.4.1.js"></script>
<script async defer src="https://maps.google.com/maps/api/js?key=AIzaSyA7IYlaWQk3iI9pPM6szPid6WmH0H4L4Tc&callback=init_map"></script>
<script type="text/javascript">

var $view_results = $('.view_results');

var g_markers = {};
var c_markers = {}; // for ids

function init_map(){
	
	// google maps stuff
    var coords = new google.maps.LatLng(58.92, 25.62);
	
    var mapOptions = {
        zoom: 12,
        center: coords,
        streetViewControl: false,
        mapTypeId: google.maps.MapTypeId.ROADMAP,
        draggable: true
    }

    g_map = new google.maps.Map(
        $('.view_map').get(0), mapOptions
    );

	g_marker = new google.maps.Marker({
        // draggable: true,
    	position: coords,
    	map: g_map,
    	icon: 'https://img.icons8.com/color/48/000000/marker.png', // 
    	draggable: true
    });

	setTimeout(() => show_results(coords.lat(), coords.lng(), ''), 1000);
    
	google.maps.event.addListener(g_marker, 'dragend', function(marker){

	    var lat = marker.latLng.lat();
        var lng = marker.latLng.lng();

        show_results(lat, lng, $('.view_search_input').val());

	});

	init_search();
	init_custom_marker();
	
}

function init_search(){

	$('.view_search_button').on('click', function(){
        show_results(g_marker.getPosition().lat(), g_marker.getPosition().lng(), $('.view_search_input').val());
	});

	$('.view_all_button').on('click', reset_map);

	$('.view_results').on('mouseenter', '.view_result', function(){
		var id = $(this).data('id');
        if (typeof g_markers[id] !== 'undefined'){
        	g_markers[id].setAnimation(google.maps.Animation.BOUNCE);
        }
	});
	$('.view_results').on('mouseleave', '.view_result', function(){
		var id = $(this).data('id');
        if (typeof g_markers[id] !== 'undefined'){
        	g_markers[id].setAnimation(null);
        }
	});

	$('.view_results').on('click', '.view_result', function(){
		var id = $(this).data('id');
        show_info(id);
	});
	
}

function show_results(lat, lng, search){

	$.ajax({
		type: 'POST',
	  	url: window.location,
	  	dataType: 'json',
	  	data: {
		  	'do': 'view_search',
		  	'lat': lat,
		  	'lng': lng,
		  	'search': search
		},
	  	context: this,
	  	success: function(data){

	  		$view_results.html('');

			$.each(g_markers, function(){
				this.setVisible(false);
			});
			
			$(data).each(function(){

				var id = this.id;

				// div
				$view_results.append('<div class="view_result view_result_' + id + '" data-id="' + id + '">' + 
						'<div class="view_result_info">' + this.info + '</div>' +
						'<div class="view_result_dist">' + this.dist.toFixed(1) + 'km</div></div>');

				// markers
				
				if (typeof g_markers[id] == 'undefined' ){
	                g_markers[id] = new google.maps.Marker({
	                    position: new google.maps.LatLng(this.lat, this.lng),
	                    icon: 'https://img.icons8.com/material-rounded/48/000000/marker.png',
	                    map: g_map
	                });

	                // over pin changes listing colour
	                g_markers[id].addListener('mouseover', function(){
	                    $('.view_result_' + id).addClass('view_result_active');
	                });
	                g_markers[id].addListener('mouseout', function(){
	                    $('.view_result_active').removeClass('view_result_active');
	                });

	                // show info
	                g_markers[id].addListener('click', function(){
	                    show_info(id);
	                });
		                
				} else {
					g_markers[id].setVisible(true);
				}
				
			});

			reset_map();

		}
	});

}

function reset_map(){

	remove_custom_markers();

	var bounds = {
        'north': g_marker.getPosition().lat(),
        'south': g_marker.getPosition().lat(),
        'east': g_marker.getPosition().lng(),
        'west': g_marker.getPosition().lng()
    };

	$.each(g_markers, function(){

		if (!this.getVisible()){
			return;
		}
		
        if (this.getPosition().lat() > bounds.north) {
            bounds.north = this.getPosition().lat();
        }
        if (this.getPosition().lat() < bounds.south) {
            bounds.south = this.getPosition().lat();
        }
        if (this.getPosition().lng() > bounds.east) {
            bounds.east = this.getPosition().lng();
        }
        if (this.getPosition().lng() < bounds.west) {
            bounds.west = this.getPosition().lng();
        }
	});

	g_map.fitBounds(bounds);
	
}

function show_info(id){

	// to avoid duplicate infos
	if (g_markers[id].getVisible() == false){
		return;
	}

	$('.view_result_' + id).removeClass('view_result_active');

	var ll = new google.maps.LatLng(g_markers[id].getPosition().lat(), g_markers[id].getPosition().lng());

	new CustomMarker(
    	ll, 
    	g_map,
    	{id: id, km: $('.view_result_' + id).children('.view_result_dist').html()}
    );

	g_markers[id].setVisible(false);
	
}

// custom marker code from akdn
function init_custom_marker(){
	
	if (typeof CustomMarker == 'undefined'){
		
		CustomMarker = function(latlng, map, args) {
			this.latlng = latlng;	
			this.args = args;	
			this.setMap(map);
			c_markers[args.id] = this;
		}
		
		CustomMarker.prototype = new google.maps.OverlayView();
		
		CustomMarker.prototype.draw = function() {

			var self = this;
			
			var div = this.div;
			
			if (!div) {
			
				div = this.div = document.createElement('div');
				
				div.className = 'map_marker map_marker_' + self.args.id;
				
				div.style.position = 'absolute';
				div.style.width = '1px';
				div.style.height = '1px';
				div.style.background = 'transparent';
				div.style.cursor = 'pointer';
				
				google.maps.event.addDomListener(div, 'click', function(event) {

					var id = self.args.id;
					
					g_markers[id].setVisible(true);
					// $('.view_result_' + id).addClass('.view_result_active');
					this.remove();
					delete c_markers[id];
					
					return false;

				});
				
				var panes = this.getPanes();
				panes.overlayImage.appendChild(div);
			}
			
			var point = this.getProjection().fromLatLngToDivPixel(this.latlng);
			
			// regulate position
			if (point) {
				div.style.left = point.x - 30 + 'px';
				div.style.top = point.y - 48 + 'px';
			}
			
			// load content to the marker
			var $marker = $('.map_marker_' + self.args.id);
			$marker.html('<div class="view_marker">' + self.args.km + '<div>');
						
		};
		
		CustomMarker.prototype.remove = function() {
			if (this.div) {
				this.div.parentNode.removeChild(this.div);
				this.div = null;
			}	
		};
		
		CustomMarker.prototype.getPosition = function() {
			return this.latlng;	
		};
	
	}

}

function remove_custom_markers(){

	$.each(c_markers, function(key, value){

		google.maps.event.trigger(c_markers[key].div, 'click');

	});
	
}

</script>