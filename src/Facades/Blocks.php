<?php

/**
 * Blocks Facade.
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
 * Facade for the Block Registry.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Facades
 *
 * @see \ArtisanPackUI\VisualEditor\Blocks\BlockRegistry
 *
 * @method static void register( \ArtisanPackUI\VisualEditor\Blocks\Contracts\BlockInterface $block )
 * @method static void unregister( string|array $type )
 * @method static void unregisterCategory( string $category )
 * @method static \ArtisanPackUI\VisualEditor\Blocks\Contracts\BlockInterface|null get( string $type )
 * @method static bool exists( string $type )
 * @method static array all()
 * @method static array getByCategory( string $category )
 * @method static array getCategories()
 *
 * @since      1.0.0
 */
class Blocks extends Facade
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
		return 'visual-editor.blocks';
	}
}
