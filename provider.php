<?php
/**
 * Query Loop block provider.
 * Each provider ideally should complete next tasks
 * - Have some related element (usually some list of posts, users, terms etc) which will be reloaded when filter with current provider will be applied
 * - Have some trigger flag to understand related element is filterable (because on the page could be a few different instances of target element)
 * - On default render of related element store the data required to render the same element with AJAX. To store this data you need to use jet_smart_filters()->providers->add_provider_settings() method. See the store_defaults method of current example
 * - Implement ajax_get_content() method. This method called on AJAX request from filter with current provider. This method should apply filtered arguments to element query and than render element HTML according changed query. This HTML returned to front and replaces default element content\
 * - Implement apply_filters_in_request() method. This method responsible for applying filtered attributes on page reload filter type. The main difference from ajax_get_content() - this method not renders HTML, just applying filtered arguments to query
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Any custom provider class should extend base provider class
 */
class JSF_Query_Loop_Provider extends Jet_Smart_Filters_Provider_Base {

	/**
	 * Custom CSS class. Allows to separate regular Query Loop block from filtered.
	 * In this case its a tigger flag described in head of the file.
	 * Its optional part required for exact provider.
	 * 
	 * To make selected Query Loop block filterable you need to add this class to block in block sidebar
	 * 
	 * @var string
	 */
	protected $trigger_css_class = 'jsf-active';

	/**
	 * Allow to add specific query ID to block.
	 * Query ID required if you have 2+ filtered blocks of same provider on the page
	 * Example of CSS class with query ID - 'jsf-query--my-query'. 'jsf-query--my-query' - its exact query ID.
	 * You need to set the same query ID into appropriate filter Query ID control. 
	 * Then this filter will applied only for block with this class
	 * Its optional part implemented in this way for exact provider. Implementation for other providers may be different.
	 * Prefix required because Query Loop block may contein other class which not related to Query ID
	 * 
	 * @var string
	 */
	protected $query_id_class_prefix = 'jsf-query--';

	protected $rendered_block = null;
	
	/**
	 * Add hooks specific for exact provider
	 */
	public function __construct() {

		if ( ! jet_smart_filters()->query->is_ajax_filter() ) {

			/**
			 * First of all you need to store default provider query and required attributes to allow
			 * JetSmartFilters attach this data to AJAX request.
			 * 
			 * This part is unique for each provider because each one implements query and attributes processing in own way
			 *
			 * This example for Query Loop block, another block may have another implementation
			 */
			add_filter( 'render_block_core/query', array( $this, 'store_defaults' ), 0, 3 );

		}
	}

	/**
	 * Store default block attributes to add them to filters AJAX request
	 */
	public function store_defaults( $content, $parsed_block, $block_instance ) {

		$class_name = ! empty( $parsed_block['attrs']['className'] ) ? $parsed_block['attrs']['className'] : '';

		if ( false === strpos( $class_name, $this->trigger_css_class ) ) {
			return $content;
		}

		$query_id = $this->get_query_id_from_class_name( $class_name );

		/**
		 * We'll parse required block settings from page content.
		 * In this case such approach used because we need inner content anyway.
		 * If your block content defined only with attributes - here you can set array of these attributes
		 * and store it with jet_smart_filters()->providers->add_provider_settings(), than filter add these attributes 
		 * to request and you'll can create new instane of required block without content parsing
		 */
		$attrs = array(
			'filtered_post_id' => get_the_ID(),
		);

		jet_smart_filters()->providers->add_provider_settings( $this->get_id(), $attrs, $query_id );

		return $content;

	}

	/**
	 * Extract filters Query ID from block class name
	 * This part is specific for exact provider
	 * 
	 * @param  string $class_name
	 * @return string
	 */
	public function get_query_id_from_class_name( $class_name ) {
		
		$class_names = explode( ' ', $class_name );
		$class_names = array_map( 'trim', $class_names );

		foreach ( $class_names as $css_class ) {
			if ( false !== strpos( $css_class, $this->query_id_class_prefix ) ) {
				return $css_class;
			}
		}

		// If no custom query ID found - return 'default' sring which will be used as Query ID.
		return 'default';

	}

	/**
	 * Set prefix for unique ID selector. Mostly is default '#' sign, but sometimes class '.' sign needed.
	 * For example for Query Loop block we don't have HTML/CSS ID attribute, so we need to use class as unique identifier.
	 */
	public function id_prefix() {
		return '.';
	}

	/**
	 * Get provider name
	 * @required: true
	 */
	public function get_name() {
		return JSF_QUERY_LOOP_PROVIDER_NAME;
	}

	/**
	 * Get provider ID
	 * @required: true
	 */
	public function get_id() {
		return JSF_QUERY_LOOP_PROVIDER_ID;
	}

	/**
	 * Get filtered provider content.
	 * jet_smart_filters() parse arguments from request and combine into array in the same format and same structures as arguments for WP_Query class
	 * parsed argumnets could be retrieved with jet_smart_filters()->query->get_query_args() method.
	 * 
	 * @required: true
	 */
	public function ajax_get_content() {

		$block = $this->get_block_by_attributes();

		if ( $block ) {
			add_filter( 'pre_get_posts', array( $this, 'add_query_args' ), 10 );
			echo $block->render();
		} else {
			echo 'Block not found';
		}
		
		
	}

	/**
	 * Get Query Loop block instance.
	 * Its optional method. Unique for exact provider.
	 * @return [type] [description]
	 */
	public function get_block_by_attributes() {
		
		$attributes = jet_smart_filters()->query->get_query_settings();
		$post_id    = ! empty( $attributes['filtered_post_id'] ) ? absint( $attributes['filtered_post_id'] ) : get_the_ID();

		if ( ! $post_id ) {
			return false;
		}

		$post = get_post( $post_id );

		$parsed_blocks = parse_blocks( $post->post_content );

		if ( empty( $parsed_blocks ) ) {
			return false;
		}

		return $this->recursive_find_block( $parsed_blocks );

	}

	/**
	 * Return filtered block instance from blocks list
	 * Its optional method. Unique for exact provider.
	 * 
	 * @param  [type] $blocks       [description]
	 * @return [type]               [description]
	 */
	public function recursive_find_block( $blocks ) {
		
		foreach ( $blocks as $block ) {
			
			if ( $this->is_filtered_block( $block ) ) {
				return new WP_Block( $block );
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				
				$inner_block = $this->recursive_find_block( $block['innerBlocks'] );

				if ( $inner_block ) {
					return new WP_Block( $inner_block );
				}

			}

		}

		return false;
	}

	/**
	 * Check if is currently filtered block
	 * Its optional method. Unique for exact provider.
	 * 
	 * @param  array  $block Parsed block
	 * @return boolean
	 */
	public function is_filtered_block( $block ) {
		
		$block_class = ! empty( $block['attrs']['className'] ) ? $block['attrs']['className'] : '';
		$block_name  = $block['blockName'];
		$query_id    = jet_smart_filters()->query->get_current_provider( 'query_id' );

		if ( 'core/query' !== $block_name ) {
			return false;
		}

		if ( ! $block_class || false === strpos( $block_class, $this->trigger_css_class ) ) {
			return false;
		}

		if ( 'default' === $query_id ) {
			return true;
		} else {
			return ( false !== strpos( $block_class, $query_id ) );
		}

	}

	/**
	 * Apply filters on page reload
	 * Filter arguments in this case pased with $_GET request.
	 * jet_smart_filters() parse arguments from request and combine into array in the same format and same structures as arguments for WP_Query class
	 * parsed argumnets could be retrieved with jet_smart_filters()->query->get_query_args() method.
	 * 
	 * @required: true
	 */
	public function apply_filters_in_request() {

		$args = jet_smart_filters()->query->get_query_args();

		if ( ! $args ) {
			return;
		}

		add_filter( 'pre_render_block', function( $content, $block ) {
			
			/**
			 * Here we checking - if will be rendered filtered block - we hook 'add_query_args' method
			 * to modify block query.
			 */
			if ( $this->is_filtered_block( $block ) ) {
				add_filter( 'pre_get_posts', array( $this, 'add_query_args' ), 10 );
			}

			return $content;

		}, 10, 2 );

	}

	/**
	 * Add custom query arguments to query object/array of related element.
	 * This example based on the WP_Query and related hooks. add_query_args callback attached to appropiate hooks inside apply_filters_in_request() and ajax_get_content() methods
	 * This methos used by both - AJAX and page reload filters to add filter request data to query.
	 * You need to check - should it be applied or not before hooking on 'pre_get_posts'
	 * 
	 * @required: true
	 */
	public function add_query_args( $query ) {

		/**
		 * With this method we can get prepared query arguments from filters request.
		 * This method returns only filtered query argumnets, not whole query.
		 * Arguments returned in the format prepared for WP_Query usage. If you need to use it in some other way -
		 * you need to manually parse this arguments into required format.
		 *
		 * All custom query variables will be gathered under 'meta_query'
		 * 
		 * @var array
		 */
		$args = jet_smart_filters()->query->get_query_args();

		if ( empty( $args ) ) {
			return;
		}

		foreach ( $args as $query_var => $value ) {
		
			if ( in_array( $query_var, array( 'tax_query', 'meta_query' ) ) ) {
				
				$current = $query->get( $query_var );

				if ( ! empty( $current ) ) {
					$value = array_merge( $current, $value );
				}

				$query->set( $query_var, $value );
			} else {
				$query->set( $query_var, $value );
			}

		}

		remove_filter( 'pre_get_posts', array( $this, 'add_query_args' ), 10 );
	}

	/**
	 * Get provider wrapper selector
	 * Its CSS selector of related HTML element with provider content.
	 * @required: true
	 */
	public function get_wrapper_selector() {
		return '.wp-block-query.' . $this->trigger_css_class;
	}

}
