<?php

/**
 * Compiles a `theme.json` payload into a `:root { --wp--preset--*: …; }`
 * CSS block.
 *
 * Mirrors WordPress's `--wp--preset--{category}--{slug}` naming so block
 * markup that already references those tokens (every Gutenberg core block's
 * preset attributes do) just works on the public site without each
 * consumer re-implementing the bridge.
 *
 * Categories covered today: `color.palette[]`, `color.gradient[]`,
 * `typography.fontSizes[]`, `spacing.spacingSizes[]`. Anything else passes
 * through untouched so theme.json files keep validating against WP's
 * schema while only the recognised sections drive CSS output.
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

		$declarations = array_merge(
			$this->compilePresetList( $settings, [ 'color', 'palette' ], 'color', 'color' ),
			$this->compilePresetList( $settings, [ 'color', 'gradient' ], 'gradient', 'gradient' ),
			$this->compilePresetList( $settings, [ 'typography', 'fontSizes' ], 'font-size', 'size' ),
			$this->compilePresetList( $settings, [ 'spacing', 'spacingSizes' ], 'spacing', 'size' ),
		);

		if ( [] === $declarations ) {
			return '';
		}

		return ":root {\n\t" . implode( "\n\t", $declarations ) . "\n}";
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
