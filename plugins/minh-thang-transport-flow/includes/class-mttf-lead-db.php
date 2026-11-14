<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MTTF_Lead_DB {
	public const SCHEMA_VERSION       = '1';
	public const OPTION_SCHEMA_VERSION = 'mttf_leads_schema_version';

	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_install_table' ), 5 );
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'mttf_leads';
	}

	public static function activate() {
		self::install_table();
		update_option( self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION );
	}

	public static function maybe_install_table() {
		$stored = get_option( self::OPTION_SCHEMA_VERSION );
		if ( self::SCHEMA_VERSION !== $stored || ! self::table_exists() ) {
			self::install_table();
			update_option( self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION );
		}
	}

	public static function table_exists() {
		global $wpdb;
		$tbl = self::table_name();
		return (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) ) === $tbl;
	}

	public static function count_all() {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return 0;
		}

		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- static table identifier from \$wpdb->prefix.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	public static function install_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$table           = self::table_name();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			route_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			route_slug varchar(191) NOT NULL DEFAULT '',
			route_title text NULL,
			hub_region varchar(32) NOT NULL DEFAULT '',
			phone varchar(32) NOT NULL DEFAULT '',
			contact_apps varchar(191) NOT NULL DEFAULT '',
			source_url text NULL,
			utm_json text NULL,
			PRIMARY KEY  (id),
			KEY route_id (route_id),
			KEY created_at (created_at),
			KEY phone (phone)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Persist lead after capture succeeds.
	 *
	 * @param array<string,mixed> $data route_id, route_title, route_slug, hub_region, phone, contact_apps_csv, source_url, utm_array.
	 */
	public static function record_lead( array $data ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			self::install_table();
		}

		$table = self::table_name();

		$utm = isset( $data['utm'] ) && is_array( $data['utm'] ) ? $data['utm'] : array();

		$inserted = $wpdb->insert(
			$table,
			array(
				'created_at'    => isset( $data['created_at'] ) ? sanitize_text_field( (string) $data['created_at'] ) : current_time( 'mysql' ),
				'route_id'      => isset( $data['route_id'] ) ? absint( $data['route_id'] ) : 0,
				'route_slug'    => isset( $data['route_slug'] ) ? sanitize_text_field( (string) $data['route_slug'] ) : '',
				'route_title'   => isset( $data['route_title'] ) ? sanitize_text_field( (string) $data['route_title'] ) : '',
				'hub_region'    => isset( $data['hub_region'] ) ? sanitize_text_field( (string) $data['hub_region'] ) : '',
				'phone'         => isset( $data['phone'] ) ? self::sanitize_stored_phone( (string) $data['phone'] ) : '',
				'contact_apps'  => isset( $data['contact_apps'] ) ? sanitize_text_field( (string) $data['contact_apps'] ) : '',
				'source_url'    => isset( $data['source_url'] ) ? esc_url_raw( (string) $data['source_url'] ) : '',
				'utm_json'      => wp_json_encode( array_map( 'sanitize_text_field', $utm ), JSON_UNESCAPED_UNICODE ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return false !== $inserted;
	}

	private static function sanitize_stored_phone( $phone ) {
		$p = sanitize_text_field( $phone );
		if ( strlen( $p ) > 32 ) {
			$p = substr( $p, 0, 32 );
		}
		return $p;
	}

	/**
	 * All nhà xe operators for stats (from taxonomy).
	 *
	 * @return array<string,array{label:string,path:string,term_id:int,slug:string}>
	 */
	public static function get_nha_xe_operators() {
		$taxonomy = class_exists( 'MTTF_Landing_Taxonomies' )
			? MTTF_Landing_Taxonomies::TAX_NHA_XE
			: 'mttf_nha_xe';

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		$out = array();
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return $out;
		}

		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			$slug = sanitize_title( (string) $term->slug );
			if ( '' === $slug ) {
				continue;
			}
			$out[ $slug ] = array(
				'label'   => (string) $term->name,
				'path'    => '/nha-xe/' . $slug,
				'term_id' => (int) $term->term_id,
				'slug'    => $slug,
			);
		}

		return $out;
	}

	/**
	 * Alias kept for older call sites.
	 *
	 * @return array<string,array{label:string,path:string,term_id?:int,slug?:string}>
	 */
	public static function landing_presets() {
		return self::get_nha_xe_operators();
	}

	/**
	 * Route post IDs belonging to a nhà xe term slug.
	 *
	 * @param string $slug Operator slug.
	 * @return int[]
	 */
	public static function get_route_ids_for_nha_xe( $slug ) {
		$slug = sanitize_title( (string) $slug );
		if ( '' === $slug ) {
			return array();
		}

		$taxonomy = class_exists( 'MTTF_Landing_Taxonomies' )
			? MTTF_Landing_Taxonomies::TAX_NHA_XE
			: 'mttf_nha_xe';

		$ids = get_posts(
			array(
				'post_type'      => 'tuyen_xe',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 1000,
				'fields'         => 'ids',
				'tax_query'      => array(
					array(
						'taxonomy' => $taxonomy,
						'field'    => 'slug',
						'terms'    => array( $slug ),
					),
				),
			)
		);

		return array_map( 'absint', is_array( $ids ) ? $ids : array() );
	}

	/**
	 * Count leads attributed to a nhà xe (landing URL or route belonging to operator).
	 *
	 * @param string $slug Operator slug.
	 * @return int
	 */
	public static function count_by_nha_xe( $slug ) {
		$slug = sanitize_title( (string) $slug );
		if ( '' === $slug ) {
			return 0;
		}

		$r = self::query_leads(
			array(
				'nha_xe'   => $slug,
				'paged'    => 1,
				'per_page' => 10,
			)
		);

		return (int) $r['total'];
	}

	/**
	 * Daily Ads LP summary: leads whose source_url is the nhà xe landing.
	 *
	 * @param string $slug    Operator slug (hk-buslines, g8-open-tour).
	 * @param string $day_ymd Local day Y-m-d.
	 * @return array{total:int,routes:array<int,array{title:string,count:int}>}
	 */
	public static function summarize_landing_day( $slug, $day_ymd ) {
		global $wpdb;

		$empty = array(
			'total'  => 0,
			'routes' => array(),
		);

		$slug = sanitize_title( (string) $slug );
		$day  = preg_replace( '/[^0-9\-]/', '', (string) $day_ymd );
		if ( '' === $slug || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) || ! self::table_exists() ) {
			return $empty;
		}

		$table     = self::table_name();
		$path_like = '%' . $wpdb->esc_like( '/nha-xe/' . $slug ) . '%';
		$date_from = $day . ' 00:00:00';
		$date_to   = $day . ' 23:59:59';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from prefix.
		$sql = $wpdb->prepare(
			"SELECT route_title, COUNT(*) AS cnt
			FROM {$table}
			WHERE source_url LIKE %s
				AND created_at >= %s
				AND created_at <= %s
			GROUP BY route_title
			ORDER BY cnt DESC, route_title ASC",
			$path_like,
			$date_from,
			$date_to
		);

		$rows = $wpdb->get_results( $sql, OBJECT ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return $empty;
		}

		$total  = 0;
		$routes = array();
		foreach ( $rows as $row ) {
			$count = isset( $row->cnt ) ? (int) $row->cnt : 0;
			if ( $count < 1 ) {
				continue;
			}
			$title = isset( $row->route_title ) ? trim( (string) $row->route_title ) : '';
			if ( '' === $title ) {
				$title = 'Không gắn tuyến';
			}
			$routes[] = array(
				'title' => $title,
				'count' => $count,
			);
			$total += $count;
		}

		return array(
			'total'  => $total,
			'routes' => $routes,
		);
	}

	/**
	 * @param string $path_fragment e.g. /nha-xe/hk-buslines or slug hk-buslines
	 * @return int
	 */
	public static function count_by_source_path( $path_fragment ) {
		$slug = self::extract_nha_xe_slug( $path_fragment );
		if ( '' !== $slug ) {
			return self::count_by_nha_xe( $slug );
		}

		$path_fragment = self::normalize_source_path( $path_fragment );
		if ( '' === $path_fragment ) {
			return 0;
		}

		$r = self::query_leads(
			array(
				'source_path' => $path_fragment,
				'paged'       => 1,
				'per_page'    => 10,
			)
		);

		return (int) $r['total'];
	}

	/**
	 * @param string $raw Slug, path, or URL.
	 * @return string Nhà xe slug or empty.
	 */
	public static function extract_nha_xe_slug( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '';
		}

		$operators = self::get_nha_xe_operators();
		if ( isset( $operators[ $raw ] ) ) {
			return $raw;
		}

		$path = wp_parse_url( $raw, PHP_URL_PATH );
		if ( is_string( $path ) && '' !== $path ) {
			$raw = $path;
		}

		if ( preg_match( '#/nha-xe/([^/]+)/?#i', $raw, $m ) ) {
			$slug = sanitize_title( $m[1] );
			return $slug;
		}

		$slug = sanitize_title( ltrim( $raw, '/' ) );
		if ( isset( $operators[ $slug ] ) ) {
			return $slug;
		}

		return '';
	}

	/**
	 * Resolve nhà xe name for a stored lead row.
	 *
	 * @param object $row Lead row.
	 * @return string
	 */
	public static function resolve_nha_xe_label_for_lead( $row ) {
		$source = isset( $row->source_url ) ? (string) $row->source_url : '';
		$slug   = self::extract_nha_xe_slug( $source );
		if ( '' !== $slug ) {
			$ops = self::get_nha_xe_operators();
			if ( isset( $ops[ $slug ] ) ) {
				return $ops[ $slug ]['label'];
			}
		}

		$route_id = isset( $row->route_id ) ? absint( $row->route_id ) : 0;
		$taxonomy = class_exists( 'MTTF_Landing_Taxonomies' )
			? MTTF_Landing_Taxonomies::TAX_NHA_XE
			: 'mttf_nha_xe';
		if ( $route_id > 0 && taxonomy_exists( $taxonomy ) ) {
			$terms = wp_get_post_terms( $route_id, $taxonomy );
			if ( ! is_wp_error( $terms ) && ! empty( $terms[0] ) ) {
				return (string) $terms[0]->name;
			}
		}

		return '';
	}

	/**
	 * @param string $raw Raw path or landing key.
	 * @return string Normalized path fragment without trailing slash.
	 */
	public static function normalize_source_path( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '';
		}

		$slug = self::extract_nha_xe_slug( $raw );
		if ( '' !== $slug ) {
			return '/nha-xe/' . $slug;
		}

		$path = wp_parse_url( $raw, PHP_URL_PATH );
		if ( is_string( $path ) && '' !== $path ) {
			$raw = $path;
		}

		$raw = '/' . ltrim( $raw, '/' );
		$raw = untrailingslashit( $raw );

		return $raw;
	}

	/**
	 * @param array{route_id?:int|string,phone?:string,source_path?:string,nha_xe?:string,paged?:int,per_page?:int,export_cap?:int} $args Filters.
	 * @return array{items:array<int,object>,total:int}
	 */
	public static function query_leads( $args ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return array(
				'items' => array(),
				'total' => 0,
			);
		}

		$table = self::table_name();

		$where  = array( '1=1' );
		$params = array();

		$route_id = isset( $args['route_id'] ) ? absint( $args['route_id'] ) : 0;
		if ( $route_id > 0 ) {
			$where[]  = 'route_id = %d';
			$params[] = $route_id;
		}

		$nha_xe = '';
		if ( ! empty( $args['nha_xe'] ) ) {
			$nha_xe = self::extract_nha_xe_slug( (string) $args['nha_xe'] );
		} elseif ( ! empty( $args['source_path'] ) ) {
			$nha_xe = self::extract_nha_xe_slug( (string) $args['source_path'] );
		}

		if ( '' !== $nha_xe ) {
			$path_like = '%' . $wpdb->esc_like( '/nha-xe/' . $nha_xe ) . '%';
			$route_ids = self::get_route_ids_for_nha_xe( $nha_xe );

			if ( ! empty( $route_ids ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $route_ids ), '%d' ) );
				$where[]      = '( source_url LIKE %s OR route_id IN (' . $placeholders . ') )';
				$params[]     = $path_like;
				foreach ( $route_ids as $rid ) {
					$params[] = (int) $rid;
				}
			} else {
				$where[]  = 'source_url LIKE %s';
				$params[] = $path_like;
			}
		} elseif ( ! empty( $args['source_path'] ) ) {
			$source_path = self::normalize_source_path( (string) $args['source_path'] );
			if ( '' !== $source_path ) {
				$where[]  = 'source_url LIKE %s';
				$params[] = '%' . $wpdb->esc_like( $source_path ) . '%';
			}
		}

		$phone_q = isset( $args['phone'] ) ? trim( (string) $args['phone'] ) : '';
		if ( '' !== $phone_q ) {
			$patterns      = array();
			$digits_only   = preg_replace( '/[^0-9+]/', '', $phone_q );
			if ( class_exists( 'MTTF_Ajax' ) ) {
				$n = MTTF_Ajax::normalize_phone_for_lookup( $phone_q );
				if ( strlen( $n ) >= 4 ) {
					$patterns[] = '%' . $wpdb->esc_like( $n ) . '%';
				}
			}
			if ( strlen( $digits_only ) >= 3 ) {
				$patterns[] = '%' . $wpdb->esc_like( $digits_only ) . '%';
			}
			$patterns = array_values( array_unique( $patterns ) );
			if ( ! empty( $patterns ) ) {
				$or_parts = array();
				foreach ( $patterns as $_p ) {
					$or_parts[] = 'phone LIKE %s';
					$params[]   = $_p;
				}
				$where[] = '(' . implode( ' OR ', $or_parts ) . ')';
			}
		}

		$date_from = isset( $args['date_from'] ) ? trim( (string) $args['date_from'] ) : '';
		if ( '' !== $date_from ) {
			$where[]  = 'created_at >= %s';
			$params[] = $date_from;
		}

		$date_to = isset( $args['date_to'] ) ? trim( (string) $args['date_to'] ) : '';
		if ( '' !== $date_to ) {
			$where[]  = 'created_at <= %s';
			$params[] = $date_to;
		}

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dynamic placeholders count matches $params from filters only.
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
		} else {
			$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no placeholders.
		}

		$per_page = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 25;
		if ( $per_page < 10 ) {
			$per_page = 10;
		}
		if ( $per_page > 500 ) {
			$per_page = 500;
		}

		$paged = isset( $args['paged'] ) ? absint( $args['paged'] ) : 1;
		if ( $paged < 1 ) {
			$paged = 1;
		}
		$offset = ( $paged - 1 ) * $per_page;

		if ( ! empty( $args['export_cap'] ) ) {
			$cap = absint( $args['export_cap'] );
			if ( $cap < 1 ) {
				$cap = 5000;
			}
			if ( $cap > 20000 ) {
				$cap = 20000;
			}
			$params_export = $params;
			$params_export[] = $cap;
			$select_sql       = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d";
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( $select_sql, $params_export ), OBJECT );

			return array(
				'items' => is_array( $rows ) ? $rows : array(),
				'total' => $total,
			);
		}

		$params_with_limit   = $params;
		$params_with_limit[] = $per_page;
		$params_with_limit[] = $offset;

		$select_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders match $params_with_limit order.
		$rows = $wpdb->get_results( $wpdb->prepare( $select_sql, $params_with_limit ), OBJECT );

		return array(
			'items' => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}
}
