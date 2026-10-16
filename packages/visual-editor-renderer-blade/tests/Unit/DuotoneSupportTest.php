<?php

/**
 * Unit tests for {@see DuotoneSupport} and its accumulator (#873).
 *
 * @since 1.15.0
 */

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\VisualEditorRendererBlade\Services\DuotoneCssAccumulator;
use ArtisanPackUI\VisualEditorRendererBlade\Services\GlobalStylesEmissionResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Support\DuotoneSupport;
use Illuminate\Support\Facades\Blade;

/**
 * @param  mixed  $duotone
 *
 * @return array<string, mixed>
 */
function duotoneAttributes( mixed $duotone ): array
{
	return [ 'style' => [ 'color' => [ 'duotone' => $duotone ] ] ];
}

describe( 'DuotoneSupport::compile', function (): void {
	it( 'compiles a custom color pair into a scope class, rule and SVG filter', function (): void {
		$compiled = DuotoneSupport::compile( duotoneAttributes( [ '#000000', '#ffffff' ] ) );

		expect( $compiled['class'] )->toMatch( '/^wp-duotone-[0-9a-f]{10}$/' )
			->and( $compiled['filterId'] )->toBe( $compiled['class'] )
			->and( $compiled['rules'] )->toBe( sprintf( '.%1$s img{filter:url(#%1$s) !important}', $compiled['class'] ) )
			->and( $compiled['filter'] )
			->toContain( sprintf( '<filter id="%s">', $compiled['class'] ) )
			->toContain( '<feFuncR type="table" tableValues="0 1"/>' )
			->toContain( '<feFuncA type="table" tableValues="1 1"/>' )
			->toContain( '<feComposite in2="SourceGraphic" operator="in"/>' );
	} );

	it( 'parses short hex, alpha hex and rgb() stops', function (): void {
		$compiled = DuotoneSupport::compile( duotoneAttributes( [ '#f00', 'rgba(0, 0, 255, 0.5)', '#00ff0080' ] ) );

		expect( $compiled['filter'] )
			->toContain( '<feFuncR type="table" tableValues="1 0 0"/>' )
			->toContain( '<feFuncB type="table" tableValues="0 1 0"/>' )
			->toContain( '<feFuncA type="table" tableValues="1 0.5 0.502"/>' );
	} );

	it( 'is stable for the same colors and distinct for different ones', function (): void {
		$a = DuotoneSupport::compile( duotoneAttributes( [ '#000', '#fff' ] ) )['class'];
		$b = DuotoneSupport::compile( duotoneAttributes( [ '#000', '#fff' ] ) )['class'];
		$c = DuotoneSupport::compile( duotoneAttributes( [ '#111', '#fff' ] ) )['class'];

		expect( $a )->toBe( $b )->not->toBe( $c );
	} );

	it( 'resolves a theme duotone preset by slug', function ( string $reference, mixed $presets ): void {
		$this->mock( ThemeManager::class, function ( $mock ) use ( $presets ): void {
			$mock->shouldReceive( 'getActiveTheme' )->andReturn( [
				'slug'     => 'test-theme',
				'settings' => [ 'color' => [ 'duotone' => $presets ] ],
			] );
		} );

		$compiled = DuotoneSupport::compile( duotoneAttributes( $reference ) );

		expect( $compiled['class'] )->toBe( 'wp-duotone-midnight' )
			->and( $compiled['filter'] )->toContain( '<feFuncR type="table" tableValues="0 1"/>' );
	} )->with( [
		'shorthand, flat list'        => [ 'var:preset|duotone|midnight', [ [ 'slug' => 'midnight', 'colors' => [ '#000', '#f00' ] ] ] ],
		'css var, origin-keyed lists' => [ 'var(--wp--preset--duotone--midnight)', [ 'default' => [], 'theme' => [ [ 'slug' => 'midnight', 'colors' => [ '#000', '#f00' ] ] ] ] ],
	] );

	it( 'parses CSS named colors in any case (F-8)', function (): void {
		$compiled = DuotoneSupport::compile( duotoneAttributes( [ 'Black', 'rebeccapurple', 'transparent' ] ) );

		expect( $compiled['filter'] )
			->toContain( '<feFuncR type="table" tableValues="0 0.4 0"/>' )
			->toContain( '<feFuncG type="table" tableValues="0 0.2 0"/>' )
			->toContain( '<feFuncB type="table" tableValues="0 0.6 0"/>' )
			->toContain( '<feFuncA type="table" tableValues="1 1 0"/>' );
	} );

	it( 'parses hsl() and hsla() in comma and space syntax (F-8)', function (): void {
		$compiled = DuotoneSupport::compile( duotoneAttributes( [
			'hsl(0, 100%, 50%)',
			'hsla(240, 100%, 50%, .5)',
			'hsl(0.5turn 100% 50% / 25%)',
			'HSL(30deg 50% 60%)',
			'hsl(-120, 100%, 50%)',
			'hsl(200grad 100% 50%)',
		] ) );

		expect( $compiled['filter'] )
			->toContain( '<feFuncR type="table" tableValues="1 0 0 0.8 0 0"/>' )
			->toContain( '<feFuncG type="table" tableValues="0 0 1 0.6 0 1"/>' )
			->toContain( '<feFuncB type="table" tableValues="0 1 1 0.4 1 1"/>' )
			->toContain( '<feFuncA type="table" tableValues="1 0.5 0.25 1 1 1"/>' );
	} );

	it( 'scopes every comma-separated target to the wrapper (F-2)', function (): void {
		$compiled = DuotoneSupport::compile( duotoneAttributes( [ '#000', '#fff' ] ), DuotoneSupport::TARGETS['core/cover'] );

		expect( $compiled['rules'] )->toBe( sprintf(
			'.%1$s > .wp-block-cover__image-background,.%1$s > .wp-block-cover__video-background{filter:url(#%1$s) !important}',
			$compiled['class'],
		) );
	} );

	it( 'turns the filter off for unset', function (): void {
		expect( DuotoneSupport::compile( duotoneAttributes( 'unset' ) ) )->toBe( [
			'class'    => 'wp-duotone-unset',
			'rules'    => '.wp-duotone-unset img{filter:none !important}',
			'filterId' => '',
			'filter'   => '',
		] );
	} );

	it( 'targets a custom media selector', function (): void {
		expect( DuotoneSupport::compile( duotoneAttributes( 'unset' ), '.wp-block-cover__image-background' )['rules'] )
			->toBe( '.wp-duotone-unset .wp-block-cover__image-background{filter:none !important}' );
	} );

	it( 'emits nothing for an unknown preset, a single stop, or a hostile color', function ( mixed $duotone ): void {
		expect( DuotoneSupport::compile( duotoneAttributes( $duotone ) )['class'] )->toBe( '' );
	} )->with( [
		'unknown preset'  => 'var:preset|duotone|does-not-exist',
		'single stop'     => [ [ '#000' ] ],
		'hostile stop'    => [ [ '#000', '"/><script>alert(1)</script>' ] ],
		'unknown name'    => [ [ 'black', 'notacolor' ] ],
		'currentcolor'    => [ [ 'currentColor', 'white' ] ],
		'hsl no percent'  => [ [ 'hsl(0 100 50)', '#fff' ] ],
		'hsl injection'   => [ [ 'hsl(0, 100%, 50%) url(x)', '#fff' ] ],
		'not a duotone'   => 'linear-gradient(red, blue)',
		'missing'         => null,
	] );
} );

describe( 'DuotoneSupport::apply', function (): void {
	it( 'pushes the rule and filter into the accumulator once per duotone', function (): void {
		$accumulator = app( DuotoneCssAccumulator::class );
		$accumulator->reset();

		$first  = DuotoneSupport::apply( duotoneAttributes( [ '#000', '#fff' ] ) );
		$second = DuotoneSupport::apply( duotoneAttributes( [ '#000', '#fff' ] ) );

		expect( $first )->toBe( $second );

		$output = $accumulator->flush();

		expect( substr_count( $output, '<filter id=' ) )->toBe( 1 )
			->and( substr_count( $output, 'filter:url(#' ) )->toBe( 1 )
			->and( $output )->toStartWith( '<style data-ve-duotone>' )
			->toContain( '<svg data-ve-duotone' )
			->and( $accumulator->flush() )->toBe( '' );
	} );

	it( 'returns an empty class and pushes nothing without a duotone', function (): void {
		$accumulator = app( DuotoneCssAccumulator::class );
		$accumulator->reset();

		expect( DuotoneSupport::apply( [] ) )->toBe( '' )
			->and( $accumulator->flush() )->toBe( '' );
	} );
} );

describe( 'DuotoneCssAccumulator', function (): void {
	it( 'emits each filter id once per request across flushes (R-10)', function (): void {
		$accumulator = app( DuotoneCssAccumulator::class );
		$accumulator->reset();

		DuotoneSupport::apply( duotoneAttributes( [ '#000', '#fff' ] ) );
		$first = $accumulator->flush();

		DuotoneSupport::apply( duotoneAttributes( [ '#000', '#fff' ] ) );
		DuotoneSupport::apply( duotoneAttributes( [ '#111', '#fff' ] ) );
		$second = $accumulator->flush();

		expect( substr_count( $first . $second, '<filter id=' ) )->toBe( 2 )
			->and( substr_count( $second, '<filter id=' ) )->toBe( 1 )
			->and( substr_count( $second, 'filter:url(#' ) )->toBe( 2 );

		$accumulator->reset();
		DuotoneSupport::apply( duotoneAttributes( [ '#000', '#fff' ] ) );

		expect( substr_count( $accumulator->flush(), '<filter id=' ) )->toBe( 1 );
	} );

	it( 'resolves the duotone presets once per request (M-16)', function (): void {
		// Keep the global-styles emission (which loads the theme too) out
		// of the count: only duotone's preset lookups are measured.
		app()->instance( GlobalStylesEmissionResolver::class, new class extends GlobalStylesEmissionResolver {
			public function emit(): string
			{
				return '';
			}
		} );

		$this->mock( ThemeManager::class, function ( $mock ): void {
			$mock->shouldReceive( 'getActiveTheme' )->once()->andReturn( [
				'slug'     => 'test-theme',
				'settings' => [ 'color' => [ 'duotone' => [ [ 'slug' => 'midnight', 'colors' => [ '#000', '#f00' ] ] ] ] ],
			] );
		} );

		$image = static fn ( string $id ): array => [
			'clientId'    => $id,
			'name'        => 'core/image',
			'attributes'  => [ 'url' => '/' . $id . '.jpg', 'style' => [ 'color' => [ 'duotone' => 'var:preset|duotone|midnight' ] ] ],
			'innerBlocks' => [],
		];

		$html = Blade::render( '<x-ve-blocks :tree="$tree" />', [
			'tree' => array_map( $image, [ 'a', 'b', 'c', 'd', 'e' ] ),
		] );

		expect( substr_count( $html, 'class="wp-block-image wp-duotone-midnight"' ) )->toBe( 5 )
			->and( substr_count( $html, '<filter id="wp-duotone-midnight">' ) )->toBe( 1 );
	} );
} );
