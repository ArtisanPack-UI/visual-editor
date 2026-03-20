<?php

/**
 * SpacingScaleManager Facade.
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
 * Facade for the SpacingScaleManager service.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Facades
 *
 * @method static array getScale()
 * @method static void setScale( array $scale )
 * @method static array|null getStep( string $slug )
 * @method static string|null getStepValue( string $slug )
 * @method static void setStep( string $slug, string $name, string $value )
 * @method static void removeStep( string $slug )
 * @method static bool hasStep( string $slug )
 * @method static string getBlockGap()
 * @method static void setBlockGap( string $slug )
 * @method static string|null getBlockGapValue()
 * @method static array getDefaultScale()
 * @method static string getDefaultBlockGap()
 * @method static void resetToDefaults()
 * @method static void applyPreset( string $preset )
 * @method static array getPresets()
 * @method static string resolveSpacingReference( string $value )
 * @method static string generateCssProperties()
 * @method static string generateCssBlock()
 * @method static array toStoreFormat()
 * @method static void fromStoreFormat( array $data )
 *
 * @see \ArtisanPackUI\VisualEditor\Services\SpacingScaleManager
 * @since      1.0.0
 */
class SpacingScaleManager extends Facade
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
		return 'visual-editor.spacing-scale';
	}
}
