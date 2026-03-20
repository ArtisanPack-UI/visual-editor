<?php

/**
 * ColorPaletteManager Facade.
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
 * Facade for the ColorPaletteManager service.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Facades
 *
 * @method static array getPalette()
 * @method static void setPalette( array $palette )
 * @method static array|null getColor( string $slug )
 * @method static string|null getColorValue( string $slug )
 * @method static void setColor( string $slug, string $name, string $color )
 * @method static void removeColor( string $slug )
 * @method static bool hasColor( string $slug )
 * @method static array getDefaultPalette()
 * @method static void resetToDefaults()
 * @method static string generateCssProperties( bool $includeShades = true )
 * @method static string generateCssBlock( bool $includeShades = true )
 * @method static array generateShades( string $hex )
 * @method static bool|null checkContrast( string $foreground, string $background )
 * @method static array checkPaletteContrast( string $background )
 * @method static string resolveColorReference( string $value )
 * @method static array toStoreFormat()
 * @method static void fromStoreFormat( array $entries )
 *
 * @see \ArtisanPackUI\VisualEditor\Services\ColorPaletteManager
 * @since      1.0.0
 */
class ColorPaletteManager extends Facade
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
		return 'visual-editor.color-palette';
	}
}
