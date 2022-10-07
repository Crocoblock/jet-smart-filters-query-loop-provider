<?php
/**
 * Plugin Name: JetSmartFilters - Query Loop provider
 * Plugin URI:  https://crocoblock.com/plugins/jetbooking/
 * Description: Add JSF provider for Query Loop block.
 * Version:     1.0.0
 * Author:      Crocoblock
 * Author URI:  https://crocoblock.com/
 * License:     GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die();
}

define( 'JSF_QUERY_LOOP_PROVIDER_PATH', plugin_dir_path( __FILE__ ) );
define( 'JSF_QUERY_LOOP_PROVIDER_ID', 'query-loop' );
define( 'JSF_QUERY_LOOP_PROVIDER_NAME', 'Query Loop' );


/**
 * Register custom provider
 */
add_action( 'jet-smart-filters/providers/register', function( $providers_manager ) {
	$providers_manager->register_provider(
		'JSF_Query_Loop_Provider', // Custom provider class name
		JSF_QUERY_LOOP_PROVIDER_PATH . 'provider.php' // Path to file where this class defined
	);
} );

/**
 * Its legacy part. Initially SmartFilters worked only with Elementor.
 * Now builder support is extended and we'll rewrite this part a bit in the future.
 * Providers used this hook will continiue to work but will be added more obvious interface to add builders support
 */
add_filter( 'jet-smart-filters/blocks/allowed-providers', function( $providers ) {
	$providers[ JSF_QUERY_LOOP_PROVIDER_ID ] = JSF_QUERY_LOOP_PROVIDER_NAME;
	return $providers;
} );
