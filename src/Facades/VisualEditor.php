<?php

/**
 * Visual Editor Facade.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Facades
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for the Visual Editor package.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Facades
 *
 * @see \ArtisanPackUI\VisualEditor\VisualEditor
 * @since      1.0.0
 */
class VisualEditor extends Facade
{
	/**
	 * Get the registered name of the component.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor(): string
	{
		return 'visual-editor';
	}
}
