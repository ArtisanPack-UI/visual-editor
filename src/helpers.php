<?php

/**
 * Visual Editor helper functions.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Contracts\BlockInterface;
use ArtisanPackUI\VisualEditor\VisualEditor;

if ( ! function_exists( 'visualEditor' ) ) {
	/**
	 * Get the Visual Editor instance.
	 *
	 * @since 1.0.0
	 *
	 * @return VisualEditor
	 */
	function visualEditor(): VisualEditor
	{
		return app( 'visual-editor' );
	}
}

if ( ! function_exists( 'veRegisterBlock' ) ) {
	/**
	 * Register a block type with the block registry.
	 *
	 * @since 1.0.0
	 *
	 * @param BlockInterface $block The block instance to register.
	 *
	 * @return void
	 */
	function veRegisterBlock( BlockInterface $block ): void
	{
		app( 'visual-editor.blocks' )->register( $block );
	}
}

if ( ! function_exists( 'veBlockExists' ) ) {
	/**
	 * Check if a block type is registered.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type The block type identifier.
	 *
	 * @return bool
	 */
	function veBlockExists( string $type ): bool
	{
		return app( 'visual-editor.blocks' )->exists( $type );
	}
}

if ( ! function_exists( 'veGetBlock' ) ) {
	/**
	 * Get a registered block by type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type The block type identifier.
	 *
	 * @return BlockInterface|null
	 */
	function veGetBlock( string $type ): ?BlockInterface
	{
		return app( 'visual-editor.blocks' )->get( $type );
	}
}
