<?php

/**
 * Show a block only inside a single date/time window.
 *
 * Either edge (start or end) can be omitted for open-ended windows —
 * "hide until launch" leaves `end` empty, "hide after cutoff" leaves
 * `start` empty. Both empty short-circuits to visible.
 *
 * Attribute shape:
 *
 *     artisanpackVisibility.dateTimeWindow = {
 *         "start":    "2026-11-24T09:00:00",   // ISO-8601, no zone
 *         "end":      "2026-11-28T23:59:00",
 *         "timezone": "America/Chicago"        // optional; falls back to config('app.timezone')
 *     }
 *
 * Times are interpreted in `timezone` — with a per-rule override so an
 * editor scheduling a Black Friday banner in America/Chicago doesn't
 * have to hand-convert against `config('app.timezone')`.
 *
 * The `visual-editor:audit-scheduled-blocks` command in
 * {@see \ArtisanPackUI\VisualEditor\Console\AuditScheduledBlocksCommand}
 * enumerates every block using this rule so ops can see what's queued.
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
use Carbon\CarbonImmutable;
use Throwable;

class DateTimeWindowRule implements VisibilityRule
{
	public function key(): string
	{
		return 'dateTimeWindow';
	}

	public function evaluate( array $ruleAttributes, VisibilityContext $context ): VisibilityDecision
	{
		$start = $this->parse( $ruleAttributes['start'] ?? null, $this->timezoneFor( $ruleAttributes ) );
		$end   = $this->parse( $ruleAttributes['end']   ?? null, $this->timezoneFor( $ruleAttributes ) );

		if ( null === $start && null === $end ) {
			return VisibilityDecision::visible();
		}

		if ( null !== $start && null !== $end && $end->lessThan( $start ) ) {
			// Malformed window (end before start). Treat as always visible so a
			// mis-configured rule can't accidentally hide production content.
			return VisibilityDecision::visible();
		}

		$now = $context->nowIn( $this->timezoneFor( $ruleAttributes ) );

		$visible = ( null === $start || $now->greaterThanOrEqualTo( $start ) )
			&& ( null === $end   || $now->lessThanOrEqualTo( $end ) );

		return $visible
			? VisibilityDecision::visible()
			: VisibilityDecision::hidden( [ $this->key() ] );
	}

	/**
	 * @param  array<string, mixed>  $ruleAttributes
	 */
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

	protected function parse( mixed $raw, string $timezone ): ?CarbonImmutable
	{
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return null;
		}

		try {
			return CarbonImmutable::parse( $raw, $timezone );
		} catch ( Throwable $e ) {
			return null;
		}
	}
}
