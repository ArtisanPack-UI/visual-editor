<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;

it( 'falls back to Tailwind v4 defaults when nothing overrides them', function (): void {
	$registry = BreakpointRegistry::fromLayers( [], [] );

	expect( $registry->all() )->toBe( [
		'sm'  => 640,
		'md'  => 768,
		'lg'  => 1024,
		'xl'  => 1280,
		'2xl' => 1536,
	] );
} );

it( 'merges config overrides on top of defaults', function (): void {
	$registry = BreakpointRegistry::fromLayers( [ 'lg' => 1100 ], [] );

	expect( $registry->get( 'lg' ) )->toBe( 1100 );
	expect( $registry->get( 'md' ) )->toBe( 768 );
} );

it( 'merges theme.json overrides on top of config', function (): void {
	$registry = BreakpointRegistry::fromLayers(
		[ 'lg' => 1100 ],
		[ 'lg' => '1200px', '3xl' => 1920 ],
	);

	expect( $registry->get( 'lg' ) )->toBe( 1200 );
	expect( $registry->get( '3xl' ) )->toBe( 1920 );
	expect( $registry->prefixes() )->toContain( '3xl' );
} );

it( 'sorts the registry ascending by min-width', function (): void {
	$registry = BreakpointRegistry::fromLayers(
		[],
		[ '3xl' => 1920, 'xxs' => 320 ],
	);

	expect( array_keys( $registry->all() ) )->toBe( [
		'xxs', 'sm', 'md', 'lg', 'xl', '2xl', '3xl',
	] );
} );

it( 'returns 0 for the implicit base slot and exposes it in keysWithBase()', function (): void {
	$registry = BreakpointRegistry::fromLayers( [], [] );

	expect( $registry->get( 'base' ) )->toBe( 0 );
	expect( $registry->keysWithBase() )->toBe( [ 'base', 'sm', 'md', 'lg', 'xl', '2xl', 'tablet', 'mobile' ] );
	expect( $registry->has( 'base' ) )->toBeTrue();
} );

it( 'rejects the reserved `base` key during validation', function (): void {
	BreakpointRegistry::fromLayers( [ 'base' => 0 ], [] );
} )->throws( InvalidArgumentException::class, 'reserved' );

it( 'rejects breakpoints with non-positive widths', function (): void {
	BreakpointRegistry::fromLayers( [ 'sm' => 0 ], [] );
} )->throws( InvalidArgumentException::class, 'positive pixel value' );

it( 'rejects breakpoints with duplicate widths', function (): void {
	BreakpointRegistry::fromLayers( [], [ 'foo' => 640 ] );
} )->throws( InvalidArgumentException::class, 'same min-width' );

it( 'rejects breakpoints with non-numeric strings', function (): void {
	BreakpointRegistry::fromLayers( [], [ 'foo' => '10rem' ] );
} )->throws( InvalidArgumentException::class, 'invalid value' );

it( 'rejects breakpoints with invalid key characters', function (): void {
	BreakpointRegistry::fromLayers( [], [ 'big screen!' => 1900 ] );
} )->throws( InvalidArgumentException::class, 'letters, numbers' );

/*
|--------------------------------------------------------------------------
| #617 — device labels + preview widths
|--------------------------------------------------------------------------
*/

it( 'ships Mobile/Tablet/Desktop labels and device preview widths by default', function (): void {
	$registry = BreakpointRegistry::fromLayers( [], [] );

	expect( $registry->label( 'sm' ) )->toBe( 'Mobile' );
	expect( $registry->label( 'md' ) )->toBe( 'Tablet' );
	expect( $registry->label( 'lg' ) )->toBe( 'Desktop' );
	expect( $registry->previewWidth( 'sm' ) )->toBe( 375 );
	expect( $registry->previewWidth( 'md' ) )->toBe( 768 );
	expect( $registry->previewWidth( 'lg' ) )->toBe( 1440 );
} );

it( 'returns 0 previewWidth for the implicit base slot and null for unknown keys', function (): void {
	$registry = BreakpointRegistry::fromLayers( [], [] );

	expect( $registry->previewWidth( 'base' ) )->toBe( 0 );
	expect( $registry->previewWidth( 'nope' ) )->toBeNull();
	expect( $registry->label( 'nope' ) )->toBeNull();
} );

it( 'accepts full object-form config entries', function (): void {
	$registry = BreakpointRegistry::fromLayers(
		[
			'sm' => [
				'minWidthPx'     => 640,
				'previewWidthPx' => 390,
				'label'          => 'iPhone',
			],
		],
		[],
	);

	expect( $registry->get( 'sm' ) )->toBe( 640 );
	expect( $registry->previewWidth( 'sm' ) )->toBe( 390 );
	expect( $registry->label( 'sm' ) )->toBe( 'iPhone' );
} );

it( 'lets partial object overrides merge into the default at the same key', function (): void {
	$registry = BreakpointRegistry::fromLayers(
		[ 'lg' => [ 'previewWidthPx' => 1600 ] ],
		[],
	);

	// Only previewWidthPx was overridden — minWidthPx + label stay from the default.
	expect( $registry->get( 'lg' ) )->toBe( 1024 );
	expect( $registry->previewWidth( 'lg' ) )->toBe( 1600 );
	expect( $registry->label( 'lg' ) )->toBe( 'Desktop' );
} );

it( 'normalises a bare scalar override to { minWidthPx, minWidthPx, key } for NEW keys', function (): void {
	// A scalar entry that introduces a fresh key (no default to
	// inherit from) resolves to `{ minWidthPx: value, previewWidthPx:
	// value, label: key }` — the back-compat guarantee documented in
	// #617.
	$registry = BreakpointRegistry::fromLayers( [ 'zoom' => '900px' ], [] );

	expect( $registry->get( 'zoom' ) )->toBe( 900 );
	expect( $registry->previewWidth( 'zoom' ) )->toBe( 900 );
	expect( $registry->label( 'zoom' ) )->toBe( 'zoom' );
} );

it( 'lets a scalar override an existing default without wiping the default label/previewWidthPx', function (): void {
	// Regression test for the #617 review finding: a pre-#617 host
	// with `'lg' => 1100` in config expected to move the min-width
	// only. Post-#617 the scalar layer contributes only `minWidthPx`
	// — `previewWidthPx` and `label` still come from the DEFAULTS.
	$registry = BreakpointRegistry::fromLayers( [ 'lg' => 1100 ], [] );

	expect( $registry->get( 'lg' ) )->toBe( 1100 );
	expect( $registry->previewWidth( 'lg' ) )->toBe( 1440 );
	expect( $registry->label( 'lg' ) )->toBe( 'Desktop' );
} );

it( 'lets a partial-object override merge onto a scalar in a lower layer', function (): void {
	// Regression test for the #617 review finding: a config layer
	// stamps a scalar `'lg' => 1024` (pre-#617 style) and a theme.json
	// layer wants to tweak just the label. The theme's `[ 'label' =>
	// 'Big display' ]` merges into the scalar layer's `[ 'minWidthPx'
	// => 1024 ]` — no `missing minWidthPx` throw, no lost fields.
	$registry = BreakpointRegistry::fromLayers(
		[ 'lg' => 1024 ],
		[ 'lg' => [ 'label' => 'Big display' ] ],
	);

	expect( $registry->get( 'lg' ) )->toBe( 1024 );
	expect( $registry->label( 'lg' ) )->toBe( 'Big display' );
	// `previewWidthPx` still falls through from the DEFAULTS' `lg`
	// object — 1440px.
	expect( $registry->previewWidth( 'lg' ) )->toBe( 1440 );
} );

it( 'lets a theme.json object override win over the config layer', function (): void {
	$registry = BreakpointRegistry::fromLayers(
		[ 'sm' => [ 'previewWidthPx' => 400, 'label' => 'Config phone' ] ],
		[ 'sm' => [ 'previewWidthPx' => 428, 'label' => 'Theme phone' ] ],
	);

	expect( $registry->previewWidth( 'sm' ) )->toBe( 428 );
	expect( $registry->label( 'sm' ) )->toBe( 'Theme phone' );
} );

it( 'rejects object-form entries missing minWidthPx', function (): void {
	BreakpointRegistry::fromLayers(
		[ '3xl' => [ 'previewWidthPx' => 1920, 'label' => 'Wide' ] ],
		[],
	);
} )->throws( InvalidArgumentException::class, '`minWidthPx`' );

it( 'rejects object-form entries with a non-string label', function (): void {
	BreakpointRegistry::fromLayers(
		[ 'sm' => [ 'minWidthPx' => 640, 'label' => 42 ] ],
		[],
	);
} )->throws( InvalidArgumentException::class, 'label must be a string' );

it( 'rejects object-form entries with an empty label', function (): void {
	BreakpointRegistry::fromLayers(
		[ 'sm' => [ 'minWidthPx' => 640, 'label' => '   ' ] ],
		[],
	);
} )->throws( InvalidArgumentException::class, 'label must not be empty' );

it( 'rejects object-form entries with a non-positive previewWidthPx', function (): void {
	BreakpointRegistry::fromLayers(
		[ 'sm' => [ 'minWidthPx' => 640, 'previewWidthPx' => 0 ] ],
		[],
	);
} )->throws( InvalidArgumentException::class, '`previewWidthPx`' );

it( 'rejects object-form entries with an invalid previewWidthPx string', function (): void {
	BreakpointRegistry::fromLayers(
		[ 'sm' => [ 'minWidthPx' => 640, 'previewWidthPx' => '10rem' ] ],
		[],
	);
} )->throws( InvalidArgumentException::class, '`previewWidthPx`' );

it( 'exposes an entries() view of the extended shape', function (): void {
	$registry = BreakpointRegistry::fromLayers( [], [] );

	expect( $registry->entries()['sm'] )->toBe( [
		'minWidthPx'     => 640,
		'maxWidthPx'     => null,
		'previewWidthPx' => 375,
		'label'          => 'Mobile',
	] );
} );

it( 'serialises to the JS wire shape via toArray()', function (): void {
	$registry = BreakpointRegistry::fromLayers( [], [] );

	$array = $registry->toArray();

	expect( $array )->toHaveCount( 7 );
	expect( $array[0] )->toBe( [
		'key'            => 'sm',
		'minWidthPx'     => 640,
		'previewWidthPx' => 375,
		'label'          => 'Mobile',
	] );
	expect( array_column( $array, 'key' ) )->toBe( [ 'sm', 'md', 'lg', 'xl', '2xl', 'tablet', 'mobile' ] );
	expect( $array[6] )->toBe( [
		'key'            => 'mobile',
		'maxWidthPx'     => 767,
		'previewWidthPx' => 375,
		'label'          => 'Mobile',
	] );
} );

it( 'lets an explicit null in a higher layer remove a default breakpoint', function (): void {
	$registry = BreakpointRegistry::fromLayers( [ 'xl' => null ], [] );

	expect( $registry->has( 'xl' ) )->toBeFalse();
	expect( $registry->prefixes() )->toBe( [ 'sm', 'md', 'lg', '2xl', 'tablet', 'mobile' ] );
} );

describe( 'desktop-first device breakpoints (#820)', function (): void {
	it( 'registers tablet and mobile as max-width overrides with in-range previews', function (): void {
		$registry = BreakpointRegistry::fromLayers( [], [] );

		expect( $registry->devicePrefixes() )->toBe( [ 'tablet', 'mobile' ] );
		expect( $registry->legacyPrefixes() )->toBe( [ 'sm', 'md', 'lg', 'xl', '2xl' ] );
		expect( $registry->maxWidth( 'mobile' ) )->toBe( 767 );
		expect( $registry->maxWidth( 'tablet' ) )->toBe( 1023 );
		expect( $registry->previewWidth( 'mobile' ) )->toBeLessThanOrEqual( 767 );
		expect( $registry->previewWidth( 'tablet' ) )->toBeGreaterThan( 767 )->toBeLessThanOrEqual( 1023 );
		expect( $registry->get( 'mobile' ) )->toBeNull();
		expect( $registry->isLegacy( 'sm' ) )->toBeTrue();
		expect( $registry->isLegacy( 'mobile' ) )->toBeFalse();
	} );

	it( 'keeps all() to the legacy min-width entries', function (): void {
		expect( array_keys( BreakpointRegistry::fromLayers( [], [] )->all() ) )->toBe( [ 'sm', 'md', 'lg', 'xl', '2xl' ] );
	} );

	it( 'builds max-width queries for device keys and min-width queries for legacy keys', function (): void {
		$registry = BreakpointRegistry::fromLayers( [], [] );

		expect( $registry->mediaQuery( 'mobile' ) )->toBe( '(max-width:767px)' );
		expect( $registry->mediaQuery( 'md' ) )->toBe( '(min-width:768px)' );
		expect( $registry->mediaQuery( 'tablet', true ) )->toBe( '(max-width: 1023px)' );
		expect( $registry->mediaQuery( 'base' ) )->toBeNull();
		expect( $registry->mediaQuery( 'nope' ) )->toBeNull();
	} );

	it( 'cascades downward: mobile inherits from tablet then base', function (): void {
		$registry = BreakpointRegistry::fromLayers( [], [] );

		expect( $registry->cascade( 'mobile', false ) )->toBe( [ 'mobile', 'tablet', 'base' ] );
		expect( $registry->cascade( 'tablet', false ) )->toBe( [ 'tablet', 'base' ] );
		expect( $registry->cascade( 'base' ) )->toBe( [ 'base' ] );
	} );

	it( 'includes the legacy keys that match at a device preview width', function (): void {
		$registry = BreakpointRegistry::fromLayers( [], [] );

		// Tablet previews at 768px, where legacy sm (640+) and md (768+) also apply.
		expect( $registry->cascade( 'tablet' ) )->toBe( [ 'tablet', 'md', 'sm', 'base' ] );
		// Mobile previews at 375px — no legacy rule matches.
		expect( $registry->cascade( 'mobile' ) )->toBe( [ 'mobile', 'tablet', 'base' ] );
	} );

	it( 'keeps the legacy mobile-first cascade for legacy keys', function (): void {
		expect( BreakpointRegistry::fromLayers( [], [] )->cascade( 'lg' ) )->toBe( [ 'lg', 'md', 'sm', 'base' ] );
	} );

	it( 'accepts custom device breakpoints from config, sorted descending', function (): void {
		$registry = BreakpointRegistry::fromLayers( [
			'phablet' => [ 'maxWidthPx' => 900, 'previewWidthPx' => 820, 'label' => 'Phablet' ],
			'mobile'  => '600px',
		], [] );

		expect( $registry->devicePrefixes() )->toBe( [ 'tablet', 'phablet', 'mobile' ] );
		expect( $registry->maxWidth( 'mobile' ) )->toBe( 600 );
		expect( $registry->cascade( 'mobile', false ) )->toBe( [ 'mobile', 'phablet', 'tablet', 'base' ] );
	} );

	it( 'lets a layer switch an entry between families', function (): void {
		$registry = BreakpointRegistry::fromLayers( [ 'md' => [ 'maxWidthPx' => 900 ] ], [] );

		expect( $registry->isLegacy( 'md' ) )->toBeFalse();
		expect( $registry->maxWidth( 'md' ) )->toBe( 900 );
	} );

	it( 'rejects an entry with both widths', function (): void {
		new BreakpointRegistry( [ 'x' => [ 'minWidthPx' => 100, 'maxWidthPx' => 200 ] ] );
	} )->throws( InvalidArgumentException::class, 'both' );

	it( 'rejects a device preview wider than its max-width', function (): void {
		new BreakpointRegistry( [ 'x' => [ 'maxWidthPx' => 500, 'previewWidthPx' => 600 ] ] );
	} )->throws( InvalidArgumentException::class, 'must not exceed' );

	it( 'rejects duplicate max-widths', function (): void {
		new BreakpointRegistry( [ 'a' => [ 'maxWidthPx' => 500 ], 'b' => [ 'maxWidthPx' => 500 ] ] );
	} )->throws( InvalidArgumentException::class, 'same max-width' );
} );

describe( 'scalar device width overrides (#820)', function (): void {
	it( 'clamps an inherited preview width that no longer fits', function (): void {
		$registry = BreakpointRegistry::fromLayers( [ 'tablet' => 760, 'mobile' => '360px' ], [] );

		expect( $registry->maxWidth( 'tablet' ) )->toBe( 760 );
		expect( $registry->previewWidth( 'tablet' ) )->toBe( 760 );
		expect( $registry->maxWidth( 'mobile' ) )->toBe( 360 );
		expect( $registry->previewWidth( 'mobile' ) )->toBe( 360 );
	} );

	it( 'keeps an inherited preview width that still fits', function (): void {
		expect( BreakpointRegistry::fromLayers( [ 'mobile' => '600px' ], [] )->previewWidth( 'mobile' ) )->toBe( 375 );
	} );

	it( 'still rejects an explicit preview wider than the max-width', function (): void {
		BreakpointRegistry::fromLayers( [ 'mobile' => [ 'maxWidthPx' => 600, 'previewWidthPx' => 700 ] ], [] );
	} )->throws( InvalidArgumentException::class, 'must not exceed' );
} );
