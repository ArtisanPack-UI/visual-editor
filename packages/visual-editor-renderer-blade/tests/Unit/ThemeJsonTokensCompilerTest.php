<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Services\ThemeJsonTokensCompiler;

beforeEach( function (): void {
	$this->compiler = new ThemeJsonTokensCompiler();
} );

it( 'compiles color, gradient, fontSize and spacing presets', function (): void {
	$css = $this->compiler->compile( [
		'settings' => [
			'color' => [
				'palette' => [
					[ 'slug' => 'primary', 'color' => '#0f172a' ],
				],
				'gradient' => [
					[ 'slug' => 'sunset', 'gradient' => 'linear-gradient(45deg, #f00, #fa0)' ],
				],
			],
			'typography' => [
				'fontSizes' => [
					[ 'slug' => 'small', 'size' => '0.875rem' ],
				],
			],
			'spacing' => [
				'spacingSizes' => [
					[ 'slug' => 'sm', 'size' => '0.5rem' ],
				],
			],
		],
	] );

	expect( $css )
		->toContain( ':root {' )
		->toContain( '--wp--preset--color--primary: #0f172a;' )
		->toContain( '--wp--preset--gradient--sunset: linear-gradient(45deg, #f00, #fa0);' )
		->toContain( '--wp--preset--font-size--small: 0.875rem;' )
		->toContain( '--wp--preset--spacing--sm: 0.5rem;' );
} );

it( 'returns an empty string when no recognised tokens are present', function (): void {
	expect( $this->compiler->compile( [] ) )->toBe( '' );
	expect( $this->compiler->compile( [ 'settings' => [] ] ) )->toBe( '' );
	expect( $this->compiler->compile( [
		'settings' => [ 'color' => [ 'palette' => [] ] ],
	] ) )->toBe( '' );
} );

it( 'normalises slugs to lowercase hyphen-safe identifiers', function (): void {
	$css = $this->compiler->compile( [
		'settings' => [
			'color' => [
				'palette' => [
					[ 'slug' => 'Primary Brand!', 'color' => '#000' ],
				],
			],
		],
	] );

	expect( $css )->toContain( '--wp--preset--color--primary-brand-: #000;' );
} );

it( 'skips entries missing slug or value', function (): void {
	$css = $this->compiler->compile( [
		'settings' => [
			'color' => [
				'palette' => [
					[ 'slug' => 'good', 'color' => '#abc' ],
					[ 'slug' => '', 'color' => '#def' ],
					[ 'color' => '#fff' ],
					[ 'slug' => 'no-value' ],
				],
			],
		],
	] );

	expect( $css )
		->toContain( '--wp--preset--color--good: #abc;' )
		->not->toContain( '#def' )
		->not->toContain( '#fff' )
		->not->toContain( 'no-value' );
} );

it( 'ignores non-array entries gracefully', function (): void {
	$css = $this->compiler->compile( [
		'settings' => [
			'color' => [
				'palette' => [
					'this should not be here',
					[ 'slug' => 'ok', 'color' => '#123' ],
					null,
				],
			],
		],
	] );

	expect( $css )->toContain( '--wp--preset--color--ok: #123;' );
} );

describe( 'Keystone #50 — layout-size custom properties + alignment rules', function (): void {
	it( 'emits content-size + wide-size custom properties from settings.layout', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'settings' => [
				'layout' => [
					'contentSize' => '720px',
					'wideSize'    => '1200px',
				],
			],
		] );

		expect( $css )->toContain( '--wp--style--global--content-size: 720px;' );
		expect( $css )->toContain( '--wp--style--global--wide-size: 1200px;' );
	} );

	it( 'emits the alignwide layout rule referencing the wide-size custom property', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'settings' => [ 'layout' => [ 'wideSize' => '1200px' ] ],
		] );

		expect( $css )->toContain( '.wp-block-group.is-layout-constrained.alignwide' );
		expect( $css )->toContain( 'max-width: var(--wp--style--global--wide-size, 1080px);' );
		expect( $css )->toContain( 'margin-left: auto;' );
		expect( $css )->toContain( 'margin-right: auto;' );
	} );

	it( 'emits the alignfull layout rule whenever any layout is configured', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'settings' => [ 'layout' => [ 'contentSize' => '720px' ] ],
		] );

		expect( $css )->toContain( '.wp-block-group.is-layout-constrained.alignfull' );
		expect( $css )->toContain( 'max-width: none;' );
	} );

	it( 'omits layout rules entirely when no layout is configured', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'settings' => [ 'color' => [ 'palette' => [ [ 'slug' => 'primary', 'color' => '#000' ] ] ] ],
		] );

		expect( $css )->not->toContain( 'is-layout-constrained.alignwide' );
		expect( $css )->not->toContain( 'is-layout-constrained.alignfull' );
	} );

	it( 'emits .wp-block-post-content child rules so root-level default alignment uses content-size', function (): void {
		// Themes opt into WP-FSE-style page layout by adding
		// `class="wp-block-post-content is-layout-constrained"` to
		// their `<main>` wrapper. The renderer's CSS rules then
		// give the children the canonical "default = content-size,
		// wide = wide-size, full = no max" behavior. Without these
		// rules a section with `align="none"` stretches full-width
		// because nothing sizes its parent.
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'settings' => [
				'layout' => [ 'contentSize' => '720px', 'wideSize' => '1200px' ],
			],
		] );

		expect( $css )
			->toContain( '.wp-block-post-content.is-layout-constrained > :where(:not(.alignwide):not(.alignfull):not(.alignleft):not(.alignright))' )
			->toContain( '.wp-block-post-content.is-layout-constrained > .alignwide' )
			->toContain( '.wp-block-post-content.is-layout-constrained > .alignfull' );
	} );

	it( 'caps unaligned children of a constrained group at content-size (#700)', function (): void {
		// A constrained group has to constrain its children — that is
		// what the layout type means upstream. Keyed on the per-block
		// compound so host markup that hand-writes the shared
		// `is-layout-constrained` modifier is not retroactively
		// constrained.
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'settings' => [
				'layout' => [ 'contentSize' => '720px', 'wideSize' => '1200px' ],
			],
		] );

		expect( $css )
			->toContain( '.wp-block-group.wp-block-group-is-layout-constrained > :where(:not(.alignwide):not(.alignfull):not(.alignleft):not(.alignright))' )
			->toContain( '.wp-block-group.wp-block-group-is-layout-constrained > .alignwide' )
			->toContain( '.wp-block-group.wp-block-group-is-layout-constrained > .alignfull' );
	} );

	it( 'omits the constrained-group child cap when no contentSize is configured (#700)', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'settings' => [ 'layout' => [ 'wideSize' => '1200px' ] ],
		] );

		expect( $css )
			->not->toContain( '.wp-block-group.wp-block-group-is-layout-constrained > :where(' )
			// alignfull stays unconditional, matching the other rule sets.
			->toContain( '.wp-block-group.wp-block-group-is-layout-constrained > .alignfull' );
	} );

	it( 'composes root presets with layout rules separated by a blank line', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'settings' => [
				'color'  => [ 'palette' => [ [ 'slug' => 'primary', 'color' => '#000' ] ] ],
				'layout' => [ 'contentSize' => '720px', 'wideSize' => '1200px' ],
			],
		] );

		// `:root { … }` block first, layout rules second.
		expect( $css )->toMatch( '/:root \{[^}]*\}\n\n\.wp-block-group/' );
	} );
} );

describe( 'styles.* CSS rule emission', function (): void {
	it( 'compiles root-level color, typography, spacing, and border declarations onto the body', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'styles' => [
				'color'      => [
					'background' => 'var:preset|color|page',
					'text'       => '#111827',
				],
				'typography' => [
					'fontFamily' => 'var:preset|font-family|sans',
					'lineHeight' => '1.6',
				],
				'spacing'    => [
					'blockGap' => '1.5rem',
				],
				'border'     => [
					'radius' => '0.5rem',
				],
			],
		] );

		expect( $css )
			->toContain( 'body {' )
			->toContain( 'background-color: var(--wp--preset--color--page);' )
			->toContain( 'color: #111827;' )
			->toContain( 'font-family: var(--wp--preset--font-family--sans);' )
			->toContain( 'line-height: 1.6;' )
			->toContain( '--wp--style--block-gap: 1.5rem;' )
			->toContain( 'border-radius: 0.5rem;' );
	} );

	it( 'emits element rules under canonical selectors', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'styles' => [
				'elements' => [
					'link'    => [ 'color' => [ 'text' => 'var:preset|color|accent' ] ],
					'heading' => [ 'typography' => [ 'fontWeight' => '700' ] ],
					'h2'      => [ 'typography' => [ 'fontSize' => '2rem' ] ],
					'button'  => [
						'color'   => [ 'background' => 'var:preset|color|accent', 'text' => '#fff' ],
						'border'  => [ 'radius' => '0.5rem' ],
						'spacing' => [ 'padding' => [ 'top' => '0.75rem', 'right' => '1.5rem', 'bottom' => '0.75rem', 'left' => '1.5rem' ] ],
					],
				],
			],
		] );

		expect( $css )
			->toContain( ':root :where(a:where(:not(.wp-element-button))) {' )
			->toContain( 'color: var(--wp--preset--color--accent);' )
			->toContain( ':root :where(h1, h2, h3, h4, h5, h6) {' )
			->toContain( 'font-weight: 700;' )
			->toContain( ':root :where(h2) {' )
			->toContain( 'font-size: 2rem;' )
			->toContain( ':root :where(.wp-element-button, .wp-block-button__link) {' )
			->toContain( 'background-color: var(--wp--preset--color--accent);' )
			->toContain( 'padding-top: 0.75rem;' )
			->toContain( 'padding-right: 1.5rem;' );
	} );

	it( 'emits per-block rules under the .wp-block-<namespace>-<slug> selector', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'styles' => [
				'blocks' => [
					'artisanpack/form' => [
						'color'   => [ 'background' => 'var:preset|color|surface' ],
						'border'  => [ 'color' => 'var:preset|color|divider', 'width' => '1px', 'radius' => '0.75rem' ],
						'spacing' => [ 'padding' => 'var:preset|spacing|40' ],
					],
				],
			],
		] );

		expect( $css )
			->toContain( ':root :where(.wp-block-artisanpack-form) {' )
			->toContain( 'background-color: var(--wp--preset--color--surface);' )
			->toContain( 'border-color: var(--wp--preset--color--divider);' )
			->toContain( 'border-width: 1px;' )
			->toContain( 'border-radius: 0.75rem;' )
			->toContain( 'padding: var(--wp--preset--spacing--40);' );
	} );

	it( 'emits per-block --wp--style--block-gap overrides that cascade to flow children', function (): void {
		// Issue #539 — verify that a block-level spacing.blockGap is
		// emitted as a per-container custom-property override, so the
		// renderer-static `:where(.is-layout-flow) > * + *` rule
		// (emitted by `<x-ve-blocks-styles />`) picks it up via the
		// `var(--wp--style--block-gap, …)` reference and produces the
		// correct sibling spacing inside that block.
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'styles' => [
				'spacing' => [ 'blockGap' => '2em' ],
				'blocks'  => [
					'artisanpack/group' => [
						'spacing' => [ 'blockGap' => '3rem' ],
					],
				],
			],
		] );

		expect( $css )
			->toContain( 'body {' )
			->toContain( '--wp--style--block-gap: 2em;' )
			->toContain( ':root :where(.wp-block-artisanpack-group) {' )
			->toContain( '--wp--style--block-gap: 3rem;' );
	} );

	it( 'descends into styles.blocks[X].elements with a descendant selector', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'styles' => [
				'blocks' => [
					'artisanpack/form' => [
						'elements' => [
							'link' => [ 'color' => [ 'text' => '#ff0000' ] ],
						],
					],
				],
			],
		] );

		expect( $css )->toContain( ':root :where(.wp-block-artisanpack-form a:where(:not(.wp-element-button))) {' )
			->and( $css )->toContain( 'color: #ff0000;' );
	} );

	it( 'scopes every selector of a nested multi-selector element to the block', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'styles' => [
				'blocks' => [
					'core/group' => [
						'elements' => [
							'heading' => [ 'color' => [ 'text' => '#123456' ] ],
						],
					],
				],
			],
		] );

		expect( $css )->toContain( ':root :where(.wp-block-group h1, .wp-block-group h2, .wp-block-group h3, .wp-block-group h4, .wp-block-group h5, .wp-block-group h6) {' );
	} );

	it( 'targets core blocks by their rendered class, without the core namespace', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'styles' => [
				'blocks' => [
					'core/group'        => [ 'color' => [ 'background' => '#eeeeee' ] ],
					'artisanpack/form'  => [ 'color' => [ 'background' => '#dddddd' ] ],
				],
			],
		] );

		expect( $css )->toContain( ':root :where(.wp-block-group) {' )
			->and( $css )->toContain( ':root :where(.wp-block-artisanpack-form) {' )
			->and( $css )->not->toContain( 'wp-block-core-' );
	} );

	it( 'handles per-corner border-radius objects', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'styles' => [
				'blocks' => [
					'artisanpack/form' => [
						'border' => [
							'radius' => [
								'topLeft'     => '8px',
								'topRight'    => '8px',
								'bottomLeft'  => '0',
								'bottomRight' => '0',
							],
						],
					],
				],
			],
		] );

		expect( $css )
			->toContain( 'border-top-left-radius: 8px;' )
			->toContain( 'border-top-right-radius: 8px;' )
			->toContain( 'border-bottom-left-radius: 0;' )
			->toContain( 'border-bottom-right-radius: 0;' );
	} );

	it( 'skips unknown elements and unrecognised payload shapes silently', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'styles' => [
				'elements' => [
					'unknown-element' => [ 'color' => [ 'text' => '#f00' ] ],
				],
				'blocks'   => [
					'core/heading' => 'not an array',
				],
			],
		] );

		// Unknown element selector returns null → no rule emitted.
		expect( $css )->not->toContain( 'unknown-element' )
			->and( $css )->not->toContain( 'wp-block-core-heading' );
	} );

	it( 'composes settings presets, layout rules, and styles output in order', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'settings' => [
				'color'  => [ 'palette' => [ [ 'slug' => 'primary', 'color' => '#000' ] ] ],
				'layout' => [ 'contentSize' => '720px', 'wideSize' => '1200px' ],
			],
			'styles'   => [
				'color' => [ 'background' => 'var:preset|color|primary' ],
			],
		] );

		// Order: :root presets → layout rules → utility classes → styles rules.
		// Guard each strpos against false up front — without the guard a
		// missing section silently makes `false < positive-int` succeed
		// and the ordering check would pass for the wrong reason.
		$rootPos     = strpos( $css, ':root' );
		$layoutPos   = strpos( $css, '.wp-block-group' );
		$utilityPos  = strpos( $css, '.has-primary-color' );
		$bodyPos     = strpos( $css, 'body {' );

		expect( $rootPos )->not->toBeFalse()
			->and( $layoutPos )->not->toBeFalse()
			->and( $utilityPos )->not->toBeFalse()
			->and( $bodyPos )->not->toBeFalse();

		expect( $rootPos )->toBeLessThan( $layoutPos )
			->and( $layoutPos )->toBeLessThan( $utilityPos )
			->and( $utilityPos )->toBeLessThan( $bodyPos );
	} );

	it( 'emits has-{slug}-color / -background-color / -border-color utility classes for each palette entry', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'settings' => [
				'color' => [
					'palette' => [
						[ 'slug' => 'accent', 'color' => '#ff0000' ],
						[ 'slug' => 'muted',  'color' => '#888888' ],
					],
				],
			],
		] );

		expect( $css )->toContain( '.has-accent-color { color: var(--wp--preset--color--accent) !important; }' );
		expect( $css )->toContain( '.has-accent-background-color { background-color: var(--wp--preset--color--accent) !important; }' );
		expect( $css )->toContain( '.has-accent-border-color { border-color: var(--wp--preset--color--accent) !important; }' );
		expect( $css )->toContain( '.has-muted-background-color { background-color: var(--wp--preset--color--muted) !important; }' );
	} );

	it( 'scopes color utilities that collide with block-support markers to real picks of that slug', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'settings' => [
				'color' => [
					'palette' => [
						[ 'slug' => 'base', 'color' => '#111827' ],
						[ 'slug' => 'text', 'color' => '#ffffff' ],
						[ 'slug' => 'link', 'color' => '#00e5ff' ],
						[ 'slug' => 'border', 'color' => '#374151' ],
					],
				],
			],
		] );

		expect( $css )
			->toContain( '.has-base-color { color: var(--wp--preset--color--base) !important; }' )
			->toContain( ':where(.has-text-color:not([style^="color:"], [style*=" color:"], [style*=";color:"])) { color: var(--wp--preset--color--text) !important; }' )
			->toContain( ':where(.has-link-color.has-text-color:not([style^="color:"], [style*=" color:"], [style*=";color:"])) { color: var(--wp--preset--color--link) !important; }' )
			->toContain( ':where(.has-border-color.has-text-color:not([style^="color:"], [style*=" color:"], [style*=";color:"])) { color: var(--wp--preset--color--border) !important; }' )
			->toContain( '.has-text-color-preset { color: var(--wp--preset--color--text) !important; }' )
			->toContain( '.has-link-color-preset { color: var(--wp--preset--color--link) !important; }' )
			->toContain( '.has-text-background-color { background-color: var(--wp--preset--color--text) !important; }' )
			->toContain( '.has-border-border-color { border-color: var(--wp--preset--color--border) !important; }' )
			->not->toMatch( '/(^|[\s}])\.has-(text|link|border)-color \{/' );
	} );

	it( 'emits has-{slug}-gradient-background for each gradient preset', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'settings' => [
				'color' => [
					'gradient' => [
						[ 'slug' => 'sunset', 'gradient' => 'linear-gradient(45deg, red, orange)' ],
					],
				],
			],
		] );

		expect( $css )->toContain( '.has-sunset-gradient-background { background: var(--wp--preset--gradient--sunset) !important; }' );
	} );

	it( 'emits has-{slug}-font-size for each font-size preset', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'settings' => [
				'typography' => [
					'fontSizes' => [
						[ 'slug' => 'large', 'size' => '1.5rem' ],
					],
				],
			],
		] );

		expect( $css )->toContain( '.has-large-font-size { font-size: var(--wp--preset--font-size--large) !important; }' );
	} );

	it( 'skips palette entries missing a slug', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'settings' => [
				'color' => [
					'palette' => [
						[ 'color' => '#abc' ],
						[ 'slug' => 'real', 'color' => '#def' ],
					],
				],
			],
		] );

		expect( $css )->toContain( '.has-real-background-color' );
		expect( $css )->not->toContain( 'has--background-color' );
	} );
} );

describe( 'floated alignments (#819)', function (): void {
	it( 'floats alignleft / alignright children of constrained post content and groups', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'settings' => [ 'layout' => [ 'contentSize' => '640px' ] ],
		] );

		foreach ( [ '.wp-block-post-content.is-layout-constrained', '.wp-block-group.wp-block-group-is-layout-constrained' ] as $parent ) {
			expect( $css )
				->toContain( "{$parent} > .alignleft {\n\tfloat: left;" )
				->toContain( "{$parent} > .alignright {\n\tfloat: right;" );
		}

		expect( $css )
			->toContain( 'margin-inline-start: max(0px, calc((100% - var(--wp--style--global--content-size, 720px)) / 2));' )
			->toContain( 'margin-inline-end: max(0px, calc((100% - var(--wp--style--global--content-size, 720px)) / 2));' );
	} );

	it( 'contains floats in post content without touching group display', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'settings' => [ 'layout' => [ 'contentSize' => '640px' ] ],
		] );

		expect( $css )
			->toContain( ":where(.wp-block-post-content.is-layout-constrained) {\n\tdisplay: flow-root;" )
			->not->toMatch( '/\.wp-block-group-is-layout-constrained \{[^}]*flow-root/' );
	} );

	it( 'stops floating on narrow viewports', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'settings' => [ 'layout' => [ 'contentSize' => '640px' ] ],
		] );

		expect( $css )->toMatch( '/@media \(max-width: \d+px\) \{\n\t\.wp-block-post-content\.is-layout-constrained > \.alignleft,[^}]*float: none;/' );
	} );
} );

describe( 'compileDefaults (#821)', function (): void {
	beforeEach( function (): void {
		config()->set( 'artisanpack.visual-editor.presets', [] );
	} );

	it( 'emits font-size vars, utility classes, layout tokens and layout rules for an empty theme', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compileDefaults( [] );

		expect( $css )
			->toContain( '--wp--preset--font-size--huge: 36px;' )
			->toContain( '.has-huge-font-size' )
			->toContain( '--wp--style--block-gap: 24px;' )
			->toContain( '--wp--style--global--content-size: 720px;' )
			->toContain( '.wp-block-group.wp-block-group-is-layout-constrained > .alignleft' );
	} );

	it( 'skips the default layout rules when the theme sets both layout sizes', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compileDefaults( [
			'settings' => [ 'layout' => [ 'contentSize' => '640px', 'wideSize' => '1200px' ] ],
		] );

		expect( $css )->not->toContain( '.alignwide' );
	} );

	it( 'merges host font sizes over the defaults', function (): void {
		config()->set( 'artisanpack.visual-editor.presets.font_sizes', [ [ 'slug' => 'large', 'size' => '30px' ], [ 'slug' => 'jumbo', 'size' => '64px' ] ] );

		$css = ( new ThemeJsonTokensCompiler() )->compileDefaults( null );

		expect( $css )
			->toContain( '--wp--preset--font-size--large: 30px;' )
			->toContain( '--wp--preset--font-size--jumbo: 64px;' )
			->toContain( '--wp--preset--font-size--small: 13px;' );
	} );

	it( 'drops unsafe preset values', function (): void {
		config()->set( 'artisanpack.visual-editor.presets.font_sizes', [ [ 'slug' => 'bad', 'size' => '1px;}body{x:y' ] ] );

		expect( ( new ThemeJsonTokensCompiler() )->compileDefaults( null ) )->not->toContain( 'font-size--bad' );
	} );

	it( 'leaves no var() without a fallback or a declaration in the output', function (): void {
		$compiler = new ThemeJsonTokensCompiler();
		$css      = $compiler->compile( [ 'settings' => [ 'layout' => [ 'contentSize' => '640px' ] ] ] )
			. "\n" . $compiler->compileDefaults( [] );

		preg_match_all( '/(--[\w-]+)\s*:/', $css, $declared );
		preg_match_all( '/var\(\s*(--(?:wp|ap)-[\w-]+)\s*\)/', $css, $unguarded );

		expect( array_values( array_diff( $unguarded[1], $declared[1] ) ) )->toBe( [] );
	} );
} );

describe( 'styles.elements pseudo-states and selectors (#852)', function (): void {
	it( 'compiles link :hover / :focus / :focus-visible / :active sub-nodes after the base rule', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'styles' => [
				'elements' => [
					'link' => [
						'color'          => [ 'text' => 'var:preset|color|primary' ],
						':hover'         => [ 'color' => [ 'text' => 'var:preset|color|accent' ] ],
						':focus'         => [ 'color' => [ 'text' => '#111111' ] ],
						':focus-visible' => [ 'color' => [ 'text' => '#222222' ] ],
						':active'        => [ 'color' => [ 'text' => '#333333' ] ],
					],
				],
			],
		] );

		$link = 'a:where(:not(.wp-element-button))';
		$base = strpos( $css, ":root :where({$link}) {" );

		expect( $base )->not->toBeFalse()
			->and( $css )->toContain( ":root :where({$link}:hover) {\n\tcolor: var(--wp--preset--color--accent);" )
			->and( $css )->toContain( ":root :where({$link}:focus) {\n\tcolor: #111111;" )
			->and( $css )->toContain( ":root :where({$link}:focus-visible) {\n\tcolor: #222222;" )
			->and( $css )->toContain( ":root :where({$link}:active) {\n\tcolor: #333333;" )
			->and( strpos( $css, ':hover)' ) )->toBeGreaterThan( $base )
			->and( strpos( $css, ':focus)' ) )->toBeGreaterThan( strpos( $css, ':hover)' ) );
	} );

	it( 'appends the pseudo to every selector in the button element list', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'styles' => [
				'elements' => [
					'button' => [
						':hover' => [ 'color' => [ 'background' => '#000000', 'text' => '#ffffff' ] ],
						':focus' => [ 'color' => [ 'text' => '#ff0000' ] ],
					],
				],
			],
		] );

		expect( $css )
			->toContain( ":root :where(.wp-element-button:hover, .wp-block-button__link:hover) {\n\tbackground-color: #000000;\n\tcolor: #ffffff;" )
			->toContain( ':root :where(.wp-element-button:focus, .wp-block-button__link:focus) {' );
	} );

	it( 'compiles pseudo-states under styles.blocks[*].elements', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'styles' => [
				'blocks' => [
					'core/paragraph' => [
						'elements' => [
							'link' => [ ':hover' => [ 'color' => [ 'text' => '#abcdef' ] ] ],
						],
					],
				],
			],
		] );

		expect( $css )->toContain( ':root :where(.wp-block-paragraph a:where(:not(.wp-element-button)):hover) {' );
	} );

	it( 'never emits a bare `a` rule that would also match button links', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'styles' => [
				'elements' => [ 'link' => [ 'color' => [ 'text' => '#00ff00' ] ] ],
			],
		] );

		expect( $css )->not->toMatch( '/(^|[\s,(])a\s*\{/m' );
	} );

	it( 'skips pseudo-state sub-nodes that carry no recognised declarations', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'styles' => [
				'elements' => [ 'link' => [ ':hover' => [ 'unknown' => [ 'x' => 'y' ] ] ] ],
			],
		] );

		expect( $css )->not->toContain( ':hover' );
	} );
} );

describe( 'shadow, gradient and font-family presets (#872)', function (): void {
	beforeEach( function (): void {
		config()->set( 'artisanpack.visual-editor.presets', [] );
	} );

	it( 'declares --wp--preset--shadow--{slug} from settings.shadow.presets', function (): void {
		$css = $this->compiler->compile( [
			'settings' => [
				'shadow' => [
					'presets' => [
						[ 'slug' => 'natural', 'shadow' => '6px 6px 9px rgba(0, 0, 0, 0.2)' ],
						[ 'slug' => 'neon-glow', 'shadow' => '0 0 12px #0ff' ],
						[ 'slug' => 'missing-value' ],
					],
				],
			],
		] );

		expect( $css )
			->toContain( '--wp--preset--shadow--natural: 6px 6px 9px rgba(0, 0, 0, 0.2);' )
			->toContain( '--wp--preset--shadow--neon-glow: 0 0 12px #0ff;' )
			->not->toContain( 'missing-value' );
	} );

	it( 'reads gradients from settings.color.gradients', function (): void {
		$css = $this->compiler->compile( [
			'settings' => [
				'color' => [
					'gradients' => [
						[ 'slug' => 'sunrise', 'gradient' => 'linear-gradient(90deg, #f60, #fc0)' ],
					],
				],
			],
		] );

		expect( $css )
			->toContain( '--wp--preset--gradient--sunrise: linear-gradient(90deg, #f60, #fc0);' )
			->toContain( '.has-sunrise-gradient-background { background: var(--wp--preset--gradient--sunrise) !important; }' );
	} );

	it( 'prefers settings.color.gradients over the legacy gradient key', function (): void {
		$css = $this->compiler->compile( [
			'settings' => [
				'color' => [
					'gradients' => [ [ 'slug' => 'current', 'gradient' => 'linear-gradient(red, blue)' ] ],
					'gradient'  => [ [ 'slug' => 'legacy', 'gradient' => 'linear-gradient(green, blue)' ] ],
				],
			],
		] );

		expect( $css )
			->toContain( '--wp--preset--gradient--current:' )
			->not->toContain( 'legacy' );
	} );

	it( 'declares font-family presets and binds has-{slug}-font-family to them', function (): void {
		$css = $this->compiler->compile( [
			'settings' => [
				'typography' => [
					'fontFamilies' => [
						[ 'slug' => 'display', 'name' => 'Display', 'fontFamily' => '"Space Grotesk", sans-serif' ],
						[ 'name' => 'No slug', 'fontFamily' => 'serif' ],
					],
				],
			],
		] );

		expect( $css )
			->toContain( '--wp--preset--font-family--display: "Space Grotesk", sans-serif;' )
			->toContain( '.has-display-font-family { font-family: var(--wp--preset--font-family--display) !important; }' )
			->not->toContain( 'No slug' );
	} );

	it( 'declares the default font families and their utility classes', function (): void {
		$css = $this->compiler->compileDefaults( null );

		expect( $css )
			->toContain( '--wp--preset--font-family--serif: Georgia, Cambria, "Times New Roman", Times, serif;' )
			->toContain( '.has-mono-font-family { font-family: var(--wp--preset--font-family--mono) !important; }' );
	} );

	it( 'layers theme and host font families over the defaults', function (): void {
		config()->set( 'artisanpack.visual-editor.presets.font_families', [
			[ 'slug' => 'brand', 'fontFamily' => '"Brand Sans", sans-serif' ],
		] );

		$css = $this->compiler->compileDefaults( [
			'settings' => [
				'typography' => [
					'fontFamilies' => [ [ 'slug' => 'serif', 'fontFamily' => '"Lora", serif' ] ],
				],
			],
		] );

		expect( $css )
			->toContain( '--wp--preset--font-family--serif: "Lora", serif;' )
			->toContain( '--wp--preset--font-family--system:' )
			->toContain( '--wp--preset--font-family--brand: "Brand Sans", sans-serif;' )
			->toContain( '.has-brand-font-family' );
	} );

	it( 'skips theme preset values that could break out of the style block', function ( string $value ): void {
		$css = $this->compiler->compile( [
			'settings' => [
				'shadow'     => [ 'presets' => [ [ 'slug' => 'bad', 'shadow' => $value ] ] ],
				'typography' => [ 'fontFamilies' => [ [ 'slug' => 'ok', 'fontFamily' => '"Inter", sans-serif' ] ] ],
			],
		] );

		expect( $css )
			->not->toContain( 'shadow--bad' )
			->toContain( '--wp--preset--font-family--ok: "Inter", sans-serif;' );
	} )->with( [
		'tag breakout'      => [ '0 0 1px red</style><script>alert(1)</script>' ],
		'declaration break' => [ '0 0 1px red;}body{display:none' ],
		'comment'           => [ '0 0 1px red /*' ],
		'newline'           => [ "0 0 1px red\n}" ],
		'unbalanced quote'  => [ '0 0 1px "red' ],
		'unbalanced paren'  => [ '0 0 1px rgba(0, 0, 0' ],
		'stray close paren' => [ '0 0 1px red)(' ],
	] );

	it( 'skips interleaved-quote preset values without swallowing the next declaration (#885)', function ( string $value ): void {
		$css = $this->compiler->compile( [
			'settings' => [
				'typography' => [
					'fontFamilies' => [
						[ 'slug' => 'broken', 'fontFamily' => $value ],
						[ 'slug' => 'display', 'fontFamily' => '"Space Grotesk", sans-serif' ],
					],
				],
			],
		] );

		expect( $css )
			->not->toContain( '--wp--preset--font-family--broken:' )
			->toContain( '--wp--preset--font-family--display: "Space Grotesk", sans-serif;' );
	} )->with( [
		'double then single' => [ '"a\' "b\'' ],
		'single then double' => [ '\'a" \'b"' ],
	] );

	it( 'keeps preset values with a parenthesis inside a string (#885)', function ( string $value ): void {
		$css = $this->compiler->compile( [
			'settings' => [
				'typography' => [ 'fontFamilies' => [ [ 'slug' => 'quoted', 'fontFamily' => $value ] ] ],
			],
		] );

		expect( $css )->toContain( '--wp--preset--font-family--quoted: ' . $value . ';' );
	} )->with( [
		'smiley'     => [ '"Smile :)", sans-serif' ],
		'open paren' => [ "'(', serif" ],
	] );

	it( 'skips theme font families with an unbalanced quote', function (): void {
		$css = $this->compiler->compile( [
			'settings' => [
				'typography' => [
					'fontFamilies' => [
						[ 'slug' => 'broken', 'fontFamily' => '"Open Sans, serif' ],
						[ 'slug' => 'display', 'fontFamily' => '"Space Grotesk", sans-serif' ],
					],
				],
			],
		] );

		expect( $css )
			->not->toContain( '--wp--preset--font-family--broken:' )
			->toContain( '--wp--preset--font-family--display: "Space Grotesk", sans-serif;' );
	} );

	it( 'drops unsafe font-family values', function ( string $family ): void {
		config()->set( 'artisanpack.visual-editor.presets.font_families', [
			[ 'slug' => 'bad', 'fontFamily' => $family ],
		] );

		expect( $this->compiler->compileDefaults( null ) )->not->toContain( 'font-family--bad' );
	} )->with( [
		'declaration break' => [ 'serif;}body{color:red' ],
		'unbalanced quote'  => [ '"Open Sans, serif' ],
		'tag breakout'      => [ 'serif</style><script>' ],
		'comment'           => [ 'serif /* x' ],
	] );
} );

it( 'flattens settings.custom into --wp--custom properties (#878)', function (): void {
	$css = $this->compiler->compile( [
		'settings' => [
			'custom' => [
				'animation' => [
					'glowColor' => 'rgba(34, 211, 238, 0.6)',
					'glowSize'  => '0',
					'glowBlur'  => '24px',
				],
				'lineHeight' => [ 'tight' => 1.1 ],
			],
		],
	] );

	expect( $css )->toContain( '--wp--custom--animation--glow-color: rgba(34, 211, 238, 0.6);' )
		->toContain( '--wp--custom--animation--glow-size: 0;' )
		->toContain( '--wp--custom--animation--glow-blur: 24px;' )
		->toContain( '--wp--custom--line-height--tight: 1.1;' );
} );

it( 'skips settings.custom values that could break out of the style block (#878)', function (): void {
	$css = $this->compiler->compile( [
		'settings' => [
			'custom' => [
				'animation' => [
					'glowColor' => 'red; } body { display: none',
					'flag'      => true,
					'list'      => [ 'a', 'b' ],
				],
			],
		],
	] );

	expect( $css )->not->toContain( 'glow-color' )
		->not->toContain( '--wp--custom--animation--flag' )
		->not->toContain( '--wp--custom--animation--list' );
} );

it( 'compiles styles.blocks.*.variations into is-style rules after the block rules (#876)', function (): void {
	$css = $this->compiler->compile( [
		'styles' => [
			'blocks' => [
				'core/group' => [
					'color'      => [ 'background' => '#ffffff' ],
					'variations' => [
						'card' => [
							'border'   => [ 'radius' => '12px' ],
							'elements' => [ 'heading' => [ 'color' => [ 'text' => 'var:preset|color|primary' ] ] ],
						],
					],
				],
			],
		],
	] );

	expect( $css )->toContain( ':root :where(.wp-block-group.is-style-card) {' )
		->toContain( 'border-radius: 12px;' )
		->toContain( ':root :where(.wp-block-group.is-style-card h1' )
		->toContain( 'color: var(--wp--preset--color--primary);' )
		->and( strpos( $css, '.wp-block-group.is-style-card' ) )->toBeGreaterThan( strpos( $css, ':root :where(.wp-block-group) {' ) );
} );

it( 'skips block style variations when asked to (#876)', function (): void {
	$themeJson = [
		'styles' => [
			'blocks' => [
				'core/group' => [
					'color'      => [ 'background' => '#ffffff' ],
					'variations' => [ 'card' => [ 'border' => [ 'radius' => '12px' ] ] ],
				],
			],
		],
	];

	expect( $this->compiler->compile( $themeJson, false ) )->toContain( ':root :where(.wp-block-group) {' )
		->not->toContain( 'is-style-card' );
} );
