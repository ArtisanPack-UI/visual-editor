<?php

declare( strict_types=1 );

/**
 * Visual Editor Facade
 *
 * Provides a static interface to the Visual Editor singleton.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Facades
 *
 * @since      1.0.0
 */

namespace ArtisanPackUI\VisualEditor\Facades;

use ArtisanPackUI\VisualEditor\Registries\BlockRegistry;
use ArtisanPackUI\VisualEditor\Registries\SectionRegistry;
use ArtisanPackUI\VisualEditor\Registries\TemplateRegistry;
use ArtisanPackUI\VisualEditor\Services\GlobalStylesManager;
use Illuminate\Support\Facades\Facade;

/**
 * Visual Editor Facade.
 *
 * @method static string version()
 * @method static mixed config( string $key, mixed $default = null )
 * @method static BlockRegistry blocks()
 * @method static SectionRegistry sections()
 * @method static TemplateRegistry templates()
 * @method static GlobalStylesManager styles()
 * @method static bool isFeatureEnabled( string $feature )
 * @method static int getAutosaveInterval()
 * @method static int getMaxHistoryStates()
 * @method static string getPreviewMode()
 * @method static string getDefaultStatus()
 * @method static array getCapabilities()
 * @method static bool useCmsFrameworkPermissions()
 * @method static int getLockHeartbeatInterval()
 * @method static int getLockTimeout()
 * @method static int getMinAccessibilityScore()
 * @method static bool blockPublishOnAccessibilityErrors()
 * @method static array getAccessibilityChecks()
 * @method static int getCacheTtl()
 * @method static bool isCachingEnabled()
 * @method static string|null getAiProvider()
 * @method static array|null getAiProviderConfig( ?string $provider = null )
 * @method static \ArtisanPackUI\VisualEditor\VisualEditor registerBlock( string $type, array $config )
 * @method static \ArtisanPackUI\VisualEditor\VisualEditor registerSection( string $type, array $config )
 * @method static \ArtisanPackUI\VisualEditor\VisualEditor registerTemplate( string $name, array $config )
 *
 * @see \ArtisanPackUI\VisualEditor\VisualEditor
 * @since 1.0.0
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
