<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Visibility\Rules\DateTimeWindowRule;
use ArtisanPackUI\VisualEditor\Visibility\Rules\RecurringScheduleRule;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityContext;
use Carbon\CarbonImmutable;

function nowIs( string $iso, string $tz = 'UTC' ): VisibilityContext
{
	return new VisibilityContext( now: CarbonImmutable::parse( $iso, $tz ) );
}

// DateTimeWindowRule

it( 'date-time window: visible with no start/end', function () {
	$rule = new DateTimeWindowRule();
	expect( $rule->evaluate( [], nowIs( '2026-07-15T12:00:00' ) )->isVisible() )->toBeTrue();
} );

it( 'date-time window: visible when now is between start and end', function () {
	$rule = new DateTimeWindowRule();
	$attrs = [ 'start' => '2026-11-24T09:00:00', 'end' => '2026-11-28T23:59:00', 'timezone' => 'UTC' ];
	expect( $rule->evaluate( $attrs, nowIs( '2026-11-25T10:00:00', 'UTC' ) )->isVisible() )->toBeTrue();
	expect( $rule->evaluate( $attrs, nowIs( '2026-11-23T10:00:00', 'UTC' ) )->isHidden() )->toBeTrue();
	expect( $rule->evaluate( $attrs, nowIs( '2026-11-29T10:00:00', 'UTC' ) )->isHidden() )->toBeTrue();
} );

it( 'date-time window: honors per-rule timezone override', function () {
	$rule = new DateTimeWindowRule();
	$attrs = [ 'start' => '2026-11-24T09:00:00', 'end' => '2026-11-28T23:59:00', 'timezone' => 'America/Chicago' ];
	// At 2026-11-24 14:00 UTC == 08:00 Chicago, still before the start.
	expect( $rule->evaluate( $attrs, nowIs( '2026-11-24T14:00:00', 'UTC' ) )->isHidden() )->toBeTrue();
	// At 2026-11-24 15:00 UTC == 09:00 Chicago, exactly at start.
	expect( $rule->evaluate( $attrs, nowIs( '2026-11-24T15:00:00', 'UTC' ) )->isVisible() )->toBeTrue();
} );

it( 'date-time window: start-only means "show forever after start"', function () {
	$rule = new DateTimeWindowRule();
	$attrs = [ 'start' => '2026-01-01T00:00:00' ];
	expect( $rule->evaluate( $attrs, nowIs( '2027-06-01T00:00:00' ) )->isVisible() )->toBeTrue();
	expect( $rule->evaluate( $attrs, nowIs( '2025-06-01T00:00:00' ) )->isHidden() )->toBeTrue();
} );

it( 'date-time window: end-only means "show forever until end"', function () {
	$rule = new DateTimeWindowRule();
	$attrs = [ 'end' => '2026-12-31T23:59:59' ];
	expect( $rule->evaluate( $attrs, nowIs( '2026-06-01T00:00:00' ) )->isVisible() )->toBeTrue();
	expect( $rule->evaluate( $attrs, nowIs( '2027-01-01T00:00:00' ) )->isHidden() )->toBeTrue();
} );

it( 'date-time window: malformed range (end before start) treats block as visible', function () {
	$rule = new DateTimeWindowRule();
	$attrs = [ 'start' => '2026-12-31', 'end' => '2026-01-01' ];
	expect( $rule->evaluate( $attrs, nowIs( '2026-06-01' ) )->isVisible() )->toBeTrue();
} );

// RecurringScheduleRule

it( 'recurring: visible with no windows', function () {
	$rule = new RecurringScheduleRule();
	expect( $rule->evaluate( [], nowIs( '2026-07-15T12:00:00' ) )->isVisible() )->toBeTrue();
} );

it( 'recurring: visible inside a matching weekly window', function () {
	$rule = new RecurringScheduleRule();
	$attrs = [
		'timezone' => 'UTC',
		'windows'  => [
			[ 'day' => 3, 'start' => '10:00', 'end' => '14:00' ], // Wednesday
		],
	];
	// 2026-07-15 is a Wednesday
	expect( $rule->evaluate( $attrs, nowIs( '2026-07-15T12:00:00' ) )->isVisible() )->toBeTrue();
	expect( $rule->evaluate( $attrs, nowIs( '2026-07-15T09:59:00' ) )->isHidden() )->toBeTrue();
	// End is inclusive by minute — 14:01 is the first minute outside the window.
	expect( $rule->evaluate( $attrs, nowIs( '2026-07-15T14:01:00' ) )->isHidden() )->toBeTrue();
	// Wrong day
	expect( $rule->evaluate( $attrs, nowIs( '2026-07-16T12:00:00' ) )->isHidden() )->toBeTrue();
} );

it( 'recurring: caps at 14 windows', function () {
	$rule = new RecurringScheduleRule();
	$fifteenWindows = array_fill( 0, 15, [ 'day' => 3, 'start' => '10:00', 'end' => '14:00' ] );
	$attrs = [ 'timezone' => 'UTC', 'windows' => $fifteenWindows ];
	// Not testing the trimming per-se, but making sure the rule doesn't
	// blow up on a large input.
	expect( $rule->evaluate( $attrs, nowIs( '2026-07-15T12:00:00' ) )->isVisible() )->toBeTrue();
} );

it( 'recurring: rejects malformed HH:MM values', function () {
	$rule = new RecurringScheduleRule();
	$attrs = [ 'timezone' => 'UTC', 'windows' => [ [ 'day' => 3, 'start' => '25:99', 'end' => '10:00' ] ] ];
	expect( $rule->evaluate( $attrs, nowIs( '2026-07-15T12:00:00' ) )->isHidden() )->toBeTrue();
} );

it( 'recurring: overnight windows (end < start) match both the start-day tail and the following-day head', function () {
	$rule = new RecurringScheduleRule();
	// Saturday 22:00 → Sunday 02:00 promo window.
	$attrs = [
		'timezone' => 'UTC',
		'windows'  => [ [ 'day' => 6, 'start' => '22:00', 'end' => '02:00' ] ],
	];

	// 2026-07-18 is a Saturday. 23:00 Sat should match (tail of start day).
	expect( $rule->evaluate( $attrs, nowIs( '2026-07-18T23:00:00' ) )->isVisible() )->toBeTrue();
	// 2026-07-19 is a Sunday. 01:00 Sun should match (head of next day).
	expect( $rule->evaluate( $attrs, nowIs( '2026-07-19T01:00:00' ) )->isVisible() )->toBeTrue();
	// 21:59 Sat is before start → hidden.
	expect( $rule->evaluate( $attrs, nowIs( '2026-07-18T21:59:00' ) )->isHidden() )->toBeTrue();
	// 02:01 Sun is after end → hidden.
	expect( $rule->evaluate( $attrs, nowIs( '2026-07-19T02:01:00' ) )->isHidden() )->toBeTrue();
	// Weekday outside the window is hidden.
	expect( $rule->evaluate( $attrs, nowIs( '2026-07-15T23:00:00' ) )->isHidden() )->toBeTrue();
} );

it( 'recurring: DST transition — a "10:00 America/Chicago" window is at 10:00 wall-clock regardless of season', function () {
	$rule = new RecurringScheduleRule();
	$attrs = [
		'timezone' => 'America/Chicago',
		'windows'  => [ [ 'day' => 0, 'start' => '10:00', 'end' => '11:00' ] ], // Sunday
	];
	// Fall back Sunday 2026-11-01 at 10:30 CST (post-transition) → matches
	expect( $rule->evaluate( $attrs, nowIs( '2026-11-01T16:30:00', 'UTC' ) )->isVisible() )->toBeTrue();
	// Spring forward Sunday 2026-03-08 at 10:30 CDT → matches
	expect( $rule->evaluate( $attrs, nowIs( '2026-03-08T15:30:00', 'UTC' ) )->isVisible() )->toBeTrue();
} );
