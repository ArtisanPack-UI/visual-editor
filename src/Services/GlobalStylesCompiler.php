<?php

/**
 * Compiles a theme.json-shaped global-styles payload into front-end CSS.
 *
 * The compiler is pure and stateless: input is a `{ version, settings,
 * styles }` array (the same shape persisted by the C3 backend and
 * returned by `GET /visual-editor/api/global-styles/{id}`), output is a
 * CSS string ready to drop into a `<style>` tag.
 *
 * Token names follow the `--wp--preset--{family}--{slug}` convention
 * D3's site-editor canvas already uses (see colors-panel.tsx,
 * use-preset-data.ts) so canvas and front-end render against the same
 * variables — that is the canvas/published parity guarantee #378
 * exists to close.
 *
 * The compiler intentionally does not validate the schema: validation
 * happens at the API edge in {@see UpdateGlobalStylesRequest}. Anything
 * that survives `firstOrCreate` and round-trips through the model is
 * trusted here. Unrecognized keys are skipped silently so a future
 * theme.json schema bump only requires extending this compiler — old
 * payloads keep emitting whatever subset we already understand.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services;

class GlobalStylesCompiler
{
	/**
	 * Compiles a theme.json payload into a CSS string.
	 *
	 * Output is ordered: `:root` design-token declarations first, then
	 * page-level body styles, then `:where(...)` element rules
	 * (link / heading / button), then per-block overrides. The
	 * `:where()` wrapper keeps element selectors at zero specificity so
	 * a per-block or inline override always wins without `!important`.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $payload  `{ version, settings, styles }`.
	 */
	public function compile( array $payload ): string
	{
		$settings = isset( $payload['settings'] ) && is_array( $payload['settings'] )
			? $payload['settings']
			: [];
		$styles   = isset( $payload['styles'] ) && is_array( $payload['styles'] )
			? $payload['styles']
			: [];

		$blocks = [];

		$root = $this->compileRootTokens( $settings );

		if ( '' !== $root ) {
			$blocks[] = $root;
		}

		$body = $this->compileBodyStyles( $styles );

		if ( '' !== $body ) {
			$blocks[] = $body;
		}

		$elements = $this->compileElementStyles( $styles );

		if ( '' !== $elements ) {
			$blocks[] = $elements;
		}

		$blockOverrides = $this->compileBlockStyles( $styles );

		if ( '' !== $blockOverrides ) {
			$blocks[] = $blockOverrides;
		}

		return implode( "\n", $blocks );
	}

	/**
	 * Emits `:root { --wp--preset--*: ...; }` for every preset family in
	 * `settings`.
	 *
	 * Currently covers `color.palette`, `typography.fontFamilies`,
	 * `typography.fontSizes`, and `spacing.spacingSizes` (theme.json's
	 * shipped families). Layout sizes (`settings.layout.contentSize` /
	 * `wideSize`) emit as `--wp--style--global--{content,wide}-size` per
	 * the upstream theme.json convention.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $settings
	 */
	protected function compileRootTokens( array $settings ): string
	{
		$declarations = [];

		$declarations = array_merge(
			$declarations,
			$this->presetDeclarations(
				$settings,
				[ 'color', 'palette' ],
				'color',
				'color'
			)
		);
		$declarations = array_merge(
			$declarations,
			$this->presetDeclarations(
				$settings,
				[ 'typography', 'fontFamilies' ],
				'font-family',
				'fontFamily'
			)
		);
		$declarations = array_merge(
			$declarations,
			$this->presetDeclarations(
				$settings,
				[ 'typography', 'fontSizes' ],
				'font-size',
				'size'
			)
		);
		$declarations = array_merge(
			$declarations,
			$this->presetDeclarations(
				$settings,
				[ 'spacing', 'spacingSizes' ],
				'spacing',
				'size'
			)
		);

		$layout = isset( $settings['layout'] ) && is_array( $settings['layout'] )
			? $settings['layout']
			: [];

		if ( isset( $layout['contentSize'] ) && is_string( $layout['contentSize'] ) && '' !== $layout['contentSize'] ) {
			$declarations[] = '--wp--style--global--content-size: ' . $layout['contentSize'];
		}

		if ( isset( $layout['wideSize'] ) && is_string( $layout['wideSize'] ) && '' !== $layout['wideSize'] ) {
			$declarations[] = '--wp--style--global--wide-size: ' . $layout['wideSize'];
		}

		if ( [] === $declarations ) {
			return '';
		}

		return $this->wrapRule( ':root', $declarations );
	}

	/**
	 * Walks a presets path (e.g. `settings.color.palette`) and returns
	 * `--wp--preset--{family}--{slug}: {value};` declarations for every
	 * entry.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $settings
	 * @param  array<int, string>    $path      Nested key path under settings.
	 * @param  string                $family    CSS-side family slug (e.g. `color`, `font-size`).
	 * @param  string                $valueKey  Name of the value attribute on each preset entry.
	 *
	 * @return array<int, string>
	 */
	protected function presetDeclarations( array $settings, array $path, string $family, string $valueKey ): array
	{
		$node = $settings;

		foreach ( $path as $segment ) {
			if ( ! is_array( $node ) || ! array_key_exists( $segment, $node ) ) {
				return [];
			}

			$node = $node[ $segment ];
		}

		if ( ! is_array( $node ) ) {
			return [];
		}

		$declarations = [];

		foreach ( $node as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$slug  = isset( $entry['slug'] ) && is_string( $entry['slug'] ) ? $entry['slug'] : null;
			$value = isset( $entry[ $valueKey ] ) ? $entry[ $valueKey ] : null;

			if ( null === $slug || '' === $slug || ! is_string( $value ) || '' === $value ) {
				continue;
			}

			$safeSlug = $this->sanitizeSlug( $slug );

			if ( '' === $safeSlug ) {
				continue;
			}

			$declarations[] = sprintf(
				'--wp--preset--%s--%s: %s',
				$family,
				$safeSlug,
				$this->sanitizeCssValue( $value )
			);
		}

		return $declarations;
	}

	/**
	 * Compiles `styles.color` / `styles.typography` / `styles.spacing`
	 * into `body { ... }`.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $styles
	 */
	protected function compileBodyStyles( array $styles ): string
	{
		$declarations = $this->collectBlockDeclarations( $styles );

		if ( [] === $declarations ) {
			return '';
		}

		return $this->wrapRule( 'body', $declarations );
	}

	/**
	 * Compiles `styles.elements.{link,heading,button}` into element-level
	 * rules wrapped in `:where()` so they sit at zero specificity.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $styles
	 */
	protected function compileElementStyles( array $styles ): string
	{
		$elements = isset( $styles['elements'] ) && is_array( $styles['elements'] )
			? $styles['elements']
			: [];

		if ( [] === $elements ) {
			return '';
		}

		$selectorMap = [
			'link'    => 'a',
			'heading' => 'h1, h2, h3, h4, h5, h6',
			'h1'      => 'h1',
			'h2'      => 'h2',
			'h3'      => 'h3',
			'h4'      => 'h4',
			'h5'      => 'h5',
			'h6'      => 'h6',
			'button'  => '.wp-element-button, .wp-block-button__link',
		];

		$rules = [];

		foreach ( $selectorMap as $key => $selector ) {
			if ( ! isset( $elements[ $key ] ) || ! is_array( $elements[ $key ] ) ) {
				continue;
			}

			$declarations = $this->collectBlockDeclarations( $elements[ $key ] );

			if ( [] === $declarations ) {
				continue;
			}

			$rules[] = $this->wrapRule( ':where(' . $selector . ')', $declarations );
		}

		return implode( "\n", $rules );
	}

	/**
	 * Compiles `styles.blocks.{namespace/name}` into `.wp-block-{name}`
	 * rules — matching upstream WordPress's class convention so the
	 * canvas (which uses `@wordpress/block-editor`'s default classes)
	 * and the front-end target the same selectors.
	 *
	 * Block names are normalized: `core/X` becomes `wp-block-X`,
	 * `namespace/X` becomes `wp-block-namespace-X`. Anything outside
	 * `[a-z0-9-]` (after lowercasing) is dropped so a malformed key
	 * cannot inject arbitrary selector text.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $styles
	 */
	protected function compileBlockStyles( array $styles ): string
	{
		$blocks = isset( $styles['blocks'] ) && is_array( $styles['blocks'] )
			? $styles['blocks']
			: [];

		if ( [] === $blocks ) {
			return '';
		}

		$rules = [];

		foreach ( $blocks as $blockName => $blockStyles ) {
			if ( ! is_string( $blockName ) || ! is_array( $blockStyles ) ) {
				continue;
			}

			$selector = $this->blockSelector( $blockName );

			if ( null === $selector ) {
				continue;
			}

			$declarations = $this->collectBlockDeclarations( $blockStyles );

			if ( [] === $declarations ) {
				continue;
			}

			$rules[] = $this->wrapRule( $selector, $declarations );
		}

		return implode( "\n", $rules );
	}

	/**
	 * Translates a block name like `core/button` into `.wp-block-button`
	 * (or `.wp-block-artisanpack-callout` for namespaced blocks).
	 * Returns null for anything that doesn't look like a valid
	 * `namespace/name` pair.
	 *
	 * @since 1.0.0
	 */
	protected function blockSelector( string $blockName ): ?string
	{
		$blockName = strtolower( trim( $blockName ) );

		if ( '' === $blockName || false === strpos( $blockName, '/' ) ) {
			return null;
		}

		[ $namespace, $name ] = explode( '/', $blockName, 2 );

		$namespace = preg_replace( '/[^a-z0-9-]/', '', $namespace ) ?? '';
		$name      = preg_replace( '/[^a-z0-9-]/', '', $name ) ?? '';

		if ( '' === $name ) {
			return null;
		}

		$slug = ( '' === $namespace || 'core' === $namespace )
			? $name
			: $namespace . '-' . $name;

		return '.wp-block-' . $slug;
	}

	/**
	 * Pulls CSS declarations out of a block-styles node — the same shape
	 * is used at the body level, the element level, and per-block. Keys
	 * are translated from camelCase (`backgroundColor`) to kebab-case
	 * (`background-color`).
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $node
	 *
	 * @return array<int, string>
	 */
	protected function collectBlockDeclarations( array $node ): array
	{
		$declarations = [];

		// `color` → background-color, color, background.
		if ( isset( $node['color'] ) && is_array( $node['color'] ) ) {
			$color = $node['color'];

			if ( isset( $color['background'] ) && is_string( $color['background'] ) ) {
				$declarations[] = 'background-color: ' . $this->sanitizeCssValue( $color['background'] );
			}

			if ( isset( $color['gradient'] ) && is_string( $color['gradient'] ) ) {
				$declarations[] = 'background: ' . $this->sanitizeCssValue( $color['gradient'] );
			}

			if ( isset( $color['text'] ) && is_string( $color['text'] ) ) {
				$declarations[] = 'color: ' . $this->sanitizeCssValue( $color['text'] );
			}
		}

		// `typography` → font-family, font-size, font-weight, line-height, etc.
		if ( isset( $node['typography'] ) && is_array( $node['typography'] ) ) {
			foreach ( $node['typography'] as $prop => $value ) {
				if ( ! is_string( $prop ) || ! is_string( $value ) || '' === $value ) {
					continue;
				}

				$declarations[] = $this->kebabCase( $prop ) . ': ' . $this->sanitizeCssValue( $value );
			}
		}

		// `spacing` → padding, margin, blockGap (mapped to gap).
		if ( isset( $node['spacing'] ) && is_array( $node['spacing'] ) ) {
			$spacing = $node['spacing'];

			foreach ( [ 'padding', 'margin' ] as $box ) {
				if ( isset( $spacing[ $box ] ) ) {
					$declarations = array_merge(
						$declarations,
						$this->boxModelDeclarations( $box, $spacing[ $box ] )
					);
				}
			}

			if ( isset( $spacing['blockGap'] ) && is_string( $spacing['blockGap'] ) && '' !== $spacing['blockGap'] ) {
				$declarations[] = 'gap: ' . $this->sanitizeCssValue( $spacing['blockGap'] );
			}
		}

		// `border` → border-radius, border-color, border-width, border-style.
		if ( isset( $node['border'] ) && is_array( $node['border'] ) ) {
			$border = $node['border'];

			if ( isset( $border['radius'] ) && is_string( $border['radius'] ) && '' !== $border['radius'] ) {
				$declarations[] = 'border-radius: ' . $this->sanitizeCssValue( $border['radius'] );
			}

			foreach ( [ 'color', 'width', 'style' ] as $prop ) {
				if ( isset( $border[ $prop ] ) && is_string( $border[ $prop ] ) && '' !== $border[ $prop ] ) {
					$declarations[] = 'border-' . $prop . ': ' . $this->sanitizeCssValue( $border[ $prop ] );
				}
			}
		}

		return $declarations;
	}

	/**
	 * Expands a padding/margin value — either a single string ("1rem"),
	 * a per-side map (`{ top, right, bottom, left }`), or an empty
	 * value — into individual `padding-top: ...;` declarations.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	protected function boxModelDeclarations( string $box, mixed $value ): array
	{
		if ( is_string( $value ) && '' !== $value ) {
			return [ $box . ': ' . $this->sanitizeCssValue( $value ) ];
		}

		if ( ! is_array( $value ) ) {
			return [];
		}

		$declarations = [];

		foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
			if ( isset( $value[ $side ] ) && is_string( $value[ $side ] ) && '' !== $value[ $side ] ) {
				$declarations[] = $box . '-' . $side . ': ' . $this->sanitizeCssValue( $value[ $side ] );
			}
		}

		return $declarations;
	}

	/**
	 * Wraps `selector { decl1; decl2; }` with a trailing newline so the
	 * compiled output is human-readable when inspected in dev tools.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, string>  $declarations
	 */
	protected function wrapRule( string $selector, array $declarations ): string
	{
		return $selector . ' { ' . implode( '; ', $declarations ) . '; }';
	}

	/**
	 * Lowercases and strips anything outside the safe slug charset.
	 *
	 * @since 1.0.0
	 */
	protected function sanitizeSlug( string $slug ): string
	{
		$slug = strtolower( trim( $slug ) );

		return (string) preg_replace( '/[^a-z0-9_-]/', '', $slug );
	}

	/**
	 * Strips characters that could close the surrounding `<style>` tag
	 * or break out of the rule body. Values flow through validated
	 * theme.json input but defense-in-depth here keeps the compiler
	 * safe to call against any payload — including the raw model in
	 * tests, where validation has not run.
	 *
	 * @since 1.0.0
	 */
	protected function sanitizeCssValue( string $value ): string
	{
		$value = trim( $value );
		$value = (string) preg_replace( '/[<>{};]/', '', $value );

		return $value;
	}

	/**
	 * Translates camelCase property names into kebab-case CSS property
	 * names (`fontFamily` → `font-family`).
	 *
	 * @since 1.0.0
	 */
	protected function kebabCase( string $value ): string
	{
		$kebab = (string) preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $value );

		return strtolower( $kebab );
	}
}
