<?php

namespace SOS\Analytics\Admin\API\Reports\Subscriptions\Stats;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Admin\API\Reports\ParameterException;

/**
 * REST API Reports orders stats controller class.
 *
 * @internal
 * @extends \Automattic\WooCommerce\Admin\API\Reports\Controller
 */
class Controller extends \Automattic\WooCommerce\Admin\API\Reports\Controller {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'reports/subscriptions/stats';

	/**
	 * Maps query arguments from the REST request.
	 *
	 * @param array $request Request array.
	 * @return array
	 */
	protected function prepare_reports_query( $request ) {
		$args                        = array();
		$args['before']              = $request['before'];
		$args['after']               = $request['after'];
		$args['interval']            = $request['interval'];
		$args['page']                = $request['page'];
		$args['per_page']            = $request['per_page'];
		$args['orderby']             = $request['orderby'];
		$args['order']               = $request['order'];
		$args['fields']              = $request['fields'];
		$args['match']               = $request['match'];
		$args['status_is']           = (array) $request['status_is'];
		$args['status_is_not']       = (array) $request['status_is_not'];
		$args['product_includes']    = (array) $request['product_includes'];
		$args['product_excludes']    = (array) $request['product_excludes'];
		$args['variation_includes']  = (array) $request['variation_includes'];
		$args['variation_excludes']  = (array) $request['variation_excludes'];
		$args['coupon_includes']     = (array) $request['coupon_includes'];
		$args['coupon_excludes']     = (array) $request['coupon_excludes'];
		$args['tax_rate_includes']   = (array) $request['tax_rate_includes'];
		$args['tax_rate_excludes']   = (array) $request['tax_rate_excludes'];
		$args['customer_type']       = $request['customer_type'];
		$args['refunds']             = $request['refunds'];
		$args['attribute_is']        = (array) $request['attribute_is'];
		$args['attribute_is_not']    = (array) $request['attribute_is_not'];
		$args['category_includes']   = (array) $request['categories'];
		$args['segmentby']           = $request['segmentby'];
		$args['force_cache_refresh'] = $request['force_cache_refresh'];

		// For backwards compatibility, `customer` is aliased to `customer_type`.
		if ( empty( $request['customer_type'] ) && ! empty( $request['customer'] ) ) {
			$args['customer_type'] = $request['customer'];
		}

		return $args;
	}

	/**
	 * Get all reports.
	 *
	 * @param WP_REST_Request $request Request data.
	 * @return array|WP_Error
	 */
	public function get_items( $request ) {
		$query_args   = $this->prepare_reports_query( $request );
		$orders_query = new Query( $query_args );
		try {
			$report_data = $orders_query->get_data();
		} catch ( ParameterException $e ) {
			return new \WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
		}

		$out_data = array(
			'totals'    => get_object_vars( $report_data->totals ),
			'intervals' => array(),
		);

		foreach ( $report_data->intervals as $interval_data ) {
			$item                    = $this->prepare_item_for_response( $interval_data, $request );
			$out_data['intervals'][] = $this->prepare_response_for_collection( $item );
		}

		return $this->add_pagination_headers(
			$request,
			$out_data,
			(int) $report_data->total,
			(int) $report_data->page_no,
			(int) $report_data->pages
		);
	}

	/**
	 * Prepare a report object for serialization.
	 *
	 * @param Array           $report  Report data.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $report, $request ) {
		$data = $report;

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		/**
		 * Filter a report returned from the API.
		 *
		 * Allows modification of the report data right before it is returned.
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param object           $report   The original report object.
		 * @param WP_REST_Request  $request  Request used to generate the response.
		 */
		return apply_filters( 'woocommerce_rest_prepare_report_orders_stats', $response, $report, $request );
	}

	/**
	 * Get the Report's schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$data_values = array(
			'net_revenue'         => array(
				'description' => __( 'Net sales.', 'sos-analytics' ),
				'type'        => 'number',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
				'format'      => 'currency',
			),
			'orders_count'        => array(
				'title'       => __( 'Orders', 'sos-analytics' ),
				'description' => __( 'Number of orders', 'sos-analytics' ),
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
				'indicator'   => true,
			),
			'avg_order_value'     => array(
				'description' => __( 'Average order value.', 'sos-analytics' ),
				'type'        => 'number',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
				'indicator'   => true,
				'format'      => 'currency',
			),
			'avg_items_per_order' => array(
				'description' => __( 'Average items per order', 'sos-analytics' ),
				'type'        => 'number',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'num_items_sold'      => array(
				'description' => __( 'Number of items sold', 'sos-analytics' ),
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'coupons'             => array(
				'description' => __( 'Amount discounted by coupons.', 'sos-analytics' ),
				'type'        => 'number',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'coupons_count'       => array(
				'description' => __( 'Unique coupons count.', 'sos-analytics' ),
				'type'        => 'number',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'total_customers'     => array(
				'description' => __( 'Total distinct customers.', 'sos-analytics' ),
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'products'            => array(
				'description' => __( 'Number of distinct products sold.', 'sos-analytics' ),
				'type'        => 'number',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
		);

		$segments = array(
			'segments' => array(
				'description' => __( 'Reports data grouped by segment condition.', 'sos-analytics' ),
				'type'        => 'array',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'segment_id' => array(
							'description' => __( 'Segment identificator.', 'sos-analytics' ),
							'type'        => 'integer',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
						'subtotals'  => array(
							'description' => __( 'Interval subtotals.', 'sos-analytics' ),
							'type'        => 'object',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
							'properties'  => $data_values,
						),
					),
				),
			),
		);

		$totals = array_merge( $data_values, $segments );

		// Products is not shown in intervals.
		unset( $data_values['products'] );

		$intervals = array_merge( $data_values, $segments );

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'report_orders_stats',
			'type'       => 'object',
			'properties' => array(
				'totals'    => array(
					'description' => __( 'Totals data.', 'sos-analytics' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
					'properties'  => $totals,
				),
				'intervals' => array(
					'description' => __( 'Reports data grouped by intervals.', 'sos-analytics' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'interval'       => array(
								'description' => __( 'Type of interval.', 'sos-analytics' ),
								'type'        => 'string',
								'context'     => array( 'view', 'edit' ),
								'readonly'    => true,
								'enum'        => array( 'day', 'week', 'month', 'year' ),
							),
							'date_start'     => array(
								'description' => __( "The date the report start, in the site's timezone.", 'sos-analytics' ),
								'type'        => 'date-time',
								'context'     => array( 'view', 'edit' ),
								'readonly'    => true,
							),
							'date_start_gmt' => array(
								'description' => __( 'The date the report start, as GMT.', 'sos-analytics' ),
								'type'        => 'date-time',
								'context'     => array( 'view', 'edit' ),
								'readonly'    => true,
							),
							'date_end'       => array(
								'description' => __( "The date the report end, in the site's timezone.", 'sos-analytics' ),
								'type'        => 'date-time',
								'context'     => array( 'view', 'edit' ),
								'readonly'    => true,
							),
							'date_end_gmt'   => array(
								'description' => __( 'The date the report end, as GMT.', 'sos-analytics' ),
								'type'        => 'date-time',
								'context'     => array( 'view', 'edit' ),
								'readonly'    => true,
							),
							'subtotals'      => array(
								'description' => __( 'Interval subtotals.', 'sos-analytics' ),
								'type'        => 'object',
								'context'     => array( 'view', 'edit' ),
								'readonly'    => true,
								'properties'  => $intervals,
							),
						),
					),
				),
			),
		);

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Get the query params for collections.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params                     = array();
		$params['context']          = $this->get_context_param( array( 'default' => 'view' ) );
		$params['page']             = array(
			'description'       => __( 'Current page of the collection.', 'sos-analytics' ),
			'type'              => 'integer',
			'default'           => 1,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
			'minimum'           => 1,
		);
		$params['per_page']         = array(
			'description'       => __( 'Maximum number of items to be returned in result set.', 'sos-analytics' ),
			'type'              => 'integer',
			'default'           => 10,
			'minimum'           => 1,
			'maximum'           => 100,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['after']            = array(
			'description'       => __( 'Limit response to resources published after a given ISO8601 compliant date.', 'sos-analytics' ),
			'type'              => 'string',
			'format'            => 'date-time',
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['before']           = array(
			'description'       => __( 'Limit response to resources published before a given ISO8601 compliant date.', 'sos-analytics' ),
			'type'              => 'string',
			'format'            => 'date-time',
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['order']            = array(
			'description'       => __( 'Order sort attribute ascending or descending.', 'sos-analytics' ),
			'type'              => 'string',
			'default'           => 'desc',
			'enum'              => array( 'asc', 'desc' ),
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['orderby']          = array(
			'description'       => __( 'Sort collection by object attribute.', 'sos-analytics' ),
			'type'              => 'string',
			'default'           => 'date',
			'enum'              => array(
				'date',
				'new_subscriptions',
				'new_trials',
				'expired_trials',
				'revenue',
				'refunds',
				'switches',
				'cancellations',
				'on_hold',
				'expired',
				'resubscribes',
			),
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['interval']         = array(
			'description'       => __( 'Time interval to use for buckets in the returned data.', 'sos-analytics' ),
			'type'              => 'string',
			'default'           => 'week',
			'enum'              => array(
				'hour',
				'day',
				'week',
				'month',
				'quarter',
				'year',
			),
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['match']            = array(
			'description'       => __( 'Indicates whether all the conditions should be true for the resulting set, or if any one of them is sufficient. Match affects the following parameters: status_is, status_is_not, product_includes, product_excludes, coupon_includes, coupon_excludes, customer, categories', 'sos-analytics' ),
			'type'              => 'string',
			'default'           => 'all',
			'enum'              => array(
				'all',
				'any',
			),
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['status_is']        = array(
			'description'       => __( 'Limit result set to items that have the specified order status.', 'sos-analytics' ),
			'type'              => 'array',
			'sanitize_callback' => 'wp_parse_slug_list',
			'validate_callback' => 'rest_validate_request_arg',
			'default'           => null,
			'items'             => array(
				'enum' => self::get_order_statuses(),
				'type' => 'string',
			),
		);
		$params['status_is_not']    = array(
			'description'       => __( 'Limit result set to items that don\'t have the specified order status.', 'sos-analytics' ),
			'type'              => 'array',
			'sanitize_callback' => 'wp_parse_slug_list',
			'validate_callback' => 'rest_validate_request_arg',
			'items'             => array(
				'enum' => self::get_order_statuses(),
				'type' => 'string',
			),
		);
		$params['product_includes'] = array(
			'description'       => __( 'Limit result set to items that have the specified product(s) assigned.', 'sos-analytics' ),
			'type'              => 'array',
			'items'             => array(
				'type' => 'integer',
			),
			'default'           => array(),
			'sanitize_callback' => 'wp_parse_id_list',

		);
		$params['product_excludes']    = array(
			'description'       => __( 'Limit result set to items that don\'t have the specified product(s) assigned.', 'sos-analytics' ),
			'type'              => 'array',
			'items'             => array(
				'type' => 'integer',
			),
			'default'           => array(),
			'sanitize_callback' => 'wp_parse_id_list',
		);
		$params['variation_includes']  = array(
			'description'       => __( 'Limit result set to items that have the specified variation(s) assigned.', 'sos-analytics' ),
			'type'              => 'array',
			'items'             => array(
				'type' => 'integer',
			),
			'default'           => array(),
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['variation_excludes']  = array(
			'description'       => __( 'Limit result set to items that don\'t have the specified variation(s) assigned.', 'sos-analytics' ),
			'type'              => 'array',
			'items'             => array(
				'type' => 'integer',
			),
			'default'           => array(),
			'validate_callback' => 'rest_validate_request_arg',
			'sanitize_callback' => 'wp_parse_id_list',
		);
		$params['coupon_includes']     = array(
			'description'       => __( 'Limit result set to items that have the specified coupon(s) assigned.', 'sos-analytics' ),
			'type'              => 'array',
			'items'             => array(
				'type' => 'integer',
			),
			'default'           => array(),
			'sanitize_callback' => 'wp_parse_id_list',
		);
		$params['coupon_excludes']     = array(
			'description'       => __( 'Limit result set to items that don\'t have the specified coupon(s) assigned.', 'sos-analytics' ),
			'type'              => 'array',
			'items'             => array(
				'type' => 'integer',
			),
			'default'           => array(),
			'sanitize_callback' => 'wp_parse_id_list',
		);
		$params['tax_rate_includes']   = array(
			'description'       => __( 'Limit result set to items that have the specified tax rate(s) assigned.', 'sos-analytics' ),
			'type'              => 'array',
			'items'             => array(
				'type' => 'integer',
			),
			'default'           => array(),
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['tax_rate_excludes']   = array(
			'description'       => __( 'Limit result set to items that don\'t have the specified tax rate(s) assigned.', 'sos-analytics' ),
			'type'              => 'array',
			'items'             => array(
				'type' => 'integer',
			),
			'default'           => array(),
			'validate_callback' => 'rest_validate_request_arg',
			'sanitize_callback' => 'wp_parse_id_list',
		);
		$params['customer']            = array(
			'description'       => __( 'Alias for customer_type (deprecated).', 'sos-analytics' ),
			'type'              => 'string',
			'enum'              => array(
				'new',
				'returning',
			),
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['customer_type']       = array(
			'description'       => __( 'Limit result set to orders that have the specified customer_type', 'sos-analytics' ),
			'type'              => 'string',
			'enum'              => array(
				'new',
				'returning',
			),
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['refunds']             = array(
			'description'       => __( 'Limit result set to specific types of refunds.', 'sos-analytics' ),
			'type'              => 'string',
			'default'           => '',
			'enum'              => array(
				'',
				'all',
				'partial',
				'full',
				'none',
			),
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['attribute_is']        = array(
			'description'       => __( 'Limit result set to orders that include products with the specified attributes.', 'sos-analytics' ),
			'type'              => 'array',
			'items'             => array(
				'type' => 'array',
			),
			'default'           => array(),
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['attribute_is_not']    = array(
			'description'       => __( 'Limit result set to orders that don\'t include products with the specified attributes.', 'sos-analytics' ),
			'type'              => 'array',
			'items'             => array(
				'type' => 'array',
			),
			'default'           => array(),
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['segmentby']           = array(
			'description'       => __( 'Segment the response by additional constraint.', 'sos-analytics' ),
			'type'              => 'string',
			'enum'              => array(
				'product',
				'category',
				'variation',
				'coupon',
				'customer_type', // new vs returning.
			),
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['fields']              = array(
			'description'       => __( 'Limit stats fields to the specified items.', 'sos-analytics' ),
			'type'              => 'array',
			'sanitize_callback' => 'wp_parse_slug_list',
			'validate_callback' => 'rest_validate_request_arg',
			'items'             => array(
				'type' => 'string',
			),
		);
		$params['force_cache_refresh'] = array(
			'description'       => __( 'Force retrieval of fresh data instead of from the cache.', 'sos-analytics' ),
			'type'              => 'boolean',
			'sanitize_callback' => 'wp_validate_boolean',
			'validate_callback' => 'rest_validate_request_arg',
		);

		return $params;
	}
}
