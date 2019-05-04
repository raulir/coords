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
			if (empty($data[$key]['id'])){
				$data[$key]['id'] = substr(md5($value['lat'] . $value['lng'] . $value['info']), 0, 10);
			}
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
<script async defer src="https://maps.google.com/maps/api/js?key=AIzaSyAycRIejSHGMWJ43a3yQzPBubZ7NjyEn44&callback=init_map"></script>
<script type="text/javascript">

var $view_results = $('.view_results');

var g_markers = {};

function init_map(){
	
	// google maps stuff
    var coords = new google.maps.LatLng(59.44, 24.75);
	
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
	
}

function init_search(){

	$('.view_search_button').on('click', function(){
        show_results(g_marker.getPosition().lat(), g_marker.getPosition().lng(), $('.view_search_input').val());
	});

	$('.view_all_button').on('click', reset_map);
	
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

				// div
				$view_results.append('<div class="view_result" data-id="' + this.id + '"><div class="view_result_info">' + this.info + '</div>' +
						'<div class="view_result_dist">' + this.dist.toFixed(1) + 'km</div></div>');

				// markers
				
				if (typeof g_markers[this.id] == 'undefined' ){
	                g_markers[this.id] = new google.maps.Marker({
	                    position: new google.maps.LatLng(this.lat, this.lng),
	                    icon: 'https://img.icons8.com/material-rounded/48/000000/marker.png',
	                    map: g_map
	                });
				} else {
					g_markers[this.id].setVisible(true);
				}
				
			});

			reset_map();

		}
	});

}

function reset_map(){

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

</script>