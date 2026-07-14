<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;

it( 'falls back to Tailwind v4 defaults when nothing overrides them', function () {
	$registry = BreakpointRegistry::fromLayers( [], [] );

	expect( $registry->all() )->toBe( [
		'sm'  => 640,
		'md'  => 768,
		'lg'  => 1024,
		'xl'  => 1280,
		'2xl' => 1536,
	] );
} );

it( 'merges config overrides on top of defaults', function () {
	$registry = BreakpointRegistry::fromLayers( [ 'lg' => 1100 ], [] );

	expect( $registry->get( 'lg' ) )->toBe( 1100 );
	expect( $registry->get( 'md' ) )->toBe( 768 );
} );

it( 'merges theme.json overrides on top of config', function () {
	$registry = BreakpointRegistry::fromLayers(
		[ 'lg' => 1100 ],
		[ 'lg' => '1200px', '3xl' => 1920 ]
	);

	expect( $registry->get( 'lg' ) )->toBe( 1200 );
	expect( $registry->get( '3xl' ) )->toBe( 1920 );
	expect( $registry->prefixes() )->toContain( '3xl' );
} );

it( 'sorts the registry ascending by min-width', function () {
	$registry = BreakpointRegistry::fromLayers(
		[],
		[ '3xl' => 1920, 'xxs' => 320 ]
	);

	expect( array_keys( $registry->all() ) )->toBe( [
		'xxs', 'sm', 'md', 'lg', 'xl', '2xl', '3xl',
	] );
} );

it( 'returns 0 for the implicit base slot and exposes it in keysWithBase()', function () {
	$registry = BreakpointRegistry::fromLayers( [], [] );

	expect( $registry->get( 'base' ) )->toBe( 0 );
	expect( $registry->keysWithBase() )->toBe( [ 'base', 'sm', 'md', 'lg', 'xl', '2xl' ] );
	expect( $registry->has( 'base' ) )->toBeTrue();
} );

it( 'rejects the reserved `base` key during validation', function () {
	BreakpointRegistry::fromLayers( [ 'base' => 0 ], [] );
} )->throws( InvalidArgumentException::class, 'reserved' );

it( 'rejects breakpoints with non-positive widths', function () {
	BreakpointRegistry::fromLayers( [ 'sm' => 0 ], [] );
} )->throws( InvalidArgumentException::class, 'positive pixel value' );

it( 'rejects breakpoints with duplicate widths', function () {
	BreakpointRegistry::fromLayers( [], [ 'foo' => 640 ] );
} )->throws( InvalidArgumentException::class, 'same min-width' );

it( 'rejects breakpoints with non-numeric strings', function () {
	BreakpointRegistry::fromLayers( [], [ 'foo' => '10rem' ] );
} )->throws( InvalidArgumentException::class, 'invalid value' );

it( 'rejects breakpoints with invalid key characters', function () {
	BreakpointRegistry::fromLayers( [], [ 'big screen!' => 1900 ] );
} )->throws( InvalidArgumentException::class, 'letters, numbers' );

/*
|--------------------------------------------------------------------------
| #617 — device labels + preview widths
|--------------------------------------------------------------------------
*/

it( 'ships Mobile/Tablet/Desktop labels and device preview widths by default', function () {
	$registry = BreakpointRegistry::fromLayers( [], [] );

	expect( $registry->label( 'sm' ) )->toBe( 'Mobile' );
	expect( $registry->label( 'md' ) )->toBe( 'Tablet' );
	expect( $registry->label( 'lg' ) )->toBe( 'Desktop' );
	expect( $registry->previewWidth( 'sm' ) )->toBe( 375 );
	expect( $registry->previewWidth( 'md' ) )->toBe( 768 );
	expect( $registry->previewWidth( 'lg' ) )->toBe( 1440 );
} );

it( 'returns 0 previewWidth for the implicit base slot and null for unknown keys', function () {
	$registry = BreakpointRegistry::fromLayers( [], [] );

	expect( $registry->previewWidth( 'base' ) )->toBe( 0 );
	expect( $registry->previewWidth( 'nope' ) )->toBeNull();
	expect( $registry->label( 'nope' ) )->toBeNull();
} );

it( 'accepts full object-form config entries', function () {
	$registry = BreakpointRegistry::fromLayers(
		[
			'sm' => [
				'minWidthPx'     => 640,
				'previewWidthPx' => 390,
				'label'          => 'iPhone',
			],
		],
		[]
	);

	expect( $registry->get( 'sm' ) )->toBe( 640 );
	expect( $registry->previewWidth( 'sm' ) )->toBe( 390 );
	expect( $registry->label( 'sm' ) )->toBe( 'iPhone' );
} );

it( 'lets partial object overrides merge into the default at the same key', function () {
	$registry = BreakpointRegistry::fromLayers(
		[ 'lg' => [ 'previewWidthPx' => 1600 ] ],
		[]
	);

	// Only previewWidthPx was overridden — minWidthPx + label stay from the default.
	expect( $registry->get( 'lg' ) )->toBe( 1024 );
	expect( $registry->previewWidth( 'lg' ) )->toBe( 1600 );
	expect( $registry->label( 'lg' ) )->toBe( 'Desktop' );
} );

it( 'normalises a bare scalar override to { minWidthPx, minWidthPx, key } for NEW keys', function () {
	// A scalar entry that introduces a fresh key (no default to
	// inherit from) resolves to `{ minWidthPx: value, previewWidthPx:
	// value, label: key }` — the back-compat guarantee documented in
	// #617.
	$registry = BreakpointRegistry::fromLayers( [ 'zoom' => '900px' ], [] );

	expect( $registry->get( 'zoom' ) )->toBe( 900 );
	expect( $registry->previewWidth( 'zoom' ) )->toBe( 900 );
	expect( $registry->label( 'zoom' ) )->toBe( 'zoom' );
} );

it( 'lets a scalar override an existing default without wiping the default label/previewWidthPx', function () {
	// Regression test for the #617 review finding: a pre-#617 host
	// with `'lg' => 1100` in config expected to move the min-width
	// only. Post-#617 the scalar layer contributes only `minWidthPx`
	// — `previewWidthPx` and `label` still come from the DEFAULTS.
	$registry = BreakpointRegistry::fromLayers( [ 'lg' => 1100 ], [] );

	expect( $registry->get( 'lg' ) )->toBe( 1100 );
	expect( $registry->previewWidth( 'lg' ) )->toBe( 1440 );
	expect( $registry->label( 'lg' ) )->toBe( 'Desktop' );
} );

it( 'lets a partial-object override merge onto a scalar in a lower layer', function () {
	// Regression test for the #617 review finding: a config layer
	// stamps a scalar `'lg' => 1024` (pre-#617 style) and a theme.json
	// layer wants to tweak just the label. The theme's `[ 'label' =>
	// 'Big display' ]` merges into the scalar layer's `[ 'minWidthPx'
	// => 1024 ]` — no `missing minWidthPx` throw, no lost fields.
	$registry = BreakpointRegistry::fromLayers(
		[ 'lg' => 1024 ],
		[ 'lg' => [ 'label' => 'Big display' ] ]
	);

	expect( $registry->get( 'lg' ) )->toBe( 1024 );
	expect( $registry->label( 'lg' ) )->toBe( 'Big display' );
	// `previewWidthPx` still falls through from the DEFAULTS' `lg`
	// object — 1440px.
	expect( $registry->previewWidth( 'lg' ) )->toBe( 1440 );
} );

it( 'lets a theme.json object override win over the config layer', function () {
	$registry = BreakpointRegistry::fromLayers(
		[ 'sm' => [ 'previewWidthPx' => 400, 'label' => 'Config phone' ] ],
		[ 'sm' => [ 'previewWidthPx' => 428, 'label' => 'Theme phone' ] ]
	);

	expect( $registry->previewWidth( 'sm' ) )->toBe( 428 );
	expect( $registry->label( 'sm' ) )->toBe( 'Theme phone' );
} );

it( 'rejects object-form entries missing minWidthPx', function () {
	BreakpointRegistry::fromLayers(
		[ '3xl' => [ 'previewWidthPx' => 1920, 'label' => 'Wide' ] ],
		[]
	);
} )->throws( InvalidArgumentException::class, '`minWidthPx`' );

it( 'rejects object-form entries with a non-string label', function () {
	BreakpointRegistry::fromLayers(
		[ 'sm' => [ 'minWidthPx' => 640, 'label' => 42 ] ],
		[]
	);
} )->throws( InvalidArgumentException::class, 'label must be a string' );

it( 'rejects object-form entries with an empty label', function () {
	BreakpointRegistry::fromLayers(
		[ 'sm' => [ 'minWidthPx' => 640, 'label' => '   ' ] ],
		[]
	);
} )->throws( InvalidArgumentException::class, 'label must not be empty' );

it( 'rejects object-form entries with a non-positive previewWidthPx', function () {
	BreakpointRegistry::fromLayers(
		[ 'sm' => [ 'minWidthPx' => 640, 'previewWidthPx' => 0 ] ],
		[]
	);
} )->throws( InvalidArgumentException::class, '`previewWidthPx`' );

it( 'rejects object-form entries with an invalid previewWidthPx string', function () {
	BreakpointRegistry::fromLayers(
		[ 'sm' => [ 'minWidthPx' => 640, 'previewWidthPx' => '10rem' ] ],
		[]
	);
} )->throws( InvalidArgumentException::class, '`previewWidthPx`' );

it( 'exposes an entries() view of the extended shape', function () {
	$registry = BreakpointRegistry::fromLayers( [], [] );

	expect( $registry->entries()['sm'] )->toBe( [
		'minWidthPx'     => 640,
		'previewWidthPx' => 375,
		'label'          => 'Mobile',
	] );
} );

it( 'serialises to the JS wire shape via toArray()', function () {
	$registry = BreakpointRegistry::fromLayers( [], [] );

	$array = $registry->toArray();

	expect( $array )->toHaveCount( 5 );
	expect( $array[0] )->toBe( [
		'key'            => 'sm',
		'minWidthPx'     => 640,
		'previewWidthPx' => 375,
		'label'          => 'Mobile',
	] );
	expect( array_column( $array, 'key' ) )->toBe( [ 'sm', 'md', 'lg', 'xl', '2xl' ] );
} );

it( 'lets an explicit null in a higher layer remove a default breakpoint', function () {
	$registry = BreakpointRegistry::fromLayers( [ 'xl' => null ], [] );

	expect( $registry->has( 'xl' ) )->toBeFalse();
	expect( $registry->prefixes() )->toBe( [ 'sm', 'md', 'lg', '2xl' ] );
} );
