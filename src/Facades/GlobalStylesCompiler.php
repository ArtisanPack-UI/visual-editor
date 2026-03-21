<?php

/**
 * GlobalStylesCompiler Facade.
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
 * Facade for the GlobalStylesCompiler service.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Facades
 *
 * @method static string compile( ?string $selectorOverride = null )
 * @method static string compileScoped( string $slug, array $overrides )
 * @method static string compileWithScopes( ?string $selectorOverride = null )
 * @method static string compileForEditor()
 * @method static string toInlineStyle( bool $forEditor = false )
 * @method static string toFile()
 * @method static string output()
 * @method static string getCached()
 * @method static void invalidateCache()
 * @method static string minify( string $css )
 * @method static array getSourceMap()
 * @method static void registerTemplateOverride( string $slug, array $overrides )
 * @method static array getTemplateOverrides()
 * @method static array getConfig()
 *
 * @see \ArtisanPackUI\VisualEditor\Services\GlobalStylesCompiler
 * @since      1.0.0
 */
class GlobalStylesCompiler extends Facade
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
		return 'visual-editor.global-styles';
	}
}
