<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Support\FlexSupport;

/**
 * #595 — flex serializer parity with the shared fixtures.
 *
 * Every fixture in `resources/js/visual-editor/blocks/_shared/
 * flex-controls/fixtures.json` is replayed through `FlexSupport` and
 * the class lists must match byte-exact. Keeps the Blade + JS
 * serializers in lockstep without a build step.
 */

it( 'matches shared fixtures byte-for-byte', function ( string $name, $input, array $expected ) {
	$support = app( FlexSupport::class );
	$result  = $support->serialize( $input );

	$expectedClasses = array_merge( $expected[ 'containerClasses' ], $expected[ 'itemClasses' ] );

	expect( $result[ 'classes' ] )->toEqual( $expectedClasses )
		->and( $result[ 'arbitraryRules' ] )->toEqual( $expected[ 'arbitraryRules' ] );
} )->with( function () {
	$path = __DIR__ . '/../../../../resources/js/visual-editor/blocks/_shared/flex-controls/fixtures.json';
	$json = file_get_contents( $path );
	if ( false === $json ) {
		throw new RuntimeException( "Failed to read fixtures file: {$path}" );
	}
	$decoded = json_decode( $json, true );
	if ( null === $decoded || JSON_ERROR_NONE !== json_last_error() ) {
		throw new RuntimeException( 'Failed to decode fixtures JSON: ' . json_last_error_msg() );
	}
	$fixtures = $decoded[ 'fixtures' ] ?? [];

	$cases = [];
	foreach ( $fixtures as $fixture ) {
		$cases[ $fixture[ 'name' ] ] = [
			$fixture[ 'name' ],
			$fixture[ 'input' ] ?? null,
			$fixture[ 'expected' ],
		];
	}

	return $cases;
} );

it( 'returns empty result for null input', function () {
	$support = app( FlexSupport::class );
	$result  = $support->serialize( null );

	expect( $result[ 'classes' ] )->toEqual( [] )
		->and( $result[ 'arbitraryRules' ] )->toEqual( [] );
} );

/**
 * #720 — the arbitrary value is spliced straight into a `<style>` block, so
 * `buildArbitraryStyles` must drop any value that fails the shared CSS-value
 * whitelist rather than emit a rule that could inject attacker CSS.
 */
it( 'emits safe arbitrary values verbatim', function () {
	$support = app( FlexSupport::class );

	$css = $support->buildArbitraryStyles( [
		[ 'className' => 'ap-basis-[200px]', 'property' => 'flex-basis', 'value' => '200px', 'breakpoint' => 'base' ],
		[ 'className' => 'ap-gap-x-[1rem]', 'property' => 'column-gap', 'value' => '1rem', 'breakpoint' => 'base' ],
	] );

	expect( $css )->toContain( 'flex-basis: 200px;' )
		->and( $css )->toContain( 'column-gap: 1rem;' );
} );

it( 'drops a hostile arbitrary value so it never reaches the stylesheet', function () {
	$support = app( FlexSupport::class );

	$css = $support->buildArbitraryStyles( [
		[ 'className' => 'ap-basis-[x]', 'property' => 'flex-basis', 'value' => '200px}body{display:none', 'breakpoint' => 'base' ],
	] );

	expect( $css )->toBe( '' );
} );

it( 'skips a breakpoint whose only rule is hostile, emitting no @media block', function () {
	$support = app( FlexSupport::class );

	$css = $support->buildArbitraryStyles( [
		[ 'className' => 'ap-basis-[200px]', 'property' => 'flex-basis', 'value' => '200px', 'breakpoint' => 'base' ],
		[ 'className' => 'md:ap-basis-[x]', 'property' => 'flex-basis', 'value' => '50%}html{opacity:0', 'breakpoint' => 'md' ],
	] );

	expect( $css )->toContain( 'flex-basis: 200px;' )
		->and( $css )->not->toContain( '@media' )
		->and( $css )->not->toContain( 'opacity' );
} );
