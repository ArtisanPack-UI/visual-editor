<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Registries\BlockTypeRegistry;

/**
 * #691 — `BlockMarkupHydrator::recoverAttributes()` looks a block up in the
 * `BlockTypeRegistry` before it can replay the manifest's `source`
 * definitions against the saved markup. A manifest that declares a sourced
 * attribute but never reaches the registry silently loses that attribute:
 * `artisanpack/marquee` shipped that way and rendered an empty marquee.
 *
 * This guard walks every bundled manifest and fails the moment a new sourced
 * block is added without a matching entry in
 * `VisualEditorServiceProvider::registerForkedBlocks()` or
 * `registerReferenceBlocks()`.
 *
 * Note that a manifest registered behind a soft-dependency gate (`form`
 * behind artisanpack-ui/forms, `latest-posts` behind cms-framework) would
 * fail this guard if it ever grew a sourced attribute — correctly so, since
 * the attribute genuinely would not recover in an install lacking that
 * package. None of them declare one today.
 */
it( 'registers every bundled block that declares a sourced attribute', function () {
	$blocksDir = __DIR__ . '/../../../resources/js/visual-editor/blocks';
	$manifests = glob( $blocksDir . '/*/block.json' ) ?: [];

	expect( $manifests )->not->toBeEmpty();

	$registry = app( BlockTypeRegistry::class );
	$missing  = [];

	foreach ( $manifests as $manifest ) {
		$metadata = json_decode( (string) file_get_contents( $manifest ), true, 512, JSON_THROW_ON_ERROR );

		$attributes = $metadata['attributes'] ?? [];

		if ( ! is_array( $attributes ) ) {
			continue;
		}

		$hasSourcedAttribute = false;

		foreach ( $attributes as $attribute ) {
			if ( is_array( $attribute ) && isset( $attribute['source'] ) ) {
				$hasSourcedAttribute = true;

				break;
			}
		}

		if ( ! $hasSourcedAttribute ) {
			continue;
		}

		$name = $metadata['name'] ?? '';

		if ( null === $registry->get( (string) $name ) ) {
			$missing[] = $name;
		}
	}

	expect( $missing )->toBe(
		[],
		'Blocks declaring a sourced attribute must be registered in VisualEditorServiceProvider: ' . implode( ', ', $missing )
	);
} );

it( 'registers the marquee block from its bundled manifest', function () {
	$block = app( BlockTypeRegistry::class )->get( 'artisanpack/marquee' );

	expect( $block )->not->toBeNull()
		->and( $block['title'] )->toBe( 'Marquee' )
		->and( $block['attributes']['marqueeContent']['source'] )->toBe( 'html' );
} );
