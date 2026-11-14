<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Daily Telegram digest for Ads nhà xe landings (HK + G8) at 23:30 site time.
 */
class MTTF_Lead_Daily_Digest {

	const CRON_HOOK      = 'mttf_ads_lead_daily_digest';
	const OPTION_LAST_DAY = 'mttf_ads_lead_digest_last_day';

	/**
	 * Operators included in the Ads digest.
	 *
	 * @return array<string,string> slug => label
	 */
	public static function operators() {
		return array(
			'hk-buslines'   => 'HK Buslines',
			'g8-open-tour' => 'G8 Open Tour',
		);
	}

	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run' ) );
		add_action( 'init', array( __CLASS__, 'ensure_cron_scheduled' ) );
	}

	public static function activate() {
		self::ensure_cron_scheduled( true );
	}

	public static function deactivate() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
		}
	}

	/**
	 * @param bool $force Reschedule even if an event exists.
	 */
	public static function ensure_cron_scheduled( $force = false ) {
		$next = wp_next_scheduled( self::CRON_HOOK );
		if ( $next && ! $force ) {
			return;
		}

		if ( $next ) {
			wp_unschedule_event( $next, self::CRON_HOOK );
		}

		wp_schedule_event( self::next_run_timestamp(), 'daily', self::CRON_HOOK );
	}

	/**
	 * Next 23:30 in the site timezone.
	 *
	 * @return int Unix timestamp.
	 */
	public static function next_run_timestamp() {
		try {
			$tz  = wp_timezone();
			$now = new DateTimeImmutable( 'now', $tz );
			$run = $now->setTime( 23, 30, 0 );
			if ( $now >= $run ) {
				$run = $run->modify( '+1 day' );
			}
			return $run->getTimestamp();
		} catch ( Exception $e ) {
			return time() + DAY_IN_SECONDS;
		}
	}

	/**
	 * Cron callback.
	 */
	public static function run() {
		$day = wp_date( 'Y-m-d' );
		$last = (string) get_option( self::OPTION_LAST_DAY, '' );
		if ( $last === $day ) {
			return;
		}

		$result = self::send_for_day( $day );
		if ( ! empty( $result['success'] ) ) {
			update_option( self::OPTION_LAST_DAY, $day, false );
		}
	}

	/**
	 * Build + send digest for a local calendar day.
	 *
	 * @param string $day_ymd Y-m-d.
	 * @return array{success:bool,skipped:bool,error:string,text:string}
	 */
	public static function send_for_day( $day_ymd ) {
		$text = self::build_message( $day_ymd );
		if ( ! class_exists( 'MTTF_Lead_Notify', false ) ) {
			return array(
				'success' => false,
				'skipped' => true,
				'error'   => 'notify_missing',
				'text'    => $text,
			);
		}

		$delivery = MTTF_Lead_Notify::send_telegram_text( $text );
		$delivery['text'] = $text;

		return $delivery;
	}

	/**
	 * @param string $day_ymd Y-m-d.
	 * @return string
	 */
	public static function build_message( $day_ymd ) {
		$day_ymd = preg_replace( '/[^0-9\-]/', '', (string) $day_ymd );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day_ymd ) ) {
			$day_ymd = wp_date( 'Y-m-d' );
		}

		$ts       = strtotime( $day_ymd . ' 12:00:00' );
		$day_label = false !== $ts ? wp_date( 'd/m', $ts ) : $day_ymd;

		$operators = self::operators();
		$summaries = array();
		$grand     = 0;

		foreach ( $operators as $slug => $label ) {
			$summary = class_exists( 'MTTF_Lead_DB', false )
				? MTTF_Lead_DB::summarize_landing_day( $slug, $day_ymd )
				: array(
					'total'  => 0,
					'routes' => array(),
				);
			$summaries[ $slug ] = array(
				'label'  => $label,
				'total'  => (int) ( $summary['total'] ?? 0 ),
				'routes' => isset( $summary['routes'] ) && is_array( $summary['routes'] ) ? $summary['routes'] : array(),
			);
			$grand += (int) $summaries[ $slug ]['total'];
		}

		$lines   = array();
		$lines[] = '📊 Lead Ads hôm nay (' . $day_label . ')';

		foreach ( $summaries as $row ) {
			$lines[] = '• ' . $row['label'] . ': ' . (int) $row['total'] . ' số liên hệ';
		}
		$lines[] = '• Tổng 2 LP: ' . $grand . ' số liên hệ';

		foreach ( $summaries as $row ) {
			$lines[] = '';
			$lines[] = $row['label'] . ':';
			if ( empty( $row['routes'] ) ) {
				$lines[] = '• Chưa có số liên hệ';
				continue;
			}
			foreach ( $row['routes'] as $route ) {
				$title = isset( $route['title'] ) ? (string) $route['title'] : 'Không gắn tuyến';
				$count = isset( $route['count'] ) ? (int) $route['count'] : 0;
				$lines[] = '• ' . $title . ': ' . $count . ' số liên hệ';
			}
		}

		return implode( "\n", $lines );
	}
}
