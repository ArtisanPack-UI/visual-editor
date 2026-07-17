<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Visibility\RuleRegistry;
use ArtisanPackUI\VisualEditor\Visibility\Rules\HideRule;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityContext;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityDecision;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityEvaluator;
use Illuminate\Config\Repository;

function makeContext(): VisibilityContext
{
	return new VisibilityContext();
}

function makeEvaluator( array $overrides = [] ): VisibilityEvaluator
{
	// array_merge_recursive on `bool` leaves you with `[true, false]`;
	// use `set()` for the override path so `enabled=false` wins cleanly.
	$config = new Repository( [ 'artisanpack' => [ 'visual-editor' => [ 'visibility' => [ 'enabled' => true ] ] ] ] );

	foreach ( $overrides as $path => $value ) {
		$config->set( $path, $value );
	}

	return new VisibilityEvaluator(
		new RuleRegistry( [ new HideRule() ] ),
		$config,
	);
}

it( 'returns visible for a block with no visibility slice', function () {
	$evaluator = makeEvaluator();

	$decision = $evaluator->evaluate(
		[ 'name' => 'artisanpack/paragraph', 'attributes' => [] ],
		makeContext(),
	);

	expect( $decision->isVisible() )->toBeTrue();
} );

it( 'hides a block when the Hide rule is toggled on', function () {
	$evaluator = makeEvaluator();

	$decision = $evaluator->evaluate(
		[
			'name'       => 'artisanpack/paragraph',
			'attributes' => [
				'artisanpackVisibility' => [ 'hide' => [ 'hidden' => true ] ],
			],
		],
		makeContext(),
	);

	expect( $decision->isHidden() )->toBeTrue();
	expect( $decision->reasons )->toContain( 'hide' );
} );

it( 'short-circuits when the site-wide kill switch is off', function () {
	$evaluator = makeEvaluator( [
		'artisanpack.visual-editor.visibility.enabled' => false,
	] );

	$decision = $evaluator->evaluate(
		[
			'name'       => 'artisanpack/paragraph',
			'attributes' => [ 'artisanpackVisibility' => [ 'hide' => [ 'hidden' => true ] ] ],
		],
		makeContext(),
	);

	expect( $decision->isVisible() )->toBeTrue();
} );

it( 'respects supports.artisanpackVisibility === false via primeSupports()', function () {
	$evaluator = makeEvaluator();
	$evaluator->primeSupports( [ 'artisanpack/opt-out' => false ] );

	$decision = $evaluator->evaluate(
		[
			'name'       => 'artisanpack/opt-out',
			'attributes' => [ 'artisanpackVisibility' => [ 'hide' => [ 'hidden' => true ] ] ],
		],
		makeContext(),
	);

	expect( $decision->isVisible() )->toBeTrue();
} );

it( 'combines two hidden decisions into one hidden decision', function () {
	$a = VisibilityDecision::hidden( [ 'a' ] );
	$b = VisibilityDecision::hidden( [ 'b' ] );

	$combined = $a->combine( $b );

	expect( $combined->isHidden() )->toBeTrue();
	expect( $combined->reasons )->toEqualCanonicalizing( [ 'a', 'b' ] );
} );

it( 'combines visible with cssHidden into cssHidden', function () {
	$a = VisibilityDecision::visible();
	$b = VisibilityDecision::cssHidden( [ 'sm' ], [ 'screenSize' ] );

	$combined = $a->combine( $b );

	expect( $combined->isCssHidden() )->toBeTrue();
	expect( $combined->hiddenBreakpoints )->toBe( [ 'sm' ] );
} );

it( 'hidden wins over cssHidden when combined', function () {
	$a = VisibilityDecision::cssHidden( [ 'sm' ] );
	$b = VisibilityDecision::hidden( [ 'hide' ] );

	$combined = $a->combine( $b );

	expect( $combined->isHidden() )->toBeTrue();
} );
