<?php
/**
 * Event date helpers: timezone-aware parsing, upcoming/past classification, formatting, queries.
 *
 * Meta (from Meta\Registry): hk9_start / hk9_end as 'Y-m-d H:i' or 'Y-m-d' in the event's
 * timezone (hk9_timezone, defaults to the site timezone), hk9_all_day, hk9_time_tbd, hk9_status.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Events;

defined( 'ABSPATH' ) || exit;

final class Dates {

	public const POST_TYPE = 'hk9_event';

	/** Common US zones offered in the event editor (value => label built at call time). */
	public const ZONES = [
		'America/New_York',
		'America/Chicago',
		'America/Denver',
		'America/Phoenix',
		'America/Los_Angeles',
		'America/Anchorage',
		'Pacific/Honolulu',
	];

	public static function register(): void {
		// Pure helpers; nothing to hook. Kept for the module contract.
	}

	/**
	 * Timezone options for a select field (site timezone first).
	 *
	 * @return array<string, string>
	 */
	public static function timezone_options(): array {
		$site = wp_timezone_string();
		$out  = [ $site => sprintf( /* translators: %s: timezone identifier */ __( 'Site timezone (%s)', 'heartland-k9s-core' ), $site ) ];
		foreach ( self::ZONES as $zone ) {
			if ( ! isset( $out[ $zone ] ) ) {
				$out[ $zone ] = str_replace( '_', ' ', $zone );
			}
		}
		return $out;
	}

	/**
	 * Resolve a timezone identifier safely (site timezone on failure).
	 */
	public static function timezone( string $identifier ): \DateTimeZone {
		$identifier = trim( $identifier );
		if ( '' !== $identifier && in_array( $identifier, timezone_identifiers_list(), true ) ) {
			return new \DateTimeZone( $identifier );
		}
		return wp_timezone();
	}

	/**
	 * Parse a stored 'Y-m-d H:i' / 'Y-m-d' value in the given timezone.
	 *
	 * @return array{0:?\DateTimeImmutable,1:bool} [datetime, had_time]
	 */
	public static function parse( mixed $value, \DateTimeZone $tz ): array {
		if ( ! is_scalar( $value ) ) {
			return [ null, false ];
		}
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return [ null, false ];
		}
		foreach ( [ 'Y-m-d H:i:s' => true, 'Y-m-d H:i' => true, 'Y-m-d\TH:i' => true, 'Y-m-d' => false ] as $format => $has_time ) {
			$dt = \DateTimeImmutable::createFromFormat( '!' . $format, $value, $tz );
			if ( $dt instanceof \DateTimeImmutable && $dt->format( $format ) === $value ) {
				return [ $dt, $has_time ];
			}
		}
		// Lenient fallback for anything strtotime understands.
		$ts = strtotime( $value );
		if ( false === $ts ) {
			return [ null, false ];
		}
		$dt = ( new \DateTimeImmutable( '@' . $ts ) )->setTimezone( $tz );
		return [ $dt, (bool) preg_match( '/\d{1,2}:\d{2}/', $value ) ];
	}

	/**
	 * Parsed date data for an event.
	 *
	 * @return array{start:?\DateTimeImmutable,end:?\DateTimeImmutable,all_day:bool,time_tbd:bool,timezone:\DateTimeZone,status:string,has_time:bool}
	 */
	public static function get( int|\WP_Post $event ): array {
		$post_id = $event instanceof \WP_Post ? (int) $event->ID : $event;
		$tz      = self::timezone( (string) get_post_meta( $post_id, 'hk9_timezone', true ) );
		$all_day = filter_var( get_post_meta( $post_id, 'hk9_all_day', true ), FILTER_VALIDATE_BOOLEAN );
		$tbd     = filter_var( get_post_meta( $post_id, 'hk9_time_tbd', true ), FILTER_VALIDATE_BOOLEAN );
		[ $start, $start_has_time ] = self::parse( get_post_meta( $post_id, 'hk9_start', true ), $tz );
		[ $end, $end_has_time ]     = self::parse( get_post_meta( $post_id, 'hk9_end', true ), $tz );
		$has_time = $start_has_time && ! $all_day && ! $tbd;
		if ( $all_day || $tbd || ! $start_has_time ) {
			// Day-precision: normalise to the start of the day; the end becomes end-of-day for classification.
			$start = $start ? $start->setTime( 0, 0, 0 ) : null;
			$end   = $end ? ( $end_has_time && ! $all_day ? $end : $end->setTime( 23, 59, 59 ) ) : null;
			if ( ! $end && $start ) {
				$end = $start->setTime( 23, 59, 59 );
			}
		}
		if ( $start && $end && $end < $start ) {
			$end = $start;
		}
		$status = get_post_meta( $post_id, 'hk9_status', true );
		return [
			'start'    => $start,
			'end'      => $end,
			'all_day'  => $all_day,
			'time_tbd' => $tbd,
			'timezone' => $tz,
			'status'   => is_scalar( $status ) && '' !== (string) $status ? (string) $status : 'scheduled',
			'has_time' => $has_time,
		];
	}

	/**
	 * Upcoming = the end (or start when no end) is at/after "now" in the event timezone.
	 */
	public static function is_upcoming( int|\WP_Post $event ): bool {
		$data = self::get( $event );
		$ref  = $data['end'] ?? $data['start'];
		if ( ! $ref instanceof \DateTimeImmutable ) {
			return false;
		}
		$now = new \DateTimeImmutable( 'now', $data['timezone'] );
		return $ref->getTimestamp() >= $now->getTimestamp();
	}

	/**
	 * Formatted date/time range.
	 *
	 * @param array $args {date_format, time_format, separator, show_timezone, show_tbd}.
	 */
	public static function datetime_range( int|\WP_Post $event, array $args = [] ): string {
		$data = self::get( $event );
		if ( ! $data['start'] instanceof \DateTimeImmutable ) {
			return '';
		}
		$args = wp_parse_args(
			$args,
			[
				'date_format'   => 'D, M j, Y',
				'time_format'   => (string) get_option( 'time_format', 'g:i a' ),
				'separator'     => ' · ',
				'range'         => ' – ',
				'show_timezone' => true,
				'show_tbd'      => true,
			]
		);
		$tz     = $data['timezone'];
		$start  = $data['start'];
		$end    = $data['end'];
		$date   = static fn( \DateTimeImmutable $d ) => wp_date( (string) $args['date_format'], $d->getTimestamp(), $tz );
		$time   = static fn( \DateTimeImmutable $d ) => wp_date( (string) $args['time_format'], $d->getTimestamp(), $tz );
		$same_day = $end && $start->format( 'Y-m-d' ) === $end->format( 'Y-m-d' );

		if ( ! $data['has_time'] ) {
			$out = $date( $start );
			if ( $end && ! $same_day ) {
				$out .= $args['range'] . $date( $end );
			}
			if ( $data['time_tbd'] && $args['show_tbd'] ) {
				$out .= $args['separator'] . __( 'Time TBD', 'heartland-k9s-core' );
			} elseif ( $data['all_day'] ) {
				$out .= $args['separator'] . __( 'All day', 'heartland-k9s-core' );
			}
			return $out;
		}

		$tz_abbr = $args['show_timezone'] ? ' ' . $start->format( 'T' ) : '';
		if ( ! $end || $end == $start ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqualEqual -- DateTime value comparison.
			return $date( $start ) . $args['separator'] . $time( $start ) . $tz_abbr;
		}
		if ( $same_day ) {
			return $date( $start ) . $args['separator'] . $time( $start ) . $args['range'] . $time( $end ) . $tz_abbr;
		}
		return $date( $start ) . $args['separator'] . $time( $start ) . $args['range'] . $date( $end ) . $args['separator'] . $time( $end ) . $tz_abbr;
	}

	/**
	 * Events by scope, classified precisely per event timezone after a coarse DB pass.
	 *
	 * @param array $args {
	 *   scope: 'upcoming'|'past' (default upcoming); count: int (default 10, -1 all); offset: int;
	 *   featured: bool|null; status: string[]|null (default all); return: 'posts'|'ids'.
	 * }
	 * @return \WP_Post[]|int[]
	 */
	public static function query( array $args = [] ): array {
		$args = wp_parse_args(
			$args,
			[
				'scope'    => 'upcoming',
				'count'    => 10,
				'offset'   => 0,
				'featured' => null,
				'status'   => null,
				'return'   => 'posts',
			]
		);
		$upcoming = 'past' !== $args['scope'];
		$count    = (int) $args['count'];
		$offset   = max( 0, (int) $args['offset'] );

		// Coarse boundary in the site timezone with a 1-day margin either side (timezones differ by < 1 day).
		$now      = new \DateTimeImmutable( 'now', wp_timezone() );
		$boundary = $upcoming ? $now->modify( '-1 day' )->format( 'Y-m-d 00:00' ) : $now->modify( '+1 day' )->format( 'Y-m-d 23:59' );

		$meta_query = [
			'relation' => 'AND',
			[
				'key'     => 'hk9_start',
				'compare' => 'EXISTS',
			],
			[
				'relation' => 'OR',
				[
					'key'     => 'hk9_end',
					'value'   => $boundary,
					'compare' => $upcoming ? '>=' : '<=',
					'type'    => 'CHAR',
				],
				[
					'key'     => 'hk9_end',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => 'hk9_end',
					'value'   => '',
					'compare' => '=',
				],
				[
					'key'     => 'hk9_start',
					'value'   => $boundary,
					'compare' => $upcoming ? '>=' : '<=',
					'type'    => 'CHAR',
				],
			],
		];
		if ( null !== $args['featured'] ) {
			$meta_query[] = [
				'key'     => 'hk9_featured',
				'value'   => $args['featured'] ? [ '1', 'true', 'yes', 'on' ] : [ '', '0', 'false', 'no', 'off' ],
				'compare' => 'IN',
			];
		}
		if ( is_array( $args['status'] ) && $args['status'] ) {
			$meta_query[] = [
				'key'     => 'hk9_status',
				'value'   => array_map( 'strval', $args['status'] ),
				'compare' => 'IN',
			];
		}

		$query = new \WP_Query(
			[
				'post_type'              => self::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
				'meta_query'             => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_key'               => 'hk9_start', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'                => 'meta_value',
				'order'                  => $upcoming ? 'ASC' : 'DESC',
			]
		);

		$posts = [];
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$data = self::get( $post );
			if ( ! $data['start'] ) {
				continue;
			}
			if ( self::is_upcoming( $post ) === $upcoming ) {
				$posts[] = $post;
			}
		}
		// Precise ordering by absolute start instant.
		usort(
			$posts,
			static function ( \WP_Post $a, \WP_Post $b ) use ( $upcoming ): int {
				$ta = self::get( $a )['start']?->getTimestamp() ?? 0;
				$tb = self::get( $b )['start']?->getTimestamp() ?? 0;
				return $upcoming ? $ta <=> $tb : $tb <=> $ta;
			}
		);
		if ( $offset > 0 ) {
			$posts = array_slice( $posts, $offset );
		}
		if ( $count > 0 ) {
			$posts = array_slice( $posts, 0, $count );
		}
		if ( 'ids' === $args['return'] ) {
			return array_map( static fn( \WP_Post $p ): int => (int) $p->ID, $posts );
		}
		return $posts;
	}
}
