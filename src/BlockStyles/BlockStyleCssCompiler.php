<?php

/**
 * Compiles block style variations into front-end and canvas CSS (#876).
 *
 * Each variation's theme.json style node becomes a rule on
 * `.wp-block-{name}.is-style-{slug}`, wrapped in `:root :where(…)` the
 * way WordPress emits global styles, so it shares their flat (0,1,0)
 * specificity: `.has-*` preset classes and a block's own inline styles
 * still beat it. Nested `elements` produce descendant rules (with the
 * link and button `:link` / `:any-link` / `:visited` / `:hover` /
 * `:focus` / `:focus-visible` / `:active` states), and nested
 * `blocks` style inner blocks of a variation (a "Card" group re-styling
 * its headings).
 *
 * The same CSS reaches the editor canvas (`GET /global-styles/css`) and
 * the front end (the Blade renderer's global-styles emission), so the
 * picker preview and the published page match.
 *
 * Values are printed unescaped into a `<style>` block, so anything that
 * could close the declaration, rule or element is dropped
 * ({@see CssValue::isPrintable()}).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\BlockStyles;

use ArtisanPackUI\VisualEditor\Resources\PresetRegistry;
use ArtisanPackUI\VisualEditor\Support\CssValue;

class BlockStyleCssCompiler
{
	/**
	 * Element selectors for `elements.*`, matching the renderer's
	 * global-styles compiler. `link` skips `.wp-element-button`, as
	 * WordPress's `WP_Theme_JSON::ELEMENTS` does, so link styles never
	 * restyle button links.
	 *
	 * @var array<string, list<string>>
	 */
	public const ELEMENT_SELECTORS = [
		'link'    => [ 'a:where(:not(.wp-element-button))' ],
		'button'  => [ '.wp-element-button', '.wp-block-button__link' ],
		'heading' => [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ],
		'h1'      => [ 'h1' ],
		'h2'      => [ 'h2' ],
		'h3'      => [ 'h3' ],
		'h4'      => [ 'h4' ],
		'h5'      => [ 'h5' ],
		'h6'      => [ 'h6' ],
		'caption' => [ '.wp-element-caption' ],
		'cite'    => [ 'cite' ],
	];

	/**
	 * Every pseudo-state sub-node an element can carry, in WordPress's
	 * cascade order (`WP_Theme_JSON::VALID_ELEMENT_PSEUDO_SELECTORS`).
	 *
	 * @var list<string>
	 */
	public const PSEUDO_STATES = [ ':link', ':any-link', ':visited', ':hover', ':focus', ':focus-visible', ':active' ];

	/**
	 * The pseudo states each element takes: only `link` and `button`, as
	 * in WordPress. Other elements' pseudo sub-nodes are ignored. Mirrors
	 * the Blade renderer's `ElementsSupport::ELEMENT_PSEUDO_STATES`.
	 *
	 * @var array<string, list<string>>
	 */
	public const ELEMENT_PSEUDO_STATES = [
		'link'   => self::PSEUDO_STATES,
		'button' => self::PSEUDO_STATES,
	];

	/**
	 * theme.json `typography` keys → CSS properties.
	 *
	 * @var array<string, string>
	 */
	protected const TYPOGRAPHY = [
		'fontFamily'     => 'font-family',
		'fontSize'       => 'font-size',
		'fontWeight'     => 'font-weight',
		'fontStyle'      => 'font-style',
		'lineHeight'     => 'line-height',
		'letterSpacing'  => 'letter-spacing',
		'textTransform'  => 'text-transform',
		'textDecoration' => 'text-decoration',
	];

	/**
	 * theme.json `outline` keys → CSS properties.
	 *
	 * @var array<string, string>
	 */
	protected const OUTLINE = [
		'color'  => 'outline-color',
		'offset' => 'outline-offset',
		'style'  => 'outline-style',
		'width'  => 'outline-width',
	];

	/**
	 * Where a block's styles land when that isn't the wrapper, mirroring
	 * the block.json `selectors` of the core blocks that declare them: a
	 * button is painted on its inner link, a table on its `<table>`, an
	 * image's border and shadow on its `<img>`. Keyed by group (`root`
	 * covers every group without its own entry); each selector suffix is
	 * appended to the variation's wrapper selectors.
	 *
	 * @var array<string, array<string, string>>
	 */
	protected const FEATURE_SELECTORS = [
		'button'              => [ 'root' => ' .wp-block-button__link' ],
		'table'               => [ 'root' => ' > table' ],
		'image'               => [ 'border' => ' img', 'shadow' => ' img' ],
		'post-featured-image' => [ 'border' => ' img', 'shadow' => ' img' ],
		'avatar'              => [ 'border' => ' img' ],
	];

	/**
	 * Compile every variation's rules, or `''` when none declare styles.
	 *
	 * @since 1.15.0
	 *
	 * @param  list<array{block: string, slug: string, style: array<string, mixed>}>  $variations
	 */
	public function compile( array $variations ): string
	{
		$rules = [];

		foreach ( $variations as $variation ) {
			$style = $variation['style'] ?? [];

			if ( ! is_array( $style ) || [] === $style ) {
				continue;
			}

			$selectors = array_map(
				static fn ( string $selector ): string => $selector . '.is-style-' . $variation['slug'],
				$this->blockSelectors( (string) $variation['block'] ),
			);

			array_push( $rules, ...$this->compileNode( $style, $selectors, $this->featureSelectors( (string) $variation['block'], $selectors ) ) );

			$innerBlocks = is_array( $style['blocks'] ?? null ) ? $style['blocks'] : [];

			foreach ( $innerBlocks as $innerName => $innerStyle ) {
				$inner = is_string( $innerName ) ? BlockStyleVariationParser::normaliseBlockName( $innerName ) : null;

				if ( null === $inner || ! is_array( $innerStyle ) ) {
					continue;
				}

				$descendants = [];

				foreach ( $selectors as $selector ) {
					foreach ( $this->blockSelectors( $inner ) as $innerSelector ) {
						$descendants[] = $selector . ' ' . $innerSelector;
					}
				}

				array_push( $rules, ...$this->compileNode( $innerStyle, $descendants, $this->featureSelectors( $inner, $descendants ) ) );
			}
		}

		return implode( "\n\n", $rules );
	}

	/**
	 * The front-end classes a block name renders. `core/x` renders
	 * `.wp-block-x`. A first-party `artisanpack/x` block can be a fork of
	 * `core/x` (rendering `.wp-block-x`) or its own block (rendering
	 * `.wp-block-artisanpack-x`), so both are targeted.
	 *
	 * @since 1.15.0
	 *
	 * @return list<string>
	 */
	public function blockSelectors( string $block ): array
	{
		[ $namespace, $name ] = explode( '/', $block, 2 );

		if ( 'core' === $namespace ) {
			return [ '.wp-block-' . $name ];
		}

		if ( 'artisanpack' === $namespace ) {
			return [ '.wp-block-' . $name, '.wp-block-artisanpack-' . $name ];
		}

		return [ '.wp-block-' . $namespace . '-' . $name ];
	}

	/**
	 * The selectors each declaration group of a variation targets, for
	 * blocks in {@see self::FEATURE_SELECTORS}; `[]` for every other block.
	 *
	 * @since 1.15.0
	 *
	 * @param  list<string>  $wrappers  The variation's wrapper selectors.
	 *
	 * @return array<string, list<string>>
	 */
	public function featureSelectors( string $block, array $wrappers ): array
	{
		[ $namespace, $name ] = explode( '/', $block, 2 );

		if ( 'core' !== $namespace && 'artisanpack' !== $namespace ) {
			return [];
		}

		$features = [];

		foreach ( self::FEATURE_SELECTORS[ $name ] ?? [] as $group => $suffix ) {
			$features[ $group ] = array_map( static fn ( string $wrapper ): string => $wrapper . $suffix, $wrappers );
		}

		return $features;
	}

	/**
	 * Rules for one style node (base declarations plus its elements).
	 * Elements always nest under `$selectors`; declaration groups listed
	 * in `$features` go to those selectors instead.
	 *
	 * @param  array<string, mixed>         $node
	 * @param  list<string>                 $selectors
	 * @param  array<string, list<string>>  $features
	 *
	 * @return list<string>
	 */
	protected function compileNode( array $node, array $selectors, array $features = [] ): array
	{
		$rules   = [];
		$targets = [];

		foreach ( $this->declarationGroups( $node ) as $group => $declarations ) {
			$groupSelectors = $features[ $group ] ?? $features['root'] ?? $selectors;
			$key            = implode( ', ', $groupSelectors );

			$targets[ $key ]['selectors']    = $groupSelectors;
			$targets[ $key ]['declarations'] = array_merge( $targets[ $key ]['declarations'] ?? [], $declarations );
		}

		foreach ( $targets as $target ) {
			$rules[] = $this->whereRule( $target['selectors'], implode( "\n\t", $target['declarations'] ) );
		}

		$elements = is_array( $node['elements'] ?? null ) ? $node['elements'] : [];

		foreach ( $elements as $element => $elementNode ) {
			$elementSelectors = is_string( $element ) ? ( self::ELEMENT_SELECTORS[ $element ] ?? null ) : null;

			if ( null === $elementSelectors || ! is_array( $elementNode ) ) {
				continue;
			}

			$scoped = [];

			foreach ( $selectors as $selector ) {
				foreach ( $elementSelectors as $elementSelector ) {
					$scoped[] = $selector . ' ' . $elementSelector;
				}
			}

			$elementDeclarations = $this->declarations( $elementNode );

			if ( '' !== $elementDeclarations ) {
				$rules[] = $this->whereRule( $scoped, $elementDeclarations );
			}

			foreach ( self::ELEMENT_PSEUDO_STATES[ $element ] ?? [] as $pseudo ) {
				$pseudoDeclarations = is_array( $elementNode[ $pseudo ] ?? null ) ? $this->declarations( $elementNode[ $pseudo ] ) : '';

				if ( '' !== $pseudoDeclarations ) {
					$rules[] = $this->whereRule(
						array_map( static fn ( string $selector ): string => $selector . $pseudo, $scoped ),
						$pseudoDeclarations,
					);
				}
			}
		}

		return $rules;
	}

	/**
	 * @param  list<string>  $selectors
	 */
	protected function whereRule( array $selectors, string $declarations ): string
	{
		return ':root :where(' . implode( ', ', $selectors ) . ") {\n\t" . $declarations . "\n}";
	}

	/**
	 * Tab-joined CSS declarations for a style node, for element nodes
	 * that always share one selector.
	 *
	 * @param  array<string, mixed>  $node
	 */
	protected function declarations( array $node ): string
	{
		return implode( "\n\t", array_merge( ...array_values( $this->declarationGroups( $node ) ) ) );
	}

	/**
	 * CSS declarations for a style node's `color`, `typography`,
	 * `spacing`, `border`, `shadow`, `dimensions` and `outline` groups,
	 * keyed by group; empty groups are left out.
	 *
	 * @param  array<string, mixed>  $node
	 *
	 * @return array<string, list<string>>
	 */
	protected function declarationGroups( array $node ): array
	{
		$color      = is_array( $node['color'] ?? null ) ? $node['color'] : [];
		$typography = is_array( $node['typography'] ?? null ) ? $node['typography'] : [];
		$spacing    = is_array( $node['spacing'] ?? null ) ? $node['spacing'] : [];
		$border     = is_array( $node['border'] ?? null ) ? $node['border'] : [];
		$dimensions = is_array( $node['dimensions'] ?? null ) ? $node['dimensions'] : [];
		$outline    = is_array( $node['outline'] ?? null ) ? $node['outline'] : [];

		$groups = [
			'color'      => [],
			'typography' => [],
			'spacing'    => [],
			'border'     => [],
			'shadow'     => [],
			'dimensions' => [],
			'outline'    => [],
		];

		// The `background` shorthand resets `background-color`, so the
		// gradient goes first, in WordPress's `PROPERTIES_METADATA` order.
		$this->push( $groups['color'], 'background', $color['gradient'] ?? null );
		$this->push( $groups['color'], 'background-color', $color['background'] ?? null );
		$this->push( $groups['color'], 'color', $color['text'] ?? null );

		foreach ( self::TYPOGRAPHY as $key => $property ) {
			$this->push( $groups['typography'], $property, $typography[ $key ] ?? null );
		}

		foreach ( [ 'padding', 'margin' ] as $box ) {
			$this->pushBox( $groups['spacing'], $box, $spacing[ $box ] ?? null );
		}

		$this->push( $groups['spacing'], '--wp--style--block-gap', $spacing['blockGap'] ?? null );

		foreach ( [ 'color', 'style', 'width' ] as $property ) {
			$this->push( $groups['border'], 'border-' . $property, $border[ $property ] ?? null );
		}

		foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
			$sideNode = is_array( $border[ $side ] ?? null ) ? $border[ $side ] : [];

			foreach ( [ 'color', 'style', 'width' ] as $property ) {
				$this->push( $groups['border'], 'border-' . $side . '-' . $property, $sideNode[ $property ] ?? null );
			}
		}

		$radius = $border['radius'] ?? null;

		if ( is_array( $radius ) ) {
			foreach ( [ 'topLeft' => 'top-left', 'topRight' => 'top-right', 'bottomLeft' => 'bottom-left', 'bottomRight' => 'bottom-right' ] as $corner => $property ) {
				$this->push( $groups['border'], 'border-' . $property . '-radius', $radius[ $corner ] ?? null );
			}
		} else {
			$this->push( $groups['border'], 'border-radius', $radius );
		}

		$this->push( $groups['shadow'], 'box-shadow', $node['shadow'] ?? null );

		$this->push( $groups['dimensions'], 'min-height', $dimensions['minHeight'] ?? null );
		$this->push( $groups['dimensions'], 'aspect-ratio', $dimensions['aspectRatio'] ?? null );

		foreach ( self::OUTLINE as $key => $property ) {
			$this->push( $groups['outline'], $property, $outline[ $key ] ?? null );
		}

		return array_filter( $groups, static fn ( array $declarations ): bool => [] !== $declarations );
	}

	/**
	 * Append `padding` / `margin` as a shorthand string or per side.
	 *
	 * @param  list<string>  $declarations
	 */
	protected function pushBox( array &$declarations, string $box, mixed $value ): void
	{
		if ( ! is_array( $value ) ) {
			$this->push( $declarations, $box, $value );

			return;
		}

		foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
			$this->push( $declarations, $box . '-' . $side, $value[ $side ] ?? null );
		}
	}

	/**
	 * Append one declaration when the value is a printable string.
	 *
	 * @param  list<string>  $declarations
	 */
	protected function push( array &$declarations, string $property, mixed $value ): void
	{
		if ( is_int( $value ) || is_float( $value ) ) {
			$value = (string) $value;
		}

		if ( ! is_string( $value ) ) {
			return;
		}

		$value = trim( $value );

		if ( '' === $value || ! CssValue::isPrintable( $value ) ) {
			return;
		}

		$declarations[] = $property . ': ' . $this->expandPresetReference( $value ) . ';';
	}

	/**
	 * `var:preset|color|primary` → `var(--wp--preset--color--primary)`.
	 */
	protected function expandPresetReference( string $value ): string
	{
		if ( ! str_starts_with( $value, 'var:preset|' ) && ! str_starts_with( $value, 'var:custom|' ) ) {
			return $value;
		}

		[ $kind, $path ] = explode( '|', $value, 2 );
		$segments        = array_map( PresetRegistry::presetSlug( ... ), explode( '|', $path ) );

		return 'var(--wp--' . substr( $kind, 4 ) . '--' . implode( '--', $segments ) . ')';
	}
}
