<?php

/**
 * StyleCascadeResolver Facade.
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
 * Facade for the StyleCascadeResolver service.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Facades
 *
 * @method static array getGlobalStyles()
 * @method static array resolve( array $blockStyles = [], array $templateStyles = [] )
 * @method static array resolveInherited( array $templateStyles = [] )
 * @method static string getSource( string $path, array $blockStyles = [], array $templateStyles = [] )
 * @method static array getSourceMap( array $blockStyles = [], array $templateStyles = [] )
 * @method static mixed getInheritedValue( string $path, array $templateStyles = [] )
 * @method static bool isBlockOverride( string $path, array $blockStyles = [] )
 * @method static bool isTemplateOverride( string $path, array $templateStyles = [] )
 *
 * @see \ArtisanPackUI\VisualEditor\Services\StyleCascadeResolver
 * @since      1.0.0
 */
class StyleCascadeResolver extends Facade
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
		return 'visual-editor.style-cascade';
	}
}
