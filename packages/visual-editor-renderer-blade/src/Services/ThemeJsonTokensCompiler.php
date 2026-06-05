<?php

/**
 * Compiles a `theme.json` payload into a `:root { --wp--preset--*: …; }`
 * CSS block, plus the `styles.*` declarations that drive the front-end
 * rendering of root, element, and per-block styles.
 *
 * Two halves to the output:
 *
 *   1. `settings.*` → `--wp--preset--{category}--{slug}` custom properties
 *      on `:root`. Mirrors WordPress's naming so block markup that already
 *      references those tokens (every Gutenberg core block's preset
 *      attributes do) just works on the public site without each consumer
 *      re-implementing the bridge. Categories covered:
 *      `color.palette[]`, `color.gradient[]`, `typography.fontSizes[]`,
 *      `spacing.spacingSizes[]`.
 *
 *   2. `styles.*` → CSS rules at three levels:
 *      - Root styles (`styles.color`, `styles.typography`, `styles.spacing`,
 *        `styles.border`) → `body { … }`.
 *      - Element styles (`styles.elements.{link, heading, h1..h6, button,
 *        caption, cite}`) → canonical selectors (`a`, `h1, h2, …`,
 *        `.wp-element-button`, etc.).
 *      - Block styles (`styles.blocks["<namespace>/<slug>"]`) →
 *        `.wp-block-<namespace>-<slug>` selectors. Nested
 *        `styles.blocks[X].elements.Y` produces descendant rules so a
 *        block can re-style its own headings/links without affecting
 *        the rest of the page.
 *
 * Anything outside those recognised sections passes through untouched so
 * theme.json files keep validating against WP's schema while only the
 * recognised payload drives CSS output. Preset references in the
 * `var:preset|{category}|{slug}` shorthand expand to
 * `var(--wp--preset--{category}--{slug})` so the styles half feeds off
 * the presets emitted in the first half.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Services;

class ThemeJsonTokensCompiler
{
	/**
	 * Compile a theme.json array into a `:root` CSS block, or '' when the
	 * input carries no recognised tokens.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $themeJson  Decoded theme.json payload.
	 */
	public function compile( array $themeJson ): string
	{
		$settings = is_array( $themeJson['settings'] ?? null ) ? $themeJson['settings'] : [];
		$styles   = is_array( $themeJson['styles'] ?? null ) ? $themeJson['styles'] : [];

		$declarations = array_merge(
			$this->compilePresetList( $settings, [ 'color', 'palette' ], 'color', 'color' ),
			$this->compilePresetList( $settings, [ 'color', 'gradient' ], 'gradient', 'gradient' ),
			$this->compilePresetList( $settings, [ 'typography', 'fontSizes' ], 'font-size', 'size' ),
			$this->compilePresetList( $settings, [ 'spacing', 'spacingSizes' ], 'spacing', 'size' ),
			$this->compileLayoutSizes( $settings ),
		);

		$root = [] === $declarations ? '' : ":root {\n\t" . implode( "\n\t", $declarations ) . "\n}";

		$layout = $this->compileLayoutRules( $settings );

		$utilities = $this->compilePresetUtilityClasses( $settings );

		$stylesCss = $this->compileStyles( $styles );

		$parts = array_values( array_filter( [ $root, $layout, $utilities, $stylesCss ], static fn ( string $section ): bool => '' !== $section ) );

		return implode( "\n\n", $parts );
	}

	/**
	 * Emit the `.has-{slug}-*` utility-class rules WordPress core binds
	 * to theme.json palette / font-size / gradient slugs. Without these
	 * a block that carries `has-accent-background-color` has no CSS
	 * rule pointing the class at `var(--wp--preset--color--accent)`.
	 *
	 * Mirrors core's `_wp_get_iframed_editor_assets()` / global-styles
	 * output: one rule per slug per property, marked `!important` so
	 * specificity matches inline styles.
	 *
	 * @since 1.2.0
	 *
	 * @param  array<string, mixed>  $settings  `themeJson.settings` payload.
	 */
	protected function compilePresetUtilityClasses( array $settings ): string
	{
		$rules = [];

		$colorPalette = $this->compileColorUtilityRules( $settings );
		if ( '' !== $colorPalette ) {
			$rules[] = $colorPalette;
		}

		$gradients = $this->compileGradientUtilityRules( $settings );
		if ( '' !== $gradients ) {
			$rules[] = $gradients;
		}

		$fontSizes = $this->compileFontSizeUtilityRules( $settings );
		if ( '' !== $fontSizes ) {
			$rules[] = $fontSizes;
		}

		return implode( "\n\n", $rules );
	}

	/**
	 * Emit `.has-{slug}-color`, `.has-{slug}-background-color`, and
	 * `.has-{slug}-border-color` for each entry in
	 * `settings.color.palette`.
	 *
	 * @param  array<string, mixed>  $settings
	 */
	protected function compileColorUtilityRules( array $settings ): string
	{
		$palette = $settings['color']['palette'] ?? null;
		if ( ! is_array( $palette ) ) {
			return '';
		}

		$lines = [];

		foreach ( $palette as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$slug  = isset( $entry['slug'] ) && is_string( $entry['slug'] ) ? trim( $entry['slug'] ) : '';
			$value = isset( $entry['color'] ) && is_string( $entry['color'] ) ? trim( $entry['color'] ) : '';

			// Skip entries that aren't a complete pair — the `:root`
			// emitter does the same. Emitting a utility class pointing
			// at an undefined `--wp--preset--*` variable would just be
			// dead CSS.
			if ( '' === $slug || '' === $value ) {
				continue;
			}

			$slug = $this->slug( $slug );
			$var  = sprintf( 'var(--wp--preset--color--%s)', $slug );

			$lines[] = sprintf( '.has-%s-color { color: %s !important; }', $slug, $var );
			$lines[] = sprintf( '.has-%s-background-color { background-color: %s !important; }', $slug, $var );
			$lines[] = sprintf( '.has-%s-border-color { border-color: %s !important; }', $slug, $var );
		}

		return implode( "\n", $lines );
	}

	/**
	 * Emit `.has-{slug}-gradient-background` for each entry in
	 * `settings.color.gradient`.
	 *
	 * @param  array<string, mixed>  $settings
	 */
	protected function compileGradientUtilityRules( array $settings ): string
	{
		$gradients = $settings['color']['gradient'] ?? null;
		if ( ! is_array( $gradients ) ) {
			return '';
		}

		$lines = [];

		foreach ( $gradients as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$slug  = isset( $entry['slug'] ) && is_string( $entry['slug'] ) ? trim( $entry['slug'] ) : '';
			$value = isset( $entry['gradient'] ) && is_string( $entry['gradient'] ) ? trim( $entry['gradient'] ) : '';

			if ( '' === $slug || '' === $value ) {
				continue;
			}

			$slug = $this->slug( $slug );

			$lines[] = sprintf(
				'.has-%s-gradient-background { background: var(--wp--preset--gradient--%s) !important; }',
				$slug,
				$slug
			);
		}

		return implode( "\n", $lines );
	}

	/**
	 * Emit `.has-{slug}-font-size` for each entry in
	 * `settings.typography.fontSizes`.
	 *
	 * @param  array<string, mixed>  $settings
	 */
	protected function compileFontSizeUtilityRules( array $settings ): string
	{
		$fontSizes = $settings['typography']['fontSizes'] ?? null;
		if ( ! is_array( $fontSizes ) ) {
			return '';
		}

		$lines = [];

		foreach ( $fontSizes as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$slug  = isset( $entry['slug'] ) && is_string( $entry['slug'] ) ? trim( $entry['slug'] ) : '';
			$value = isset( $entry['size'] ) && is_string( $entry['size'] ) ? trim( $entry['size'] ) : '';

			if ( '' === $slug || '' === $value ) {
				continue;
			}

			$slug = $this->slug( $slug );

			$lines[] = sprintf(
				'.has-%s-font-size { font-size: var(--wp--preset--font-size--%s) !important; }',
				$slug,
				$slug
			);
		}

		return implode( "\n", $lines );
	}

	/**
	 * Compile the `styles.*` half of theme.json into CSS rules. Skipped
	 * silently when the payload carries no recognised nodes.
	 *
	 * @since 1.2.0
	 *
	 * @param  array<string, mixed>  $styles  `themeJson.styles` payload.
	 */
	protected function compileStyles( array $styles ): string
	{
		$rules = [];

		// Root-level: anything declared at `styles.{color,typography,spacing,border}`
		// applies to the document body so it cascades through every block
		// and element. Skip if no recognised declarations are emitted.
		$rootDeclarations = $this->compileStyleNode( $styles );

		if ( '' !== $rootDeclarations ) {
			$rules[] = "body {\n\t" . $rootDeclarations . "\n}";
		}

		// styles.elements.* — typed selectors. Per-element nodes can also
		// carry sub-selectors via `:hover` / `:focus` state keys; we only
		// cover the base state today since the editor surface itself
		// doesn't expose state authoring yet.
		$elements = is_array( $styles['elements'] ?? null ) ? $styles['elements'] : [];

		foreach ( $elements as $name => $node ) {
			if ( ! is_string( $name ) || ! is_array( $node ) ) {
				continue;
			}

			$selector = $this->elementSelector( $name );

			if ( null === $selector ) {
				continue;
			}

			$declarations = $this->compileStyleNode( $node );

			if ( '' !== $declarations ) {
				$rules[] = $selector . " {\n\t" . $declarations . "\n}";
			}
		}

		// styles.blocks[<name>] — per-block rules. Nested `elements`
		// produces descendant rules so a block can override headings or
		// links inside it without affecting siblings.
		$blocks = is_array( $styles['blocks'] ?? null ) ? $styles['blocks'] : [];

		foreach ( $blocks as $blockName => $node ) {
			if ( ! is_string( $blockName ) || ! is_array( $node ) ) {
				continue;
			}

			$blockSelector = $this->blockSelector( $blockName );
			$declarations  = $this->compileStyleNode( $node );

			if ( '' !== $declarations ) {
				$rules[] = $blockSelector . " {\n\t" . $declarations . "\n}";
			}

			$nestedElements = is_array( $node['elements'] ?? null ) ? $node['elements'] : [];

			foreach ( $nestedElements as $elementName => $elementNode ) {
				if ( ! is_string( $elementName ) || ! is_array( $elementNode ) ) {
					continue;
				}

				$elementSelector = $this->elementSelector( $elementName );

				if ( null === $elementSelector ) {
					continue;
				}

				$nestedDeclarations = $this->compileStyleNode( $elementNode );

				if ( '' !== $nestedDeclarations ) {
					$rules[] = $blockSelector . ' ' . $elementSelector . " {\n\t" . $nestedDeclarations . "\n}";
				}
			}
		}

		return implode( "\n\n", $rules );
	}

	/**
	 * Convert a single style node (root, element, or block) into a
	 * tab-indented list of CSS declarations. The node shape mirrors
	 * Gutenberg's per-block style payload: `color.{background,text,gradient}`,
	 * `typography.{fontFamily,fontSize,fontWeight,fontStyle,lineHeight,
	 * letterSpacing,textTransform,textDecoration}`,
	 * `spacing.{padding,margin,blockGap}` (string or per-side object),
	 * and `border.{color,style,width,radius}` (radius may be string or
	 * per-corner object).
	 *
	 * Preset references (`var:preset|color|primary`) expand to the
	 * corresponding CSS custom property reference; non-preset values
	 * pass through untouched.
	 *
	 * @since 1.2.0
	 *
	 * @param  array<string, mixed>  $node
	 */
	protected function compileStyleNode( array $node ): string
	{
		$declarations = [];

		$color = is_array( $node['color'] ?? null ) ? $node['color'] : [];

		if ( isset( $color['background'] ) && is_string( $color['background'] ) && '' !== trim( $color['background'] ) ) {
			$declarations[] = 'background-color: ' . $this->expandPresetReference( trim( $color['background'] ) ) . ';';
		}

		if ( isset( $color['gradient'] ) && is_string( $color['gradient'] ) && '' !== trim( $color['gradient'] ) ) {
			$declarations[] = 'background: ' . $this->expandPresetReference( trim( $color['gradient'] ) ) . ';';
		}

		if ( isset( $color['text'] ) && is_string( $color['text'] ) && '' !== trim( $color['text'] ) ) {
			$declarations[] = 'color: ' . $this->expandPresetReference( trim( $color['text'] ) ) . ';';
		}

		$typography = is_array( $node['typography'] ?? null ) ? $node['typography'] : [];
		$typographyMap = [
			'fontFamily'     => 'font-family',
			'fontSize'       => 'font-size',
			'fontWeight'     => 'font-weight',
			'fontStyle'      => 'font-style',
			'lineHeight'     => 'line-height',
			'letterSpacing'  => 'letter-spacing',
			'textTransform'  => 'text-transform',
			'textDecoration' => 'text-decoration',
		];

		foreach ( $typographyMap as $key => $property ) {
			$value = $typography[ $key ] ?? null;

			if ( ! is_string( $value ) || '' === trim( $value ) ) {
				continue;
			}

			$declarations[] = $property . ': ' . $this->expandPresetReference( trim( $value ) ) . ';';
		}

		$spacing = is_array( $node['spacing'] ?? null ) ? $node['spacing'] : [];

		foreach ( [ 'padding', 'margin' ] as $box ) {
			$value = $spacing[ $box ] ?? null;

			if ( is_string( $value ) && '' !== trim( $value ) ) {
				$declarations[] = $box . ': ' . $this->expandPresetReference( trim( $value ) ) . ';';

				continue;
			}

			if ( ! is_array( $value ) ) {
				continue;
			}

			foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
				$sideValue = $value[ $side ] ?? null;

				if ( ! is_string( $sideValue ) || '' === trim( $sideValue ) ) {
					continue;
				}

				$declarations[] = $box . '-' . $side . ': ' . $this->expandPresetReference( trim( $sideValue ) ) . ';';
			}
		}

		$blockGap = $spacing['blockGap'] ?? null;

		if ( is_string( $blockGap ) && '' !== trim( $blockGap ) ) {
			$declarations[] = '--wp--style--block-gap: ' . $this->expandPresetReference( trim( $blockGap ) ) . ';';
		}

		$border = is_array( $node['border'] ?? null ) ? $node['border'] : [];

		foreach ( [ 'color', 'style', 'width' ] as $property ) {
			$value = $border[ $property ] ?? null;

			if ( ! is_string( $value ) || '' === trim( $value ) ) {
				continue;
			}

			$declarations[] = 'border-' . $property . ': ' . $this->expandPresetReference( trim( $value ) ) . ';';
		}

		$radius = $border['radius'] ?? null;

		if ( is_string( $radius ) && '' !== trim( $radius ) ) {
			$declarations[] = 'border-radius: ' . $this->expandPresetReference( trim( $radius ) ) . ';';
		} elseif ( is_array( $radius ) ) {
			foreach ( [ 'topLeft', 'topRight', 'bottomLeft', 'bottomRight' ] as $corner ) {
				$cornerValue = $radius[ $corner ] ?? null;

				if ( ! is_string( $cornerValue ) || '' === trim( $cornerValue ) ) {
					continue;
				}

				$declarations[] = 'border-' . $this->kebabCase( $corner ) . '-radius: ' . $this->expandPresetReference( trim( $cornerValue ) ) . ';';
			}
		}

		return implode( "\n\t", $declarations );
	}

	/**
	 * Translate a theme.json element key into its canonical selector.
	 * Returns `null` for unknown keys so callers can skip the rule.
	 *
	 * @since 1.2.0
	 */
	protected function elementSelector( string $element ): ?string
	{
		return match ( $element ) {
			'link'    => 'a',
			'heading' => 'h1, h2, h3, h4, h5, h6',
			'h1'      => 'h1',
			'h2'      => 'h2',
			'h3'      => 'h3',
			'h4'      => 'h4',
			'h5'      => 'h5',
			'h6'      => 'h6',
			'button'  => '.wp-element-button, .wp-block-button__link',
			'caption' => '.wp-element-caption',
			'cite'    => 'cite',
			default   => null,
		};
	}

	/**
	 * Translate a Gutenberg block name (`namespace/slug`) into its
	 * front-end class selector (`.wp-block-namespace-slug`). Block names
	 * outside the `namespace/slug` shape lose only the slash → dash
	 * substitution; downstream CSS authoring still resolves correctly.
	 *
	 * @since 1.2.0
	 */
	protected function blockSelector( string $blockName ): string
	{
		return '.wp-block-' . str_replace( '/', '-', $blockName );
	}

	/**
	 * Expand Gutenberg's `var:preset|{category}|{slug}` shorthand into a
	 * real CSS `var(--wp--preset--{category}--{slug})` reference. Anything
	 * else passes through untouched so themes can drop hex values or
	 * other primitives into theme.json without the compiler mangling
	 * them.
	 *
	 * @since 1.2.0
	 */
	protected function expandPresetReference( string $value ): string
	{
		if ( ! str_starts_with( $value, 'var:preset|' ) ) {
			return $value;
		}

		$parts = explode( '|', substr( $value, strlen( 'var:preset|' ) ) );
		$parts = array_map( fn ( string $segment ): string => $this->slug( $segment ), $parts );

		return 'var(--wp--preset--' . implode( '--', $parts ) . ')';
	}

	/**
	 * `topLeft` → `top-left`. Used to translate per-corner border-radius
	 * keys into CSS property names.
	 *
	 * @since 1.2.0
	 */
	protected function kebabCase( string $value ): string
	{
		return strtolower( (string) preg_replace( '/([a-z])([A-Z])/', '$1-$2', $value ) );
	}

	/**
	 * Emit `--wp--style--global--content-size` and
	 * `--wp--style--global--wide-size` custom properties from
	 * `settings.layout` so the layout rules generated by
	 * {@see compileLayoutRules} resolve against the theme's values.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $settings
	 *
	 * @return list<string>
	 */
	protected function compileLayoutSizes( array $settings ): array
	{
		$layout = $settings['layout'] ?? null;

		if ( ! is_array( $layout ) ) {
			return [];
		}

		$declarations = [];

		$contentSize = isset( $layout['contentSize'] ) && is_string( $layout['contentSize'] ) ? trim( $layout['contentSize'] ) : '';
		$wideSize    = isset( $layout['wideSize'] ) && is_string( $layout['wideSize'] ) ? trim( $layout['wideSize'] ) : '';

		if ( '' !== $contentSize ) {
			$declarations[] = '--wp--style--global--content-size: ' . $contentSize . ';';
		}

		if ( '' !== $wideSize ) {
			$declarations[] = '--wp--style--global--wide-size: ' . $wideSize . ';';
		}

		return $declarations;
	}

	/**
	 * Emit the layout rules that make `alignwide` / `alignfull` actually
	 * take effect on root-level constrained groups (Keystone #50).
	 *
	 * WordPress-FSE themes do this at the template level by wrapping
	 * post content in a `.wp-block-post-content` container that
	 * provides a parent context for `.is-layout-constrained > .alignwide`
	 * to match against. Our renderer-blade output drops blocks directly
	 * into the consumer's `<main>` / template wrapper, which carries no
	 * such marker — without these rules the alignment classes ride on
	 * the section but no CSS sizes the section accordingly.
	 *
	 * The rules target the constrained group's own classes (not a
	 * parent-relative selector), so they apply whether the group sits
	 * at the page root or inside another container. The default case
	 * (constrained group without an alignment override) is left alone
	 * so themes that style children-of-constrained directly aren't
	 * double-constrained.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $settings
	 */
	protected function compileLayoutRules( array $settings ): string
	{
		$layout = $settings['layout'] ?? null;

		if ( ! is_array( $layout ) ) {
			return '';
		}

		$hasContentSize = isset( $layout['contentSize'] ) && is_string( $layout['contentSize'] ) && '' !== trim( $layout['contentSize'] );
		$hasWideSize    = isset( $layout['wideSize'] ) && is_string( $layout['wideSize'] ) && '' !== trim( $layout['wideSize'] );

		if ( ! $hasContentSize && ! $hasWideSize ) {
			return '';
		}

		$rules = [];

		// Rule set A — the constrained group's OWN classes. Applies
		// whether the group is at the page root or nested inside
		// another container; covers the common "section with an
		// alignment override" case authors hit in the editor.
		if ( $hasWideSize ) {
			$rules[] = ".wp-block-group.is-layout-constrained.alignwide {\n"
				. "\tmax-width: var(--wp--style--global--wide-size);\n"
				. "\tmargin-left: auto;\n"
				. "\tmargin-right: auto;\n"
				. '}';
		}

		// `alignfull` is unconditional — full-bleed is the same regardless
		// of theme.json values. Emit it whenever ANY layout is configured
		// so authors can toggle it on without the renderer caring whether
		// `wideSize` happened to be declared.
		$rules[] = ".wp-block-group.is-layout-constrained.alignfull {\n"
			. "\tmax-width: none;\n"
			. '}';

		// Rule set B — children of an opt-in `.wp-block-post-content`
		// container. Mirrors WP-FSE's post-content layout: when a theme
		// wraps its page content in `<main class="wp-block-post-content
		// is-layout-constrained">`, the children get the canonical
		// "default = content-size, wide = wide-size, full = no max"
		// behavior. Themes that don't add the class keep the old
		// "container is full-bleed unless aligned" behavior — the
		// opt-in keeps this from clashing with header / footer
		// wrappers that are intentionally full-width.
		if ( $hasContentSize ) {
			$rules[] = ".wp-block-post-content.is-layout-constrained > :where(:not(.alignwide):not(.alignfull):not(.alignleft):not(.alignright)) {\n"
				. "\tmax-width: var(--wp--style--global--content-size);\n"
				. "\tmargin-left: auto;\n"
				. "\tmargin-right: auto;\n"
				. '}';
		}

		if ( $hasWideSize ) {
			$rules[] = ".wp-block-post-content.is-layout-constrained > .alignwide {\n"
				. "\tmax-width: var(--wp--style--global--wide-size);\n"
				. "\tmargin-left: auto;\n"
				. "\tmargin-right: auto;\n"
				. '}';
		}

		$rules[] = ".wp-block-post-content.is-layout-constrained > .alignfull {\n"
			. "\tmax-width: none;\n"
			. '}';

		return implode( "\n\n", $rules );
	}

	/**
	 * Walk `$settings[path[0]][path[1]]` if it exists and return one CSS
	 * declaration per entry, of the form
	 * `--wp--preset--{$category}--{$slug}: {$value};`.
	 *
	 * Entries missing either `slug` or the value key are skipped silently
	 * — theme.json validation is the consumer's responsibility, the
	 * compiler stays defensive so a malformed entry doesn't blow up the
	 * whole render.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $settings
	 * @param  array{0: string, 1: string}  $path
	 * @return list<string>
	 */
	protected function compilePresetList( array $settings, array $path, string $category, string $valueKey ): array
	{
		$list = $settings[ $path[0] ][ $path[1] ] ?? null;

		if ( ! is_array( $list ) ) {
			return [];
		}

		$declarations = [];

		foreach ( $list as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$slug  = isset( $entry['slug'] ) && is_string( $entry['slug'] ) ? trim( $entry['slug'] ) : '';
			$value = isset( $entry[ $valueKey ] ) && is_string( $entry[ $valueKey ] ) ? trim( $entry[ $valueKey ] ) : '';

			if ( '' === $slug || '' === $value ) {
				continue;
			}

			$declarations[] = sprintf(
				'--wp--preset--%s--%s: %s;',
				$this->slug( $category ),
				$this->slug( $slug ),
				$value
			);
		}

		return $declarations;
	}

	/**
	 * Normalise a slug for use in a CSS custom property name. Mirrors
	 * WordPress's behaviour: lowercase, ASCII-safe, hyphenated.
	 *
	 * @since 1.1.0
	 */
	protected function slug( string $value ): string
	{
		$value = strtolower( $value );

		return (string) preg_replace( '/[^a-z0-9\-]/', '-', $value );
	}
}
