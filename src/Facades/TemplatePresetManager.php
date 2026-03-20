<?php

/**
 * TemplatePresetManager Facade.
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
 * Facade for the TemplatePresetManager service.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Facades
 *
 * @method static void register( string $slug, array $config )
 * @method static void unregister( string $slug )
 * @method static array all()
 * @method static array forCategory( string $category )
 * @method static array forContentType( string $contentType )
 * @method static array categories()
 * @method static \ArtisanPackUI\VisualEditor\Models\TemplatePreset|array|null resolve( string $slug )
 * @method static bool exists( string $slug )
 * @method static \ArtisanPackUI\VisualEditor\Models\TemplatePreset create( array $data )
 * @method static \ArtisanPackUI\VisualEditor\Models\TemplatePreset update( \ArtisanPackUI\VisualEditor\Models\TemplatePreset $preset, array $data )
 * @method static bool delete( \ArtisanPackUI\VisualEditor\Models\TemplatePreset $preset )
 * @method static \ArtisanPackUI\VisualEditor\Models\Template|null createTemplateFromPreset( string $presetSlug, string $slug, string $name, array $overrides = [] )
 * @method static \ArtisanPackUI\VisualEditor\Models\TemplatePreset saveTemplateAsPreset( \ArtisanPackUI\VisualEditor\Models\Template $template, string $slug, string $name, ?string $category = null )
 * @method static array seedRegistered()
 * @method static array getRegistered()
 * @method static void clearRegistered()
 *
 * @see \ArtisanPackUI\VisualEditor\Services\TemplatePresetManager
 * @since      1.0.0
 */
class TemplatePresetManager extends Facade
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
		return 'visual-editor.template-presets';
	}
}
