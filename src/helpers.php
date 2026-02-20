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
