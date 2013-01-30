<?php

function wp_export( $args = array() ) {
	$defaults = array(
		'filters' => array(),
		'format' => 'WP_WXR_XML_Generator',
		'writer' => 'WP_Export_Returner',
		'writer_args' => null,
	);
	$args = wp_parse_args( $args, $defaults );
	$export_query = new WP_Export_Query( $args['filters'] );
	$generator = new $args['format']( $export_query );
	$writer = new $args['writer']( $generator, $args['writer_args'] );
	try {
		return $writer->export();
	} catch ( WP_Export_Exception $e ) {
		return new WP_Error( 'wp-export-error', $e->getMessage() );
	}
}
