<?php

if( php_sapi_name() !== 'cli' ) {
	die("Meant to be run from command line\n");
}

// Environment setup
define( 'BASE_PATH', find_wordpress_base_path()."/" );
define('WP_USE_THEMES', false);
global $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header, $wpdb;
require(BASE_PATH . 'wp-load.php');

$result = '';

// Multisite loop
if (is_multisite()) {
	$site_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
	foreach ($site_ids as $site_id) {
		switch_to_blog($site_id);

		$result .= "Adding meta for site: ".get_bloginfo( 'name' )."\n";
		$result .= site_add_meta_action();
		$result .= "Finished for site: ".get_bloginfo( 'name' )."\n\n";
		restore_current_blog();
	}
} else {
	// activated on a single site
	$result .= site_add_meta_action();
}

echo $result;

function site_add_meta_action(): string {
	// Get recurring orders
	$orders = wc_get_orders( array(
		'numberposts' => -1,
		'meta_key' => 'AS_RecurringActive',
		'meta_compare' => 'EXISTS',
	) );

	// Loop through each initial recurring order
	$output = '';

	foreach ( $orders as $order ) {
		$ref_id = get_post_meta($order->get_id(), 'AS_ReferenceID', true);
		$duplicates = addDuplicateMeta( $order->get_id(), $ref_id );
		$output .= "Added meta for orders: " . implode(', ', $duplicates) . "\n";
	}

	$output .= "\ntotal: " . count($orders) . "\n";

	return $output;
}

function addDuplicateMeta( $base_order_id, $reference_id ){
	$output = [];

	// Get duplicate orders by reference ID
	$orders = wc_get_orders( array(
		'numberposts' => -1,
		'meta_key' => 'AS_ReferenceID',
		'meta_value' => $reference_id,
	) );

	foreach ( $orders as $order ) {
		if( $order->get_id() != $base_order_id) {
			update_post_meta($order->get_id(), 'AS_RecurringDuplicate', 'yes');

			$output[] = $order->get_id();
		}
	}

	return $output;
}

function find_wordpress_base_path() {
	$dir = dirname(__FILE__);
	do {
		//it is possible to check for other files here
		if( file_exists($dir."/wp-config.php") ) {
			return $dir;
		}
	} while( $dir = realpath("$dir/..") );
	return null;
}
