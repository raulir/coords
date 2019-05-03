<?php

if (!file_exists('cache')){
	mkdir('cache');
}

// controllers

$do = !empty($_POST['do']) ? $_POST['do'] : '';

if ($do == 'place_autocomplete'){
	
	$location = htmlentities(trim($_POST['location']));
	$location = mb_ereg_replace('[&;]', '-', $location);
	$location = mb_ereg_replace('[^\w\s\d\-_,\(\)\.]', '_', $location);
	$location = mb_ereg_replace('\s+', '_', $location);
	
	$filename = 'cache/a_'.$location.'.json';
	
	if (file_exists($filename)){
		readfile($filename);
		die();
	}
	
	$data = file_get_contents('https://maps.googleapis.com/maps/api/place/autocomplete/json?input=' .
			urlencode($_POST['location']) . '&components=country:' . urlencode($_POST['country']) . '&language=' . urlencode($_POST['language']) . 
			'&key=AIzaSyAycRIejSHGMWJ43a3yQzPBubZ7NjyEn44');
	
	$data = json_decode($data, true);
	
	$data['cached'] = date('Y-m-d H:i:s');
	
	$data = json_encode($data, JSON_PRETTY_PRINT);
	
	file_put_contents($filename, $data);
	
	print($data);
	die();
	
} elseif ($do == 'place_coordinates'){

	$filename = htmlentities(trim($_POST['place_id']));
	$filename = mb_ereg_replace('[^\w\d\-_,\(\)\.]', '_', $filename);

	$filename = 'cache/p_'.$filename.'.json';

	if (file_exists($filename)){
		readfile($filename);
		die();
	}

	$data = file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?place_id='.urlencode($_POST['place_id']).'&key=AIzaSyAycRIejSHGMWJ43a3yQzPBubZ7NjyEn44');

	$data = json_decode($data, true);

	$data['cached'] = date('Y-m-d H:i:s');

	$data = json_encode($data, JSON_PRETTY_PRINT);

	file_put_contents($filename, $data);

	print($data);
	die();

} elseif ($do == 'save_data'){

	$filename = 'data.json';

	if (file_exists($filename)){
		$data = file_get_contents($filename);
		$data = json_decode($data, true);
	} else {
		$data = [];
	}
	
	$data[] = [
			'long' => $_POST['long'],
			'lat' => $_POST['lat'],
			'info' => $_POST['info'],
			'added' => date('Y-m-d H:i:s'),
	];

	$data = json_encode($data, JSON_PRETTY_PRINT);

	file_put_contents($filename, $data);

	print($data);
	die();

}

?>
<!-- style -->

<style>

	.profile_search {
		position: relative;
	}
	
	.profile_search_autocomplete {
		position: absolute;
		z-index: 10;
		left: 0;
		top: 120%;
		width: 200px;
	}
	
	.profile_search_result {
		cursor: pointer;
		border: 1px solid grey;
		background-color: white;
	}

	.profile_map {
		width: 500px;
		height: 500px;
		border: 1px solid black;
	}
	
	.profile_map_pin_drag {
		position: absolute;
		z-index: 10;
		opacity: 0;
	}
	
	.profile_info_input {
		width: 450px;
		height: 40px;
	}
	
	.profile_info_button {
		cursor: pointer;
	}

</style>

<!-- template -->

<div class="profile_container">

	<div class="profile_search">
		<input class="profile_search_input" type="text" placeholder="enter place or postcode">
		<div class="profile_search_autocomplete"></div>
	</div>
	
	<div class="profile_map">
		
	</div>
	<img class="profile_map_pin_drag" src="https://img.icons8.com/color/48/000000/marker.png">
		
	<div class="profile_coordinates">
		<div class="profile_coordinates_long">
			long:
			<input class="profile_coordinates_long" type="text" readonly="readonly">
			lat:
			<input class="profile_coordinates_lat" type="text" readonly="readonly">
		</div>
	</div>
	
	<div class="profile_info">
		info:
		<textarea class="profile_info_input" type="text" placeholder="enter location info"></textarea>
		<div class="profile_info_button">[save]</div>
	</div>

</div>

<!-- javascript -->

<script src="https://code.jquery.com/jquery-3.4.1.js"></script>
<script async defer src="https://maps.google.com/maps/api/js?key=AIzaSyAycRIejSHGMWJ43a3yQzPBubZ7NjyEn44&callback=init_map"></script>
<script type="text/javascript">

var country = 'ee';
var language = 'et';

var $profile_search_input = $('.profile_search_input');
var $profile_search_autocomplete = $('.profile_search_autocomplete');

// easy init
setTimeout(init_autocomplete, 1000);
setTimeout(init_save, 1000);

// autocomplete
function init_autocomplete(){

	$profile_search_input.on('keyup change', function(e){
		run_search(e)
	});

}

function init_save(){

	$('.profile_info_button').on('click', save_data);
	
}

function init_map(){
	
	// google maps stuff
    var coords = new google.maps.LatLng(59.44, 24.75);
	
    var mapOptions = {
        zoom: 17,
        center: coords,
        streetViewControl: false,
        mapTypeId: google.maps.MapTypeId.ROADMAP,
        draggable: true
    }

    g_map = new google.maps.Map(
        $('.profile_map').get(0), mapOptions
    );

	$('.profile_map_pin_drag').appendTo('.profile_map');
	google.maps.event.trigger(g_map, 'idle');

    google.maps.event.addListener(g_map, 'dragstart', function() {
    	
    	// put temporary marker
    	$('.profile_map_pin_drag').css({
    		'opacity': '0.3'
    	});
    	
    });
    
    google.maps.event.addListener(g_map, 'dragend', function() {

    	// sometimes this won't call itself
    	setTimeout(function(){
    		google.maps.event.trigger(g_map, 'idle')
    	}, 100);

    });
    	
    google.maps.event.addListener(g_map, 'idle', function() {
    	
   		// hide temporary marker
   		$('.profile_map_pin_drag').css({
   			'bottom': $('.profile_map').height() / 2 + 'px',
    		'left': ($('.profile_map').width() - $('.profile_map_pin_drag').width())/2 + 'px',
    		'opacity': '1'
        });
    	
    	var position = g_map.getCenter();
    	$('.profile_coordinates_long').val(position.lng().toFixed(4));
    	$('.profile_coordinates_lat').val(position.lat().toFixed(4));

    });

    // if map area size can change
    google.maps.event.addDomListener(window, 'resize', function() {
    	var center = g_map.getCenter();
    	google.maps.event.trigger(g_map, 'resize');
    	g_map.setCenter(center); 
    });
    
}

function run_search(e){

	var code = (e.keyCode ? e.keyCode : e.which);
	
	if ($profile_search_input.val().length >= 3){

		if (!$profile_search_input.data('busy')){

			$profile_search_input.data('busy', true);

			search_me($profile_search_input.val(), {
				'location': $profile_search_input.val(),
				'success': function(data){

					$profile_search_input.data('busy', false);

					if ($profile_search_input.data('queue')){
						$profile_search_input.data('queue', false);
						run_search(e);
					}

					$profile_search_autocomplete.html('');
					
					display_autocomplete(data);

					if (code == 13){
						var id = $('.profile_search_result').first().data('id');
						$profile_search_autocomplete.html('');
						show_location(id);
					}
					
				}
			});

		} else {
			
			$profile_search_input.data('queue', true);

		}
		
	} else {

		$profile_search_autocomplete.html('');
		
	}
	
}

function search_me(location, params){
	
	var params = $.extend({'success': function(){}, 'failure': function(){}}, params);
	
	$.ajax({
		type: 'POST',
	  	url: window.location,
	  	dataType: 'json',
	  	data: {
		  	'do': 'place_autocomplete',
		  	'location': params.location,
		  	'country': country,
		  	'language': language
		},
	  	context: this,
	  	success: params.success,
	  	failure: params.failure
	});
	
}

function display_autocomplete(data){
	
	if (data.status == 'OK'){
		$(data.predictions).each(function(){
			$profile_search_autocomplete.append('<div class="profile_search_result" data-id="' + this.place_id + '">' + this.description + '</div>')
		});
	}
	
}

function show_location(place_id){

	// get place_id coordinates
	$.ajax({
		type: 'POST',
	  	url: window.location,
	  	dataType: 'json',
	  	data: {
		  	'do': 'place_coordinates',
		  	'place_id': place_id
		},
	  	context: this,
	  	success: function(data){

	  		if (data.status == 'OK'){
		  	
	  			var coords = new google.maps.LatLng(data.results[0].geometry.location.lat, data.results[0].geometry.location.lng);
	  			g_map.setCenter(coords);
	  			
	  		}
	  		
		}
	});
	
}

function save_data(){

	if (!$('.profile_info_input').val()){
		$('.profile_info_input').css({'border':'1px solid red'});
		setTimeout(function(){
			$('.profile_info_input').css({'border':''});
		}, 500);
	}

	$.ajax({
		type: 'POST',
	  	url: window.location,
	  	dataType: 'json',
	  	data: {
		  	'do': 'save_data',
		  	'long': $('.profile_coordinates_long').val(),
		  	'lat': $('.profile_coordinates_lat').val(),
		  	'info': $('.profile_info_input').val()
		},
	  	context: this,
	  	success: function(data){

	  		$('.profile_info_input').val('');

			$('.profile_info_button').css({'color':'green'});
			setTimeout(function(){
				$('.profile_info_button').css({'color':''});
			}, 500);
	  		
		}
	});
	
}

</script>
