<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Embed\MapEmbed\MapEmbedBlock;

test( 'map embed block has correct type and category', function (): void {
	$block = new MapEmbedBlock();

	expect( $block->getType() )->toBe( 'map-embed' );
	expect( $block->getCategory() )->toBe( 'embed' );
} );

test( 'map embed block content schema has provider and location fields', function (): void {
	$block  = new MapEmbedBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'provider' );
	expect( $schema )->toHaveKey( 'address' );
	expect( $schema )->toHaveKey( 'latitude' );
	expect( $schema )->toHaveKey( 'longitude' );
	expect( $schema )->toHaveKey( 'zoom' );
	expect( $schema )->toHaveKey( 'mapType' );
	expect( $schema )->toHaveKey( 'markerLabel' );
	expect( $schema )->toHaveKey( 'interactive' );
} );

test( 'map embed block style schema has height field', function (): void {
	$block  = new MapEmbedBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'height' );
	expect( $schema['height']['default'] )->toBe( '400px' );
} );

test( 'map embed block defaults to openstreetmap provider', function (): void {
	$block    = new MapEmbedBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['provider'] )->toBe( 'openstreetmap' );
	expect( $defaults['zoom'] )->toBe( 13 );
	expect( $defaults['interactive'] )->toBeTrue();
} );

test( 'map embed block renders openstreetmap iframe', function (): void {
	$block  = new MapEmbedBlock();
	$output = $block->render(
		[
			'provider'    => 'openstreetmap',
			'latitude'    => '40.7128',
			'longitude'   => '-74.0060',
			'zoom'        => 13,
			'mapType'     => 'roadmap',
			'address'     => 'New York, NY',
			'markerLabel' => '',
			'interactive' => true,
		],
		[ 'height' => '400px' ],
	);

	expect( $output )->toContain( 've-block-map-embed' );
	expect( $output )->toContain( 'openstreetmap.org' );
	expect( $output )->toContain( 'iframe' );
} );

test( 'map embed block renders google maps iframe', function (): void {
	$block  = new MapEmbedBlock();
	$output = $block->render(
		[
			'provider'    => 'google',
			'latitude'    => '40.7128',
			'longitude'   => '-74.0060',
			'zoom'        => 13,
			'mapType'     => 'roadmap',
			'address'     => 'New York, NY',
			'markerLabel' => '',
			'interactive' => true,
		],
		[ 'height' => '400px' ],
	);

	expect( $output )->toContain( 've-block-map-embed' );
	expect( $output )->toContain( 'maps.google.com' );
} );

test( 'map embed block renders static placeholder without coordinates', function (): void {
	$block  = new MapEmbedBlock();
	$output = $block->render(
		[
			'provider'    => 'openstreetmap',
			'latitude'    => '',
			'longitude'   => '',
			'zoom'        => 13,
			'mapType'     => 'roadmap',
			'address'     => '',
			'markerLabel' => '',
			'interactive' => true,
		],
		[ 'height' => '400px' ],
	);

	expect( $output )->toContain( 've-block-map-embed' );
	expect( $output )->not->toContain( 'iframe' );
} );

test( 'map embed block has keywords', function (): void {
	$block = new MapEmbedBlock();

	expect( $block->getKeywords() )->toContain( 'map' );
	expect( $block->getKeywords() )->toContain( 'location' );
} );

test( 'map embed block is public', function (): void {
	$block = new MapEmbedBlock();

	expect( $block->isPublic() )->toBeTrue();
} );

test( 'map embed block supports border and shadow', function (): void {
	$block = new MapEmbedBlock();

	expect( $block->supportsFeature( 'border' ) )->toBeTrue();
	expect( $block->supportsFeature( 'shadow' ) )->toBeTrue();
} );

test( 'map embed block has transform to generic embed', function (): void {
	$block      = new MapEmbedBlock();
	$transforms = $block->getTransforms();

	expect( $transforms )->toHaveKey( 'embed' );
} );

test( 'map embed block provider options include google and openstreetmap', function (): void {
	$block   = new MapEmbedBlock();
	$schema  = $block->getContentSchema();
	$options = $schema['provider']['options'];

	expect( $options )->toHaveKey( 'openstreetmap' );
	expect( $options )->toHaveKey( 'google' );
} );

test( 'map embed block zoom range has min and max', function (): void {
	$block  = new MapEmbedBlock();
	$schema = $block->getContentSchema();

	expect( $schema['zoom']['min'] )->toBe( 1 );
	expect( $schema['zoom']['max'] )->toBe( 20 );
} );
