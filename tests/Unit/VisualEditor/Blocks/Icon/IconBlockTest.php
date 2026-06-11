<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Icon\IconBlock;
use ArtisanPackUI\VisualEditor\Services\Icon\IconSvgResolver;
use ArtisanPackUI\VisualEditor\Services\Icon\SvgSanitizer;

beforeEach( function (): void {
	// Most tests want iconRef→inline SVG to "just work" without standing up
	// the FA Free directory on disk. A fixture path with a single stub SVG
	// is enough to exercise the resolver path.
	test()->iconBase = sys_get_temp_dir() . '/icon-block-' . bin2hex( random_bytes( 4 ) );
	mkdir( test()->iconBase . '/fab', 0o755, true );
	file_put_contents(
		test()->iconBase . '/fab/github.svg',
		'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0"/></svg>',
	);

	$resolver    = new IconSvgResolver( [ 'fab' => test()->iconBase . '/fab' ] );
	test()->block = new IconBlock( new SvgSanitizer(), $resolver );
} );

afterEach( function (): void {
	$base = test()->iconBase ?? null;
	if ( is_string( $base ) && is_dir( $base ) ) {
		foreach ( new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST,
		) as $path ) {
			$path->isDir() ? rmdir( $path->getRealPath() ) : unlink( $path->getRealPath() );
		}
		rmdir( $base );
	}
} );

it( 'reports the artisanpack/icon block name', function () {
	expect( test()->block->name() )->toBe( 'artisanpack/icon' );
} );

it( 'renders a placeholder span when no iconRef or customSvg is set', function () {
	$html = test()->block->render( [] );

	expect( $html )->toContain( 'wp-block-artisanpack-icon__placeholder' )
		->and( $html )->toContain( 'aria-hidden="true"' )
		->and( $html )->toContain( 'wp-block-artisanpack-icon' );
} );

it( 'inlines the resolved SVG when iconRef matches a registered set', function () {
	$html = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
	] );

	expect( $html )->toContain( 'wp-block-artisanpack-icon__ref' )
		->and( $html )->toContain( 'data-icon-set="fab"' )
		->and( $html )->toContain( '<svg' )
		->and( $html )->toContain( 'width="100%"' )
		->and( $html )->toContain( 'height="100%"' )
		->and( $html )->toContain( 'viewBox="0 0 24 24"' )
		->and( $html )->toContain( '<path' );
} );

it( 'falls back to a placeholder when the iconRef cannot be resolved', function () {
	$html = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'does-not-exist' ],
	] );

	expect( $html )->toContain( 'wp-block-artisanpack-icon__placeholder' )
		->and( $html )->not->toContain( '<svg' );
} );

it( 'strips width/height regardless of value quoting style', function () {
	$base = test()->iconBase;
	file_put_contents(
		$base . '/fab/mixed-quotes.svg',
		// One double-quoted, one single-quoted, plus an unquoted variant in
		// the wild — admin-uploaded SVGs are not guaranteed to be XML-strict.
		"<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height='24' preserveAspectRatio=xMidYMid viewBox=\"0 0 24 24\"><path d=\"M0 0\"/></svg>",
	);

	$resolver = new IconSvgResolver( [ 'fab' => $base . '/fab' ] );
	$block    = new IconBlock( new SvgSanitizer(), $resolver );

	$html = $block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'mixed-quotes' ],
	] );

	expect( substr_count( $html, 'width=' ) )->toBe( 1 )
		->and( substr_count( $html, 'height=' ) )->toBe( 1 )
		->and( $html )->toContain( 'width="100%"' )
		->and( $html )->toContain( 'height="100%"' )
		->and( $html )->not->toContain( "width=\"24\"" )
		->and( $html )->not->toContain( "height='24'" );
} );

it( 'strips existing width/height from the SVG root before injecting wrapper-fill values', function () {
	$base = test()->iconBase;
	file_put_contents(
		$base . '/fab/sized.svg',
		'<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M0 0"/></svg>',
	);

	$resolver = new IconSvgResolver( [ 'fab' => $base . '/fab' ] );
	$block    = new IconBlock( new SvgSanitizer(), $resolver );

	$html = $block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'sized' ],
	] );

	// Exactly one width and one height — the original 24×24 pair must not
	// survive alongside the injected 100%/100% wrapper-fill pair.
	expect( substr_count( $html, 'width=' ) )->toBe( 1 )
		->and( substr_count( $html, 'height=' ) )->toBe( 1 )
		->and( $html )->toContain( 'width="100%"' )
		->and( $html )->toContain( 'height="100%"' )
		->and( $html )->not->toContain( 'width="24"' )
		->and( $html )->not->toContain( 'height="24"' );
} );

it( 'still falls back to a placeholder when no resolver is wired', function () {
	$block = new IconBlock( new SvgSanitizer() );

	$html = $block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
	] );

	expect( $html )->toContain( 'wp-block-artisanpack-icon__placeholder' )
		->and( $html )->not->toContain( '<svg' );
} );

it( 'rejects iconRef sets with disallowed characters', function () {
	$html = test()->block->render( [
		'iconRef' => [ 'set' => '../etc', 'name' => 'github' ],
	] );

	expect( $html )->toContain( 'wp-block-artisanpack-icon__placeholder' )
		->and( $html )->not->toContain( '../etc' );
} );

it( 'inlines a sanitized customSvg', function () {
	$html = test()->block->render( [
		'customSvg' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><path d="M0 0"/></svg>',
	] );

	expect( $html )->toContain( '<path' )
		->and( $html )->not->toContain( '<script' )
		->and( $html )->not->toContain( 'alert(1)' );
} );

it( 'wraps the icon in an anchor when link is set', function () {
	$html = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
		'link'    => 'https://example.com',
	] );

	expect( $html )->toContain( '<a href="https://example.com"' );
} );

it( 'forces noopener noreferrer when target is _blank', function () {
	$html = test()->block->render( [
		'iconRef'    => [ 'set' => 'fab', 'name' => 'github' ],
		'link'       => 'https://example.com',
		'linkTarget' => '_blank',
		'linkRel'    => 'nofollow',
	] );

	expect( $html )->toContain( 'target="_blank"' )
		->and( $html )->toContain( 'rel=' )
		->and( $html )->toContain( 'noopener' )
		->and( $html )->toContain( 'noreferrer' )
		->and( $html )->toContain( 'nofollow' );
} );

it( 'drops link wrapping when no icon is selected', function () {
	$html = test()->block->render( [
		'link' => 'https://example.com',
	] );

	expect( $html )->not->toContain( '<a ' );
} );

it( 'emits aria-hidden when decorative', function () {
	$html = test()->block->render( [
		'iconRef'      => [ 'set' => 'fab', 'name' => 'github' ],
		'isDecorative' => true,
		'ariaLabel'    => 'GitHub Profile',
	] );

	// Decorative wins — aria-label is suppressed by aria-hidden.
	expect( $html )->toContain( 'aria-hidden="true"' )
		->and( $html )->not->toContain( 'aria-label="GitHub Profile"' );
} );

it( 'emits aria-label for accessible non-decorative icons', function () {
	$html = test()->block->render( [
		'iconRef'   => [ 'set' => 'fab', 'name' => 'github' ],
		'ariaLabel' => 'GitHub Profile',
	] );

	expect( $html )->toContain( 'aria-label="GitHub Profile"' );
} );

it( 'composes a transform from rotation + flip', function () {
	$html = test()->block->render( [
		'iconRef'  => [ 'set' => 'fab', 'name' => 'github' ],
		'rotation' => 90,
		'flipH'    => true,
	] );

	expect( $html )->toContain( 'rotate(90deg)' )
		->and( $html )->toContain( 'scaleX(-1)' );
} );

it( 'rejects out-of-range rotations', function () {
	$normalized = test()->block->validateAttrs( [ 'rotation' => 45 ] );

	expect( $normalized['rotation'] )->toBe( 0 );
} );

it( 'rejects invalid size units', function () {
	$normalized = test()->block->validateAttrs( [ 'sizeUnit' => 'pt' ] );

	expect( $normalized['sizeUnit'] )->toBe( 'px' );
} );

it( 'clamps size to a reasonable range', function () {
	expect( test()->block->validateAttrs( [ 'size' => 0 ] )['size'] )->toBe( 1.0 )
		->and( test()->block->validateAttrs( [ 'size' => 99999 ] )['size'] )->toBe( 1024.0 );
} );

it( 'rejects invalid link targets', function () {
	$normalized = test()->block->validateAttrs( [ 'linkTarget' => '_evil' ] );

	expect( $normalized['linkTarget'] )->toBe( '' );
} );

it( 'rejects malformed colors', function () {
	$normalized = test()->block->validateAttrs( [ 'color' => 'javascript:alert(1)' ] );

	expect( $normalized['color'] )->toBeNull();
} );

it( 'accepts hex, rgb, hsl, var, and named colors', function () {
	expect( test()->block->validateAttrs( [ 'color' => '#fff' ] )['color'] )->toBe( '#fff' )
		->and( test()->block->validateAttrs( [ 'color' => 'rgb(0,0,0)' ] )['color'] )->toBe( 'rgb(0,0,0)' )
		->and( test()->block->validateAttrs( [ 'color' => 'hsl(0,100%,50%)' ] )['color'] )->toBe( 'hsl(0,100%,50%)' )
		->and( test()->block->validateAttrs( [ 'color' => 'var(--brand-primary)' ] )['color'] )->toBe( 'var(--brand-primary)' )
		->and( test()->block->validateAttrs( [ 'color' => 'red' ] )['color'] )->toBe( 'red' );
} );

it( 'rejects javascript: links', function () {
	$html = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
		'link'    => 'javascript:alert(1)',
	] );

	expect( $html )->not->toContain( '<a ' )
		->and( $html )->not->toContain( 'javascript:' );
} );

it( 'rejects data: links', function () {
	$html = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
		'link'    => 'data:text/html,<script>alert(1)</script>',
	] );

	expect( $html )->not->toContain( '<a ' )
		->and( $html )->not->toContain( 'data:' );
} );

it( 'allows relative links', function () {
	$html = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
		'link'    => '/about',
	] );

	expect( $html )->toContain( '<a href="/about"' );
} );

it( 'allows mailto and tel links', function () {
	$mail = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
		'link'    => 'mailto:hello@example.com',
	] );
	$tel  = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
		'link'    => 'tel:+15551234567',
	] );

	expect( $mail )->toContain( 'href="mailto:hello@example.com"' )
		->and( $tel )->toContain( 'href="tel:+15551234567"' );
} );

it( 'exposes ariaLabel + title via searchableText', function () {
	$text = test()->block->searchableText( [
		'ariaLabel' => 'GitHub Profile',
		'titleAttr' => 'Visit on GitHub',
	] );

	expect( $text )->toContain( 'GitHub Profile' )
		->and( $text )->toContain( 'Visit on GitHub' );
} );

// --- WP `attributes.style` envelope (color/border/spacing) ---

it( 'applies text color from the WP style envelope', function () {
	$html = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
		'style'   => [ 'color' => [ 'text' => '#abc123' ] ],
	] );

	expect( $html )->toContain( 'color: #abc123' );
} );

it( 'applies background color from the WP style envelope', function () {
	$html = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
		'style'   => [ 'color' => [ 'background' => '#facade' ] ],
	] );

	expect( $html )->toContain( 'background-color: #facade' );
} );

it( 'lets the WP style envelope override the legacy top-level color', function () {
	$html = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
		'color'   => '#111111',
		'style'   => [ 'color' => [ 'text' => '#222222' ] ],
	] );

	expect( $html )->toContain( 'color: #222222' )
		->and( $html )->not->toContain( 'color: #111111' );
} );

it( 'falls back to the legacy top-level color when WP style is empty', function () {
	$html = test()->block->render( [
		'iconRef'         => [ 'set' => 'fab', 'name' => 'github' ],
		'color'           => '#cafe00',
		'backgroundColor' => '#beef00',
	] );

	expect( $html )->toContain( 'color: #cafe00' )
		->and( $html )->toContain( 'background-color: #beef00' );
} );

it( 'applies uniform border declarations to the body span', function () {
	$html = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
		'style'   => [
			'border' => [
				'color'  => '#000000',
				'style'  => 'solid',
				'width'  => '2px',
				'radius' => '6px',
			],
		],
	] );

	expect( $html )->toContain( 'border-color: #000000' )
		->and( $html )->toContain( 'border-style: solid' )
		->and( $html )->toContain( 'border-width: 2px' )
		->and( $html )->toContain( 'border-radius: 6px' );
} );

it( 'applies per-corner radius from a radius object', function () {
	$html = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
		'style'   => [
			'border' => [
				'radius' => [
					'topLeft'     => '1px',
					'topRight'    => '2px',
					'bottomRight' => '3px',
					'bottomLeft'  => '4px',
				],
			],
		],
	] );

	expect( $html )->toContain( 'border-top-left-radius: 1px' )
		->and( $html )->toContain( 'border-top-right-radius: 2px' )
		->and( $html )->toContain( 'border-bottom-right-radius: 3px' )
		->and( $html )->toContain( 'border-bottom-left-radius: 4px' );
} );

it( 'applies padding shorthand on the body span', function () {
	$html = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
		'style'   => [ 'spacing' => [ 'padding' => '8px' ] ],
	] );

	expect( $html )->toContain( 'padding: 8px' );
} );

it( 'applies per-side padding on the body span', function () {
	$html = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
		'style'   => [
			'spacing' => [
				'padding' => [
					'top'    => '1px',
					'right'  => '2px',
					'bottom' => '3px',
					'left'   => '4px',
				],
			],
		],
	] );

	expect( $html )->toContain( 'padding-top: 1px' )
		->and( $html )->toContain( 'padding-right: 2px' )
		->and( $html )->toContain( 'padding-bottom: 3px' )
		->and( $html )->toContain( 'padding-left: 4px' );
} );

it( 'applies margin to the wrapper, not the body span', function () {
	$html = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
		'style'   => [ 'spacing' => [ 'margin' => '12px' ] ],
	] );

	expect( $html )->toMatch( '/<div class="wp-block-artisanpack-icon" style="margin: 12px;">/' );
} );

it( 'omits the wrapper style attribute when no wrapper-level styles apply', function () {
	$html = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
	] );

	expect( $html )->toMatch( '/<div class="wp-block-artisanpack-icon">/' );
} );

it( 'drops style values that fail the safe-CSS allowlist', function () {
	$html = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
		'style'   => [
			'color'  => [ 'text' => 'expression(alert(1))' ],
			'border' => [ 'width' => '2px; background: url(evil)' ],
			'spacing'=> [ 'padding' => "8px;</style><script>alert('xss')</script>" ],
		],
	] );

	expect( $html )->not->toContain( 'expression(' )
		->and( $html )->not->toContain( 'url(evil)' )
		->and( $html )->not->toContain( '<script' )
		->and( $html )->not->toContain( '</style' );
} );

// --- width / height override ---

it( 'uses size for both width and height when no override is set', function () {
	$html = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
		'size'    => 48,
	] );

	expect( $html )->toContain( 'width: 48px' )
		->and( $html )->toContain( 'height: 48px' );
} );

it( 'overrides the width axis when a width attribute is set', function () {
	$html = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
		'size'    => 32,
		'width'   => 64,
	] );

	expect( $html )->toContain( 'width: 64px' )
		->and( $html )->toContain( 'height: 32px' );
} );

it( 'inherits sizeUnit for width when widthUnit is null', function () {
	$attrs = test()->block->validateAttrs( [
		'iconRef'  => [ 'set' => 'fab', 'name' => 'github' ],
		'size'     => 32,
		'sizeUnit' => 'rem',
		'width'    => 4,
	] );

	expect( $attrs['width'] )->toBe( 4.0 )
		->and( $attrs['widthUnit'] )->toBe( 'rem' );
} );

it( 'accepts new sizeUnit values (%, vw, vh)', function () {
	$normalized = test()->block->validateAttrs( [ 'sizeUnit' => 'vh' ] );
	expect( $normalized['sizeUnit'] )->toBe( 'vh' );

	$normalized = test()->block->validateAttrs( [ 'sizeUnit' => '%' ] );
	expect( $normalized['sizeUnit'] )->toBe( '%' );
} );

it( 'clamps explicit width and height to the 1..1024 range', function () {
	$normalized = test()->block->validateAttrs( [ 'width' => -10, 'height' => 99999 ] );
	expect( $normalized['width'] )->toBe( 1.0 )
		->and( $normalized['height'] )->toBe( 1024.0 );
} );

it( 'normalizes missing width/height back to null', function () {
	$normalized = test()->block->validateAttrs( [] );
	expect( $normalized['width'] )->toBeNull()
		->and( $normalized['height'] )->toBeNull();
} );

// --- iconColor + palette-class wiring ---

it( 'applies the explicit iconColor to the body span', function () {
	$html = test()->block->render( [
		'iconRef'   => [ 'set' => 'fab', 'name' => 'github' ],
		'iconColor' => '#abc123',
	] );

	expect( $html )->toContain( 'color: #abc123' );
} );

it( 'lets iconColor override the WP style.color.text fallback', function () {
	$html = test()->block->render( [
		'iconRef'   => [ 'set' => 'fab', 'name' => 'github' ],
		'iconColor' => '#aaaaaa',
		'style'     => [ 'color' => [ 'text' => '#bbbbbb' ] ],
	] );

	expect( $html )->toContain( 'color: #aaaaaa' )
		->and( $html )->not->toContain( 'color: #bbbbbb' );
} );

it( 'falls back to style.color.text when iconColor is unset', function () {
	$html = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
		'style'   => [ 'color' => [ 'text' => '#cccccc' ] ],
	] );

	expect( $html )->toContain( 'color: #cccccc' );
} );

it( 'emits has-X-background-color + has-background for palette background slugs', function () {
	$html = test()->block->render( [
		'iconRef'         => [ 'set' => 'fab', 'name' => 'github' ],
		'backgroundColor' => 'primary',
	] );

	expect( $html )->toContain( 'has-primary-background-color' )
		->and( $html )->toContain( 'has-background' );
} );

it( 'emits has-X-border-color + has-border-color for palette border slugs', function () {
	$html = test()->block->render( [
		'iconRef'     => [ 'set' => 'fab', 'name' => 'github' ],
		'borderColor' => 'accent',
	] );

	expect( $html )->toContain( 'has-accent-border-color' )
		->and( $html )->toContain( 'has-border-color' );
} );

it( 'does NOT emit a has-X class when backgroundColor carries a hex value', function () {
	$html = test()->block->render( [
		'iconRef'         => [ 'set' => 'fab', 'name' => 'github' ],
		'backgroundColor' => '#abc123',
	] );

	// `#abc123` falls through to the inline background path instead of
	// producing a bogus `has-#abc123-background-color` class.
	expect( $html )->not->toContain( 'has-#abc123' )
		->and( $html )->toContain( 'background-color: #abc123' );
} );

it( 'puts WP-managed background/border/padding/margin on the wrapper', function () {
	$html = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
		'style'   => [
			'color'   => [ 'background' => '#facade' ],
			'border'  => [ 'color' => '#000000', 'width' => '2px' ],
			'spacing' => [
				'padding' => '8px',
				'margin'  => '12px',
			],
		],
	] );

	// Wrapper carries background/border/padding/margin; the body span
	// no longer does. Pulling the wrapper's `style="…"` attribute out
	// and asserting on each declaration in isolation keeps the test
	// resilient to incidental ordering / spacing changes inside the
	// inline style string.
	expect( $html )->toContain( 'wp-block-artisanpack-icon' )
		->and( $html )->toContain( 'has-background' )
		->and( $html )->toContain( 'has-border-color' );

	preg_match( '/<div\b[^>]*\bstyle="([^"]*)"/', $html, $wrapperMatch );
	$wrapperStyle = $wrapperMatch[1] ?? '';

	expect( $wrapperStyle )->toContain( 'background-color: #facade' )
		->and( $wrapperStyle )->toContain( 'border-color: #000000' )
		->and( $wrapperStyle )->toContain( 'border-width: 2px' )
		->and( $wrapperStyle )->toContain( 'padding: 8px' )
		->and( $wrapperStyle )->toContain( 'margin: 12px' );

	// And the body span's style does NOT pick up any of those — a
	// future regression that moves them back onto the body span gets
	// caught here.
	preg_match( '/<span\b[^>]*class="wp-block-artisanpack-icon__ref"[^>]*\bstyle="([^"]*)"/', $html, $bodyMatch );
	$bodyStyle = $bodyMatch[1] ?? '';

	expect( $bodyStyle )->not->toContain( 'background-color' )
		->and( $bodyStyle )->not->toContain( 'border-color' )
		->and( $bodyStyle )->not->toContain( 'border-width' )
		->and( $bodyStyle )->not->toContain( 'padding' )
		->and( $bodyStyle )->not->toContain( 'margin' );
} );
