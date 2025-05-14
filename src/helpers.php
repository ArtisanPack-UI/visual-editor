<?php

use ArtisanPackUI\VisualEditor\VisualEditor;

if ( !function_exists( 'visualEditor' ) ) {
	/**
	 * Get the Eventy instance.
	 *
	 * @return VisualEditor
	 */
	function visualEditor()
	{
		return app( 'visualEditor' );
	}
}
