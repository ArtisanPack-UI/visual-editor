<?php

/**
 * TemplateManager Facade.
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
 * Facade for the TemplateManager service.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Facades
 *
 * @method static void register( string $slug, array $config )
 * @method static void unregister( string $slug )
 * @method static array all()
 * @method static array allActive()
 * @method static array forContentType( string $contentType )
 * @method static \ArtisanPackUI\VisualEditor\Models\Template|array|null resolve( string $slug )
 * @method static bool exists( string $slug )
 * @method static \ArtisanPackUI\VisualEditor\Models\Template create( array $data )
 * @method static \ArtisanPackUI\VisualEditor\Models\Template update( \ArtisanPackUI\VisualEditor\Models\Template $template, array $data, ?int $userId = null )
 * @method static bool delete( \ArtisanPackUI\VisualEditor\Models\Template $template )
 * @method static \ArtisanPackUI\VisualEditor\Models\Template duplicate( \ArtisanPackUI\VisualEditor\Models\Template $template, string $newSlug, ?string $newName = null )
 * @method static \ArtisanPackUI\VisualEditor\Models\Template lock( \ArtisanPackUI\VisualEditor\Models\Template $template )
 * @method static \ArtisanPackUI\VisualEditor\Models\Template unlock( \ArtisanPackUI\VisualEditor\Models\Template $template )
 * @method static array seedRegistered()
 * @method static array getRegistered()
 * @method static void clearRegistered()
 *
 * @see \ArtisanPackUI\VisualEditor\Services\TemplateManager
 * @since      1.0.0
 */
class TemplateManager extends Facade
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
		return 'visual-editor.templates';
	}
}
