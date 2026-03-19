<?php

/**
 * TemplatePartManager Facade.
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
 * Facade for the TemplatePartManager service.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Facades
 *
 * @method static void register( string $slug, array $config )
 * @method static void unregister( string $slug )
 * @method static array all()
 * @method static array allActive()
 * @method static array forArea( string $area )
 * @method static \ArtisanPackUI\VisualEditor\Models\TemplatePart|array|null resolve( string $slug )
 * @method static bool exists( string $slug )
 * @method static \ArtisanPackUI\VisualEditor\Models\TemplatePart create( array $data )
 * @method static \ArtisanPackUI\VisualEditor\Models\TemplatePart update( \ArtisanPackUI\VisualEditor\Models\TemplatePart $part, array $data, ?int $userId = null )
 * @method static bool delete( \ArtisanPackUI\VisualEditor\Models\TemplatePart $part )
 * @method static \ArtisanPackUI\VisualEditor\Models\TemplatePart duplicate( \ArtisanPackUI\VisualEditor\Models\TemplatePart $part, string $newSlug, ?string $newName = null )
 * @method static \ArtisanPackUI\VisualEditor\Models\TemplatePart lock( \ArtisanPackUI\VisualEditor\Models\TemplatePart $part )
 * @method static \ArtisanPackUI\VisualEditor\Models\TemplatePart unlock( \ArtisanPackUI\VisualEditor\Models\TemplatePart $part )
 * @method static array seedRegistered()
 * @method static array getRegistered()
 * @method static void clearRegistered()
 *
 * @see \ArtisanPackUI\VisualEditor\Services\TemplatePartManager
 * @since      1.0.0
 */
class TemplatePartManager extends Facade
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
		return 'visual-editor.template-parts';
	}
}
