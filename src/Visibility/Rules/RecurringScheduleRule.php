<?php

/**
 * Show a block only during recurring weekly windows (e.g. "every
 * Saturday 10:00–14:00, every Sunday 08:00–12:00").
 *
 * Attribute shape:
 *
 *     artisanpackVisibility.recurring = {
 *         "timezone": "America/Chicago",         // optional; falls back to config('app.timezone')
 *         "windows": [
 *             { "day": 6, "start": "10:00", "end": "14:00" },
 *             { "day": 0, "start": "08:00", "end": "12:00" }
 *         ]
 *     }
 *
 * `day` uses PHP's `w` format — 0 = Sunday, 6 = Saturday. `start` and
 * `end` are 24-hour `HH:MM` strings in `timezone`. Empty `windows`
 * short-circuits to visible. The rule caps at 14 windows (2 per day)
 * to keep schedule review a manageable UI and to bound evaluation
 * cost; excess windows are ignored.
 *
 * DST: because comparison happens against `CarbonImmutable::nowIn`
 * which is already in `timezone`, a "10:00 America/Chicago" window
 * consistently means 10:00 wall-clock time — during DST fall-back the
 * duplicated 01:00–02:00 hour is included both times, during spring-
 * forward the skipped hour is not scheduled at all. That is the
 * intuitive behavior editors expect from wall-clock schedules.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.4.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Visibility\Rules;

use ArtisanPackUI\VisualEditor\Visibility\VisibilityContext;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityDecision;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityRule;
use Throwable;

class RecurringScheduleRule implements VisibilityRule
{
	public const MAX_WINDOWS = 14;

	public function key(): string
	{
		return 'recurring';
	}

	public function evaluate( array $ruleAttributes, VisibilityContext $context ): VisibilityDecision
	{
		$windows = isset( $ruleAttributes['windows'] ) && is_array( $ruleAttributes['windows'] )
			? $ruleAttributes['windows']
			: [];

		if ( [] === $windows ) {
			return VisibilityDecision::visible();
		}

		$timezone = $this->timezoneFor( $ruleAttributes );
		$now      = $context->nowIn( $timezone );

		$currentDay     = (int) $now->format( 'w' );
		$currentMinutes = ( (int) $now->format( 'H' ) ) * 60 + (int) $now->format( 'i' );

		$capped = array_slice( $windows, 0, self::MAX_WINDOWS );

		foreach ( $capped as $window ) {
			if ( ! is_array( $window ) ) {
				continue;
			}

			if ( ! $this->matchWindow( $window, $currentDay, $currentMinutes ) ) {
				continue;
			}

			return VisibilityDecision::visible();
		}

		return VisibilityDecision::hidden( [ $this->key() ] );
	}

	/**
	 * @param  array<string, mixed>  $window
	 */
	protected function matchWindow( array $window, int $currentDay, int $currentMinutes ): bool
	{
		$day = isset( $window['day'] ) && is_numeric( $window['day'] ) ? (int) $window['day'] : -1;

		if ( $day < 0 || $day > 6 ) {
			return false;
		}

		$start = $this->minutesFromClock( $window['start'] ?? null );
		$end   = $this->minutesFromClock( $window['end']   ?? null );

		if ( null === $start || null === $end ) {
			return false;
		}

		// Overnight window (e.g. Sat 22:00 → Sun 02:00). Match on
		// EITHER the window's day (from start onwards) OR the next
		// day-of-week (up to end). Same-day windows fall through to
		// the simple `start <= now <= end` check.
		if ( $end < $start ) {
			if ( $day === $currentDay && $currentMinutes >= $start ) {
				return true;
			}

			$nextDay = ( $day + 1 ) % 7;
			if ( $nextDay === $currentDay && $currentMinutes <= $end ) {
				return true;
			}

			return false;
		}

		if ( $day !== $currentDay ) {
			return false;
		}

		return $currentMinutes >= $start && $currentMinutes <= $end;
	}

	protected function minutesFromClock( mixed $raw ): ?int
	{
		if ( ! is_string( $raw ) ) {
			return null;
		}

		$parts = explode( ':', trim( $raw ) );

		if ( 2 !== count( $parts ) ) {
			return null;
		}

		[ $h, $m ] = $parts;

		if ( ! ctype_digit( $h ) || ! ctype_digit( $m ) ) {
			return null;
		}

		$h = (int) $h;
		$m = (int) $m;

		if ( $h < 0 || $h > 23 || $m < 0 || $m > 59 ) {
			return null;
		}

		return $h * 60 + $m;
	}

	protected function timezoneFor( array $ruleAttributes ): string
	{
		$tz = $ruleAttributes['timezone'] ?? null;

		if ( is_string( $tz ) && '' !== $tz && $this->isValidTimezone( $tz ) ) {
			return $tz;
		}

		$appTz = null;
		if ( function_exists( 'config' ) ) {
			$appTz = config( 'app.timezone' );
		}

		return is_string( $appTz ) && '' !== $appTz ? $appTz : 'UTC';
	}

	protected function isValidTimezone( string $tz ): bool
	{
		try {
			new \DateTimeZone( $tz );
			return true;
		} catch ( Throwable $e ) {
			return false;
		}
	}
}
