<?php

/**
 * Block Supports Trait.
 *
 * Provides methods for managing block feature support declarations
 * such as alignment, colors, typography, spacing, and more.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Concerns
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Concerns;

/**
 * Trait for block feature support declarations.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Concerns
 *
 * @since      1.0.0
 */
trait HasBlockSupports
{
	/**
	 * Get the block's supported features.
	 *
	 * Reads from block.json metadata when available, falls back
	 * to default supports. Override in subclasses to customize.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function getSupports(): array
	{
		if ( property_exists( $this, 'metadata' ) && null !== $this->metadata && isset( $this->metadata['supports'] ) ) {
			return $this->mergeSupportsWithDefaults( $this->metadata['supports'] );
		}

		return $this->getDefaultSupports();
	}

	/**
	 * Check if the block supports a specific feature.
	 *
	 * @since 1.0.0
	 *
	 * @param string $feature The feature to check (e.g. 'align', 'color.text').
	 *
	 * @return bool
	 */
	public function supportsFeature( string $feature ): bool
	{
		$supports = $this->getSupports();
		$parts    = explode( '.', $feature );

		$value = $supports;
		foreach ( $parts as $part ) {
			if ( ! is_array( $value ) || ! array_key_exists( $part, $value ) ) {
				return false;
			}
			$value = $value[ $part ];
		}

		return (bool) $value;
	}

	/**
	 * Get supported alignment options.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function getSupportedAlignments(): array
	{
		$align = $this->getSupports()['align'] ?? false;

		if ( true === $align ) {
			return [ 'left', 'center', 'right', 'wide', 'full' ];
		}

		if ( is_array( $align ) ) {
			return $align;
		}

		return [];
	}

	/**
	 * Get a flat list of active style-related supports using dot-path notation.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, string>
	 */
	public function getActiveStyleSupports(): array
	{
		$supports = $this->getSupports();
		$active   = [];

		$styleKeys = [ 'color', 'typography', 'spacing', 'border', 'shadow', 'dimensions', 'background' ];

		foreach ( $styleKeys as $key ) {
			if ( ! isset( $supports[ $key ] ) ) {
				continue;
			}

			$value = $supports[ $key ];

			if ( is_bool( $value ) && $value ) {
				$active[] = $key;
			} elseif ( is_array( $value ) ) {
				foreach ( $value as $subKey => $subValue ) {
					if ( $subValue ) {
						$active[] = $key . '.' . $subKey;
					}
				}
			}
		}

		return $active;
	}

	/**
	 * Get the default supports array.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, mixed>
	 */
	protected function getDefaultSupports(): array
	{
		return [
			'align'      => false,
			'color'      => [
				'text'       => false,
				'background' => false,
			],
			'typography' => [
				'fontSize'   => false,
				'fontFamily' => false,
			],
			'spacing'    => [
				'margin'  => false,
				'padding' => false,
			],
			'border'      => false,
			'shadow'      => false,
			'dimensions'  => [
				'aspectRatio' => false,
				'minHeight'   => false,
			],
			'background'  => [
				'backgroundImage'    => false,
				'backgroundSize'     => false,
				'backgroundPosition' => false,
				'backgroundGradient' => false,
			],
			'anchor'     => true,
			'htmlId'     => true,
			'className'  => true,
		];
	}

	/**
	 * Merge block.json supports with defaults.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $supports The supports from block.json.
	 *
	 * @return array<string, mixed>
	 */
	protected function mergeSupportsWithDefaults( array $supports ): array
	{
		$defaults = $this->getDefaultSupports();

		return array_replace_recursive( $defaults, $supports );
	}
}
