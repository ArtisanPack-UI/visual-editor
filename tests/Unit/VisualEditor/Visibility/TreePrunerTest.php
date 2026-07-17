<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Visibility\RuleRegistry;
use ArtisanPackUI\VisualEditor\Visibility\Rules\HideRule;
use ArtisanPackUI\VisualEditor\Visibility\TreePruner;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityContext;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityEvaluator;
use Illuminate\Config\Repository;

function prunerWith( array $rules ): TreePruner
{
	$config = new Repository( [ 'artisanpack' => [ 'visual-editor' => [ 'visibility' => [ 'enabled' => true ] ] ] ] );
	return new TreePruner( new VisibilityEvaluator( new RuleRegistry( $rules ), $config ) );
}

it( 'drops hidden blocks from the top-level tree', function () {
	$pruner = prunerWith( [ new HideRule() ] );

	$tree = [
		[ 'name' => 'artisanpack/paragraph', 'attributes' => [] ],
		[ 'name' => 'artisanpack/paragraph', 'attributes' => [ 'artisanpackVisibility' => [ 'hide' => [ 'hidden' => true ] ] ] ],
		[ 'name' => 'artisanpack/heading',   'attributes' => [] ],
	];

	$result = $pruner->prune( $tree, new VisibilityContext() );

	expect( $result )->toHaveCount( 2 );
	expect( $result[0]['name'] )->toBe( 'artisanpack/paragraph' );
	expect( $result[1]['name'] )->toBe( 'artisanpack/heading' );
} );

it( 'drops hidden inner blocks and preserves surviving parent + siblings', function () {
	$pruner = prunerWith( [ new HideRule() ] );

	$tree = [
		[
			'name'        => 'artisanpack/group',
			'attributes'  => [],
			'innerBlocks' => [
				[ 'name' => 'artisanpack/paragraph', 'attributes' => [] ],
				[ 'name' => 'artisanpack/paragraph', 'attributes' => [ 'artisanpackVisibility' => [ 'hide' => [ 'hidden' => true ] ] ] ],
			],
		],
	];

	$result = $pruner->prune( $tree, new VisibilityContext() );

	expect( $result )->toHaveCount( 1 );
	expect( $result[0]['innerBlocks'] )->toHaveCount( 1 );
} );
