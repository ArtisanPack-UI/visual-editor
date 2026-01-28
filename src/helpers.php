<?php

declare( strict_types=1 );

/**
 * Visual Editor Helper Functions
 *
 * Global helper functions for the Visual Editor package.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.0.0
 */

use ArtisanPackUI\VisualEditor\Registries\BlockRegistry;
use ArtisanPackUI\VisualEditor\Registries\SectionRegistry;
use ArtisanPackUI\VisualEditor\Registries\TemplateRegistry;
use ArtisanPackUI\VisualEditor\Services\GlobalStylesManager;
use ArtisanPackUI\VisualEditor\VisualEditor;

if ( !function_exists( 'visualEditor' ) ) {
	/**
	 * Get the Visual Editor instance.
	 *
	 * @since 1.0.0
	 *
	 * @return VisualEditor
	 */
	function visualEditor(): VisualEditor
	{
		return app( 'visual-editor' );
	}
}

if ( !function_exists( 'veConfig' ) ) {
	/**
	 * Get a Visual Editor configuration value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     The configuration key in dot notation.
	 * @param mixed  $default The default value.
	 *
	 * @return mixed
	 */
	function veConfig( string $key, mixed $default = null ): mixed
	{
		return config( "artisanpack.visual-editor.{$key}", $default );
	}
}

if ( !function_exists( 'veBlocks' ) ) {
	/**
	 * Get the block registry.
	 *
	 * @since 1.0.0
	 *
	 * @return BlockRegistry
	 */
	function veBlocks(): BlockRegistry
	{
		return app( BlockRegistry::class );
	}
}

if ( !function_exists( 'veSections' ) ) {
	/**
	 * Get the section registry.
	 *
	 * @since 1.0.0
	 *
	 * @return SectionRegistry
	 */
	function veSections(): SectionRegistry
	{
		return app( SectionRegistry::class );
	}
}

if ( !function_exists( 'veTemplates' ) ) {
	/**
	 * Get the template registry.
	 *
	 * @since 1.0.0
	 *
	 * @return TemplateRegistry
	 */
	function veTemplates(): TemplateRegistry
	{
		return app( TemplateRegistry::class );
	}
}

if ( !function_exists( 'veStyles' ) ) {
	/**
	 * Get the global styles manager.
	 *
	 * @since 1.0.0
	 *
	 * @return GlobalStylesManager
	 */
	function veStyles(): GlobalStylesManager
	{
		return app( GlobalStylesManager::class );
	}
}

if ( !function_exists( 'veRegisterBlock' ) ) {
	/**
	 * Register a block type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type   The block type identifier.
	 * @param array  $config The block configuration.
	 *
	 * @return BlockRegistry
	 */
	function veRegisterBlock( string $type, array $config ): BlockRegistry
	{
		return veBlocks()->register( $type, $config );
	}
}

if ( !function_exists( 'veRegisterSection' ) ) {
	/**
	 * Register a section type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type   The section type identifier.
	 * @param array  $config The section configuration.
	 *
	 * @return SectionRegistry
	 */
	function veRegisterSection( string $type, array $config ): SectionRegistry
	{
		return veSections()->register( $type, $config );
	}
}

if ( !function_exists( 'veRegisterTemplate' ) ) {
	/**
	 * Register a template.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name   The template name.
	 * @param array  $config The template configuration.
	 *
	 * @return TemplateRegistry
	 */
	function veRegisterTemplate( string $name, array $config ): TemplateRegistry
	{
		return veTemplates()->register( $name, $config );
	}
}

if ( !function_exists( 'veIsFeatureEnabled' ) ) {
	/**
	 * Check if a Visual Editor feature is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @param string $feature The feature name.
	 *
	 * @return bool
	 */
	function veIsFeatureEnabled( string $feature ): bool
	{
		return visualEditor()->isFeatureEnabled( $feature );
	}
}

if ( !function_exists( 'veGetBlock' ) ) {
	/**
	 * Get a block type configuration.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type The block type identifier.
	 *
	 * @return array|null
	 */
	function veGetBlock( string $type ): ?array
	{
		return veBlocks()->get( $type );
	}
}

if ( !function_exists( 'veGetSection' ) ) {
	/**
	 * Get a section type configuration.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type The section type identifier.
	 *
	 * @return array|null
	 */
	function veGetSection( string $type ): ?array
	{
		return veSections()->get( $type );
	}
}

if ( !function_exists( 'veGetTemplate' ) ) {
	/**
	 * Get a template configuration.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name The template name.
	 *
	 * @return array|null
	 */
	function veGetTemplate( string $name ): ?array
	{
		return veTemplates()->get( $name );
	}
}

if ( !function_exists( 'veGetGlobalStyles' ) ) {
	/**
	 * Get the current global styles.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	function veGetGlobalStyles(): array
	{
		return veStyles()->getStyles();
	}
}

if ( !function_exists( 'veGetStyleValue' ) ) {
	/**
	 * Get a specific global style value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     The style key in dot notation (e.g., 'colors.primary').
	 * @param mixed  $default The default value.
	 *
	 * @return mixed
	 */
	function veGetStyleValue( string $key, mixed $default = null ): mixed
	{
		return veStyles()->get( $key, $default );
	}
}

if ( !function_exists( 'veGenerateCss' ) ) {
	/**
	 * Generate CSS from global styles.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $cached Whether to use cached CSS.
	 *
	 * @return string
	 */
	function veGenerateCss( bool $cached = true ): string
	{
		return $cached ? veStyles()->getCachedCss() : veStyles()->generateCss();
	}
}

if ( !function_exists( 'veGetAvailableBlocks' ) ) {
	/**
	 * Get all available blocks (filtered by allowed/disallowed config).
	 *
	 * @since 1.0.0
	 *
	 * @return Illuminate\Support\Collection
	 */
	function veGetAvailableBlocks(): Illuminate\Support\Collection
	{
		return veBlocks()->getAvailable();
	}
}

if ( !function_exists( 'veGetBlocksByCategory' ) ) {
	/**
	 * Get blocks grouped by category.
	 *
	 * @since 1.0.0
	 *
	 * @return Illuminate\Support\Collection
	 */
	function veGetBlocksByCategory(): Illuminate\Support\Collection
	{
		return veBlocks()->getGroupedByCategory();
	}
}

if ( !function_exists( 'veGetSectionsByCategory' ) ) {
	/**
	 * Get sections grouped by category.
	 *
	 * @since 1.0.0
	 *
	 * @return Illuminate\Support\Collection
	 */
	function veGetSectionsByCategory(): Illuminate\Support\Collection
	{
		return veSections()->getGroupedByCategory();
	}
}

if ( !function_exists( 'veGetAllTemplates' ) ) {
	/**
	 * Get all registered templates.
	 *
	 * @since 1.0.0
	 *
	 * @return Illuminate\Support\Collection
	 */
	function veGetAllTemplates(): Illuminate\Support\Collection
	{
		return veTemplates()->all();
	}
}
