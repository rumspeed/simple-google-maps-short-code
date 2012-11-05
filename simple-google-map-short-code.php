<?php
/*
Plugin Name: Simple Google Maps Short Code
Plugin URL: http://pippinsplugins.com/simple-google-maps-short-code
Description: Adds a simple Google Maps short code
Version: 1.0.1
Author: Pippin Williamson
Author URI: http://pippinsplugins.com
Contributors: mordauk
*/


/**
 * Displays the map
 *
 * @access      private
 * @since       1.0 
 * @return      void
*/

function pw_map_shortcode( $atts ) {

	$atts = shortcode_atts(
		array(
			'address' 	=> false,
			'width' 	=> '100%',
			'height' 	=> '400px'
		),
		$atts
	);

	$address = $atts['address'];

	if( $address ) :

		wp_print_scripts( 'google-maps-api' );

		$coordinates = pw_map_get_coordinates( $address );

		if( !is_array( $coordinates ) )
			return;

		$map_id = uniqid( 'pw_map_' ); // generate a unique ID for this map

		ob_start(); ?>
		<div class="pw_map_canvas" id="<?php echo esc_attr( $map_id ); ?>" style="height: <?php echo esc_attr( $atts['height'] ); ?>; width: <?php echo esc_attr( $atts['width'] ); ?>"></div>
	    <script type="text/javascript">
			var map_<?php echo $map_id; ?>;
			function pw_run_map_<?php echo $map_id ; ?>(){
				var location = new google.maps.LatLng("<?php echo $coordinates['lat']; ?>", "<?php echo $coordinates['lng']; ?>");
				var map_options = {
					zoom: 15,
					center: location,
					mapTypeId: google.maps.MapTypeId.ROADMAP
				}
				map_<?php echo $map_id ; ?> = new google.maps.Map(document.getElementById("<?php echo $map_id ; ?>"), map_options);
				var marker = new google.maps.Marker({
				position: location,
				map: map_<?php echo $map_id ; ?>
				});
			}
			pw_run_map_<?php echo $map_id ; ?>();
		</script>
		<?php
	endif;
	return ob_get_clean();
}
add_shortcode( 'pw_map', 'pw_map_shortcode' );


/**
 * Loads Google Map API
 *
 * @access      private
 * @since       1.0 
 * @return      void
*/

function pw_map_load_scripts() {
	wp_register_script( 'google-maps-api', 'http://maps.google.com/maps/api/js?sensor=false' );
}
add_action( 'wp_enqueue_scripts', 'pw_map_load_scripts' );



/**
 * Retrieve coordinates for an address
 *
 * Coordinates are cached using transients and a hash of the address
 *
 * @access      private
 * @since       1.0 
 * @return      void
*/

function pw_map_get_coordinates( $address, $force_refresh = false ) {
	
    $address_hash = md5( $address );

    $coordinates = get_transient( $address_hash );

    if ($force_refresh || $coordinates === false) {
    	
    	$url 		= 'http://maps.google.com/maps/geo?q=' . urlencode($address) . '&output=xml';
     	$response 	= wp_remote_get( $url );

     	if( is_wp_error( $response ) )
     		return;

     	$xml = wp_remote_retrieve_body( $response );

     	if( is_wp_error( $xml ) )
     		return;

		if ( $response['response']['code'] == 200 ) {

			$data = new SimpleXMLElement( $xml );

			if ( $data->Response->Status->code == 200 ) {

			  	$coordinates = $data->Response->Placemark->Point->coordinates;

			  	//Placemark->Point->coordinates;
			  	$coordinates 			= explode(',', $coordinates[0]);
			  	$cache_value['lat'] 	= $coordinates[1];
			  	$cache_value['lng'] 	= $coordinates[0];
			  	$cache_value['address'] = (string) $data->Response->Placemark->address[0];

			  	// cache coordinates for 3 months
			  	set_transient($address_hash, $cache_value, 3600*24*30*3);
			  	$data = $cache_value;

			} elseif ($data->Response->Status->code == 602) {
			  	return sprintf( __( 'Unable to parse entered address. API response code: %s', 'pw-maps' ), @$data->Response->Status->code );
			} else {
			   	return sprintf( __( 'XML parsing error. Please try again later. API response code: %s', 'pw-maps' ), @$data->Response->Status->code );
			}

		} else {
		 	return __( 'Unable to contact Google API service.', 'pw-maps' );
		}

    } else {
       // return cached results
       $data = $coordinates;
    }

    return $data;
}


/**
 * Fixes a problem with responsive themes
 *
 * @access      private
 * @since       1.0.1 
 * @return      void
*/

function pw_map_css() {
	echo '<style type="text/css">/* =Responsive Map fix
-------------------------------------------------------------- */
.pw_map_canvas img {
	max-width: none;
}</style>';

}
add_action( 'wp_head', 'pw_map_css' );