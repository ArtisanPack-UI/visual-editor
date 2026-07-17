<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Visibility\Rules\QueryStringRule;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityContext;

function qsContext( array $query ): VisibilityContext
{
	return new VisibilityContext( queryString: $query );
}

it( 'is visible with no clauses', function () {
	$rule = new QueryStringRule();
	expect( $rule->evaluate( [], qsContext( [ 'a' => 'b' ] ) )->isVisible() )->toBeTrue();
} );

it( 'shows when a clause matches (direction=show, combinator=any)', function () {
	$rule = new QueryStringRule();
	$attrs = [
		'direction'  => 'show',
		'combinator' => 'any',
		'clauses'    => [ [ 'key' => 'utm_source', 'value' => 'newsletter' ] ],
	];
	expect( $rule->evaluate( $attrs, qsContext( [ 'utm_source' => 'newsletter' ] ) )->isVisible() )->toBeTrue();
	expect( $rule->evaluate( $attrs, qsContext( [ 'utm_source' => 'other' ] ) )->isHidden() )->toBeTrue();
} );

it( 'supports the wildcard value "*" meaning "key present"', function () {
	$rule = new QueryStringRule();
	$attrs = [ 'direction' => 'show', 'clauses' => [ [ 'key' => 'debug', 'value' => '*' ] ] ];
	expect( $rule->evaluate( $attrs, qsContext( [ 'debug' => 'anything' ] ) )->isVisible() )->toBeTrue();
	expect( $rule->evaluate( $attrs, qsContext( [] ) )->isHidden() )->toBeTrue();
} );

it( 'supports combinator=all', function () {
	$rule = new QueryStringRule();
	$attrs = [
		'direction'  => 'show',
		'combinator' => 'all',
		'clauses'    => [
			[ 'key' => 'a', 'value' => '1' ],
			[ 'key' => 'b', 'value' => '2' ],
		],
	];
	expect( $rule->evaluate( $attrs, qsContext( [ 'a' => '1', 'b' => '2' ] ) )->isVisible() )->toBeTrue();
	expect( $rule->evaluate( $attrs, qsContext( [ 'a' => '1' ] ) )->isHidden() )->toBeTrue();
} );

it( 'inverts when direction=hide', function () {
	$rule = new QueryStringRule();
	$attrs = [ 'direction' => 'hide', 'clauses' => [ [ 'key' => 'preview', 'value' => 'true' ] ] ];
	expect( $rule->evaluate( $attrs, qsContext( [ 'preview' => 'true' ] ) )->isHidden() )->toBeTrue();
	expect( $rule->evaluate( $attrs, qsContext( [] ) )->isVisible() )->toBeTrue();
} );
