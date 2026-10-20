<?php

/**
 * Unit tests for the block-support value validation (#920 review): a
 * stored colour, spacing, border, typography or dimensions value that
 * could append declarations to the inline style is dropped, legitimate
 * values survive, and the cover keeps every background longhand with
 * the image on its overlay span.
 *
 * @since 1.15.3
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

describe( 'BlockSupports::compile (unsafe values)', function (): void {
	it( 'drops colour, spacing, border, typography and dimensions values that could add declarations', function (): void {
		$result = BlockSupports::compile( [
			'style' => [
				'color'      => [
					'background' => 'red;position:fixed',
					'text'       => 'red}body{color:red',
					'gradient'   => 'linear-gradient(red, blue);top:0',
				],
				'spacing'    => [
					'padding' => '1rem; position:fixed; inset:0',
					'margin'  => [ 'top' => '1rem/* x', 'bottom' => '2rem' ],
				],
				'border'     => [
					'radius' => [ 'topLeft' => '4px{', 'bottomRight' => '12px' ],
					'width'  => '2px;color:red',
					'color'  => 'blue',
					'top'    => [ 'width' => '3px;', 'style' => 'dashed' ],
				],
				'typography' => [
					'fontSize'      => '1rem;color:red',
					'fontFamily'    => '"Inter";color:red',
					'lineHeight'    => '1.5',
					'letterSpacing' => 'url(javascript:alert(1))',
				],
				'dimensions' => [ 'aspectRatio' => '16/9;position:fixed', 'minHeight' => '200px' ],
			],
		] );

		expect( $result['classes'] )->toBe( [] );
		expect( $result['style'] )->toBe(
			'margin-bottom: 2rem; border-bottom-right-radius: 12px; border-color: blue; border-top-style: dashed; line-height: 1.5; min-height: 200px;',
		);
	} );

	it( 'keeps legitimate preset, calc, clamp, shorthand and quoted font values', function (): void {
		$result = BlockSupports::compile( [
			'style' => [
				'color'      => [
					'background' => 'rgb(0 0 0 / 50%)',
					'text'       => 'var:preset|color|contrast',
					'gradient'   => 'var:preset|gradient|vivid-cyan',
				],
				'spacing'    => [ 'padding' => 'calc(1rem + 2vw) clamp(1rem, 2vw, 3rem)', 'margin' => '0 auto' ],
				'border'     => [ 'radius' => '8px 4px', 'width' => '1px 2px' ],
				'typography' => [ 'fontFamily' => '"Times New Roman", \'Open Sans\', serif', 'fontSize' => 'clamp(1rem, 2.5vw, 2rem)' ],
				'dimensions' => [ 'aspectRatio' => '16/9' ],
			],
		] );

		expect( $result['classes'] )->toBe( [ 'has-background', 'has-text-color', 'has-aspect-ratio' ] );
		expect( $result['style'] )->toBe(
			'background-color: rgb(0 0 0 / 50%); background: var(--wp--preset--gradient--vivid-cyan); color: var(--wp--preset--color--contrast); '
			. 'padding: calc(1rem + 2vw) clamp(1rem, 2vw, 3rem); margin: 0 auto; border-radius: 8px 4px; border-width: 1px 2px; border-style: solid; '
			. 'font-size: clamp(1rem, 2.5vw, 2rem); font-family: "Times New Roman", \'Open Sans\', serif; aspect-ratio: 16/9; min-height: unset;',
		);
	} );

	it( 'drops a rejected root border width without forcing a solid style', function (): void {
		expect( BlockSupports::compile( [ 'style' => [ 'border' => [ 'width' => '2px;color:red' ] ] ] )['style'] )->toBe( '' );
	} );

	it( 'drops a rejected border from the avatar image attributes', function (): void {
		expect( BlockSupports::borderAttrs( [ 'style' => [ 'border' => [ 'radius' => '50%;position:fixed', 'width' => '2px' ] ] ] ) )->toBe( [
			'classes' => [],
			'style'   => 'border-width: 2px; border-style: solid;',
		] );
	} );
} );

describe( 'BlockSupports::safeGradientValue', function (): void {
	it( 'keeps a legitimate gradient', function ( string $value ): void {
		expect( BlockSupports::safeGradientValue( $value ) )->toBe( $value );
	} )->with( [
		'linear-gradient(135deg, #000 0%, #fff 100%)',
		'radial-gradient(circle at 50% 50%, rgba(6, 147, 227, 1) 0%, rgb(155, 81, 224) 100%)',
		'repeating-conic-gradient(from 0deg, color-mix(in srgb, red 50%, blue) 0 10%, var(--x) 10% 20%)',
		'Linear-Gradient(to right, hsl(0 0% 0%), calc(50% + 1px))',
		'var(--wp--preset--gradient--vivid-cyan)',
	] );

	it( 'rejects an unsafe or non-gradient value', function ( string $value ): void {
		expect( BlockSupports::safeGradientValue( $value ) )->toBeNull();
	} )->with( [
		'linear-gradient(red, blue);position:fixed',
		'linear-gradient(red, blue)}body{color:red',
		'linear-gradient(red, blue), url(evil.png)',
		'linear-gradient(red, url(evil.png))',
		'url(javascript:alert(1))',
		'url(evil.png)',
		'image-set(evil.png 1x)',
		'linear-gradient(red, blue',
		'linear-gradient(red, blue))',
		'linear-gradient(red /* x */, blue)',
		'red',
		'var(--x) url(a)',
		'',
	] );
} );

describe( 'BlockSupports::safeFontFamilyValue', function (): void {
	it( 'keeps a legitimate font stack', function ( string $value ): void {
		expect( BlockSupports::safeFontFamilyValue( $value ) )->toBe( $value );
	} )->with( [
		'Georgia, serif',
		'"Times New Roman", Times, serif',
		"'Open Sans', sans-serif",
		'var(--wp--preset--font-family--body)',
	] );

	it( 'rejects an unsafe font stack', function ( string $value ): void {
		expect( BlockSupports::safeFontFamilyValue( $value ) )->toBeNull();
	} )->with( [
		'"Inter";color:red',
		'"Open Sans, serif',
		'"a;b", serif',
		'Inter}body{color:red',
		'"x" /* y',
		'',
	] );
} );

describe( 'BlockSupports::compileCoverOverlay (unsafe values)', function (): void {
	it( 'drops an unsafe custom overlay colour or gradient', function (): void {
		expect( BlockSupports::compileCoverOverlay( [
			'customOverlayColor' => '#000;position:fixed',
			'customGradient'     => 'linear-gradient(red, blue), url(evil.png)',
		] ) )->toBe( [ 'classes' => [], 'style' => '' ] );
	} );
} );

describe( 'BlockSupports::splitBackgroundOutput (background longhands)', function (): void {
	it( 'keeps every background longhand with the image', function (): void {
		$compiled = BlockSupports::compile( [
			'style' => [
				'background' => [
					'backgroundImage'      => [ 'url' => 'https://example.com/a.jpg' ],
					'backgroundSize'       => 'contain',
					'backgroundRepeat'     => 'no-repeat',
					'backgroundAttachment' => 'fixed',
				],
				'dimensions' => [ 'minHeight' => '10rem' ],
			],
		] );

		$split = BlockSupports::splitBackgroundOutput( $compiled['classes'], $compiled['style'] );

		expect( $split['background']['style'] )->toBe(
			"background-image: url('https://example.com/a.jpg'); background-size: contain; background-position: 50% 50%; background-repeat: no-repeat; background-attachment: fixed;",
		);
		expect( $split['wrapper']['style'] )->toBe( 'min-height: 10rem;' );
	} );
} );
