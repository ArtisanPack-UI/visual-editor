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
	 * Override in subclasses to customize supported features.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function getSupports(): array
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
			'border'     => false,
			'anchor'     => true,
			'className'  => true,
		];
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
}
