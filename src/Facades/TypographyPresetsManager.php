<?php

/**
 * TypographyPresetsManager Facade.
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
 * Facade for the TypographyPresetsManager service.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Facades
 *
 * @method static array getFontFamilies()
 * @method static void setFontFamilies( array $families )
 * @method static string|null getFontFamily( string $slot )
 * @method static void setFontFamily( string $slot, string $family )
 * @method static array getElements()
 * @method static void setElements( array $elements )
 * @method static array|null getElement( string $element )
 * @method static void setElement( string $element, array $styles )
 * @method static void setElementProperty( string $element, string $property, string $value )
 * @method static bool hasElement( string $element )
 * @method static void removeElement( string $element )
 * @method static array getDefaultFontFamilies()
 * @method static array getDefaultElements()
 * @method static void resetToDefaults()
 * @method static array generateTypeScale( float $baseSize, float $ratio, string $unit = 'rem' )
 * @method static void applyTypeScale( float $baseSize, float $ratio, string $unit = 'rem' )
 * @method static void registerFont( string $slug, string $name, string $family, string $category = 'all', string $source = 'custom' )
 * @method static void unregisterFont( string $slug )
 * @method static bool hasFont( string $slug )
 * @method static array|null getFont( string $slug )
 * @method static array getAvailableFonts( ?string $category = null )
 * @method static array getFontOptions( ?string $category = null )
 * @method static array getDefaultSystemFonts()
 * @method static void registerCustomFont( string $family, string $src, string $weight = '400', string $style = 'normal', string $category = 'all' )
 * @method static array getCustomFonts()
 * @method static void registerGoogleFont( string $family, array $weights = [ '400', '700' ], array $styles = [ 'normal' ], string $category = 'all' )
 * @method static array getGoogleFonts()
 * @method static string|null generateGoogleFontsUrl()
 * @method static string generateFontFaceDeclarations()
 * @method static string generateCssProperties()
 * @method static string generateCssBlock()
 * @method static array toStoreFormat()
 * @method static void fromStoreFormat( array $data )
 *
 * @see \ArtisanPackUI\VisualEditor\Services\TypographyPresetsManager
 * @since      1.0.0
 */
class TypographyPresetsManager extends Facade
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
		return 'visual-editor.typography-presets';
	}
}
