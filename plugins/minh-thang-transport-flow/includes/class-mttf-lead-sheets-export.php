<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST export for Google Sheets Apps Script → Customer Match.
 */
class MTTF_Lead_Sheets_Export {

	const REST_NS   = 'mttf/v1';
	const REST_ROUTE = '/leads-export';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route(
			self::REST_NS,
			self::REST_ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_export' ),
				'permission_callback' => array( __CLASS__, 'permission_check' ),
				'args'                => array(
					'since'   => array(
						'description'       => 'Only leads created at/after this time (Y-m-d or Y-m-d H:i:s).',
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'nha_xe'  => array(
						'description'       => 'Filter by nhà xe slug.',
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_title',
					),
					'limit'   => array(
						'description'       => 'Max rows (1–5000).',
						'type'              => 'integer',
						'required'          => false,
						'default'           => 5000,
						'sanitize_callback' => 'absint',
					),
					'dedupe'  => array(
						'description'       => 'If 1, dedupe by phone_e164 keeping newest.',
						'type'              => 'integer',
						'required'          => false,
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'token'   => array(
						'description'       => 'API token (prefer Authorization Bearer).',
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public static function permission_check( $request ) {
		if ( ! (int) MTTF_Settings::get( 'sheets_export_enabled', 0 ) ) {
			return new WP_Error(
				'mttf_sheets_disabled',
				'Sheets export API is disabled.',
				array( 'status' => 403 )
			);
		}

		$expected = (string) MTTF_Settings::get( 'sheets_export_token', '' );
		if ( '' === $expected ) {
			return new WP_Error(
				'mttf_sheets_no_token',
				'Sheets export token is not configured.',
				array( 'status' => 403 )
			);
		}

		$provided = self::extract_token( $request );
		if ( '' === $provided || ! hash_equals( $expected, $provided ) ) {
			return new WP_Error(
				'mttf_sheets_forbidden',
				'Invalid export token.',
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return string
	 */
	private static function extract_token( $request ) {
		$auth = (string) $request->get_header( 'authorization' );
		if ( '' !== $auth && preg_match( '/^\s*Bearer\s+(\S+)/i', $auth, $m ) ) {
			return trim( (string) $m[1] );
		}

		return trim( (string) $request->get_param( 'token' ) );
	}

	/**
	 * Public REST URL for settings UI / Apps Script.
	 *
	 * @return string
	 */
	public static function get_export_url() {
		return rest_url( self::REST_NS . self::REST_ROUTE );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_export( $request ) {
		$limit = absint( $request->get_param( 'limit' ) );
		if ( $limit < 1 ) {
			$limit = 5000;
		}
		if ( $limit > 5000 ) {
			$limit = 5000;
		}

		$since_raw = trim( (string) $request->get_param( 'since' ) );
		$date_from = self::parse_since_bound( $since_raw );

		$nha_xe = sanitize_title( (string) $request->get_param( 'nha_xe' ) );
		$dedupe = (int) $request->get_param( 'dedupe' );
		if ( $dedupe < 0 ) {
			$dedupe = 1;
		}

		$query_args = array(
			'paged'      => 1,
			'per_page'   => 25,
			'export_cap' => $limit,
		);
		if ( '' !== $date_from ) {
			$query_args['date_from'] = $date_from;
		}
		if ( '' !== $nha_xe ) {
			$query_args['nha_xe'] = $nha_xe;
		}

		$result = MTTF_Lead_DB::query_leads( $query_args );
		$items  = isset( $result['items'] ) && is_array( $result['items'] ) ? $result['items'] : array();

		$rows          = array();
		$seen_phones   = array();
		$skipped_empty = 0;

		foreach ( $items as $row ) {
			$phone_raw  = isset( $row->phone ) ? (string) $row->phone : '';
			$phone_e164 = MTTF_Ajax::to_e164_phone( $phone_raw );

			if ( $dedupe && '' !== $phone_e164 ) {
				if ( isset( $seen_phones[ $phone_e164 ] ) ) {
					continue;
				}
				$seen_phones[ $phone_e164 ] = true;
			}

			if ( '' === $phone_e164 ) {
				++$skipped_empty;
			}

			$utm = array();
			if ( ! empty( $row->utm_json ) ) {
				$decoded = json_decode( (string) $row->utm_json, true );
				if ( is_array( $decoded ) ) {
					$utm = $decoded;
				}
			}

			$nha_label = MTTF_Lead_DB::resolve_nha_xe_label_for_lead( $row );
			$nha_slug  = self::resolve_nha_xe_slug_for_lead( $row );

			$rows[] = array(
				'id'          => isset( $row->id ) ? (int) $row->id : 0,
				'created_at'  => isset( $row->created_at ) ? (string) $row->created_at : '',
				'phone_raw'   => $phone_raw,
				'phone_e164'  => $phone_e164,
				'nha_xe'      => $nha_slug,
				'nha_xe_label'=> $nha_label,
				'route_title' => isset( $row->route_title ) ? (string) $row->route_title : '',
				'route_slug'  => isset( $row->route_slug ) ? (string) $row->route_slug : '',
				'source_url'  => isset( $row->source_url ) ? (string) $row->source_url : '',
				'utm'         => $utm,
			);
		}

		return rest_ensure_response(
			array(
				'ok'            => true,
				'generated_at'  => gmdate( 'c' ),
				'since'         => $since_raw,
				'nha_xe'        => $nha_xe,
				'count'         => count( $rows ),
				'total_matched' => isset( $result['total'] ) ? (int) $result['total'] : count( $rows ),
				'dedupe'        => (int) $dedupe,
				'skipped_no_e164' => $skipped_empty,
				'leads'         => $rows,
			)
		);
	}

	/**
	 * @param object $row Lead row.
	 * @return string Nhà xe slug.
	 */
	private static function resolve_nha_xe_slug_for_lead( $row ) {
		$source = isset( $row->source_url ) ? (string) $row->source_url : '';
		$slug   = MTTF_Lead_DB::extract_nha_xe_slug( $source );
		if ( '' !== $slug ) {
			return $slug;
		}

		$route_id = isset( $row->route_id ) ? absint( $row->route_id ) : 0;
		$taxonomy = class_exists( 'MTTF_Landing_Taxonomies' )
			? MTTF_Landing_Taxonomies::TAX_NHA_XE
			: 'mttf_nha_xe';
		if ( $route_id > 0 && taxonomy_exists( $taxonomy ) ) {
			$terms = wp_get_post_terms( $route_id, $taxonomy );
			if ( ! is_wp_error( $terms ) && ! empty( $terms[0] ) && ! empty( $terms[0]->slug ) ) {
				return (string) $terms[0]->slug;
			}
		}

		return '';
	}

	/**
	 * Normalize since into MySQL datetime lower bound.
	 *
	 * @param string $since Raw since param.
	 * @return string Empty if unset/invalid.
	 */
	private static function parse_since_bound( $since ) {
		$since = trim( (string) $since );
		if ( '' === $since ) {
			return '';
		}

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $since ) ) {
			return $since . ' 00:00:00';
		}

		$ts = strtotime( $since );
		if ( false === $ts ) {
			return '';
		}

		// created_at is stored in site local time (current_time).
		return wp_date( 'Y-m-d H:i:s', $ts );
	}
}
