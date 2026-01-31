<?php

declare( strict_types=1 );

/**
 * Visual Editor main class.
 *
 * Provides the main entry point for the Visual Editor package, offering
 * convenient access to configuration, registries, and services.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.0.0
 */

namespace ArtisanPackUI\VisualEditor;

use ArtisanPackUI\VisualEditor\Registries\BlockRegistry;
use ArtisanPackUI\VisualEditor\Registries\SectionRegistry;
use ArtisanPackUI\VisualEditor\Registries\TemplateRegistry;
use ArtisanPackUI\VisualEditor\Services\GlobalStylesManager;

/**
 * Visual Editor class.
 *
 * Main facade-accessible class for the Visual Editor package. Provides
 * methods to interact with blocks, sections, templates, and global styles.
 *
 * @since 1.0.0
 */
class VisualEditor
{
	/**
	 * Get the package version.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function version(): string
	{
		return '1.0.0';
	}

	/**
	 * Get a configuration value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     The configuration key in dot notation.
	 * @param mixed  $default The default value.
	 *
	 * @return mixed
	 */
	public function config( string $key, mixed $default = null ): mixed
	{
		return config( "artisanpack.visual-editor.{$key}", $default );
	}

	/**
	 * Get the block registry.
	 *
	 * @since 1.0.0
	 *
	 * @return BlockRegistry
	 */
	public function blocks(): BlockRegistry
	{
		return app( BlockRegistry::class );
	}

	/**
	 * Get the section registry.
	 *
	 * @since 1.0.0
	 *
	 * @return SectionRegistry
	 */
	public function sections(): SectionRegistry
	{
		return app( SectionRegistry::class );
	}

	/**
	 * Get the template registry.
	 *
	 * @since 1.0.0
	 *
	 * @return TemplateRegistry
	 */
	public function templates(): TemplateRegistry
	{
		return app( TemplateRegistry::class );
	}

	/**
	 * Get the global styles manager.
	 *
	 * @since 1.0.0
	 *
	 * @return GlobalStylesManager
	 */
	public function styles(): GlobalStylesManager
	{
		return app( GlobalStylesManager::class );
	}

	/**
	 * Check if a feature is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @param string $feature The feature name.
	 *
	 * @return bool
	 */
	public function isFeatureEnabled( string $feature ): bool
	{
		return match ( $feature ) {
			'custom_blocks'      => (bool) $this->config( 'blocks.enable_custom_blocks', true ),
			'user_sections'      => (bool) $this->config( 'sections.enable_user_sections', true ),
			'custom_templates'   => (bool) $this->config( 'templates.enable_custom_templates', true ),
			'revisions'          => (bool) $this->config( 'content.enable_revisions', true ),
			'scheduling'         => (bool) $this->config( 'content.enable_scheduling', true ),
			'ai'                 => (bool) $this->config( 'ai.enabled', false ),
			'experiments'        => (bool) $this->config( 'experiments.enabled', false ),
			'accessibility'      => (bool) $this->config( 'accessibility.enabled', true ),
			'content_locking'    => (bool) $this->config( 'locking.enable_content_locking', true ),
			'block_locking'      => (bool) $this->config( 'locking.enable_block_locking', true ),
			'lazy_loading'       => (bool) $this->config( 'performance.enable_lazy_loading', true ),
			'style_editing'      => (bool) $this->config( 'styles.enable_style_editing', true ),
			default              => false,
		};
	}

	/**
	 * Get the autosave interval in seconds.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function getAutosaveInterval(): int
	{
		return (int) $this->config( 'editor.autosave_interval', 60 );
	}

	/**
	 * Get the maximum history states for undo/redo.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function getMaxHistoryStates(): int
	{
		return (int) $this->config( 'editor.max_history_states', 50 );
	}

	/**
	 * Get the preview mode.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function getPreviewMode(): string
	{
		return $this->config( 'editor.preview_mode', 'new_tab' );
	}

	/**
	 * Get the default content status.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function getDefaultStatus(): string
	{
		return $this->config( 'content.default_status', 'draft' );
	}

	/**
	 * Get all registered capabilities.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function getCapabilities(): array
	{
		return $this->config( 'permissions.custom_capabilities', [] );
	}

	/**
	 * Check if CMS framework permissions should be used.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function useCmsFrameworkPermissions(): bool
	{
		return (bool) $this->config( 'permissions.use_cms_framework', true );
	}

	/**
	 * Get the locking heartbeat interval in seconds.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function getLockHeartbeatInterval(): int
	{
		return (int) $this->config( 'locking.heartbeat_interval', 30 );
	}

	/**
	 * Get the lock timeout in seconds.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function getLockTimeout(): int
	{
		return (int) $this->config( 'locking.lock_timeout', 120 );
	}

	/**
	 * Get the minimum accessibility score for publishing.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function getMinAccessibilityScore(): int
	{
		return (int) $this->config( 'accessibility.minimum_score', 80 );
	}

	/**
	 * Check if publishing should be blocked on accessibility errors.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function blockPublishOnAccessibilityErrors(): bool
	{
		return (bool) $this->config( 'accessibility.block_publish_on_errors', false );
	}

	/**
	 * Get the accessibility checks that are enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function getAccessibilityChecks(): array
	{
		$checks = $this->config( 'accessibility.checks', [] );

		return array_keys( array_filter( $checks ) );
	}

	/**
	 * Get the cache TTL for rendered content.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function getCacheTtl(): int
	{
		return (int) $this->config( 'performance.cache_ttl', 3600 );
	}

	/**
	 * Check if content caching is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function isCachingEnabled(): bool
	{
		return (bool) $this->config( 'performance.cache_rendered_content', true );
	}

	/**
	 * Get the configured AI provider.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	public function getAiProvider(): ?string
	{
		if ( !$this->isFeatureEnabled( 'ai' ) ) {
			return null;
		}

		return $this->config( 'ai.default_provider' );
	}

	/**
	 * Get the AI provider configuration.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $provider The provider name. Defaults to default provider.
	 *
	 * @return array|null
	 */
	public function getAiProviderConfig( ?string $provider = null ): ?array
	{
		$provider = $provider ?? $this->getAiProvider();

		if ( !$provider ) {
			return null;
		}

		return $this->config( "ai.providers.{$provider}" );
	}

	/**
	 * Register a block type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type   The block type identifier.
	 * @param array  $config The block configuration.
	 *
	 * @return self
	 */
	public function registerBlock( string $type, array $config ): self
	{
		$this->blocks()->register( $type, $config );

		return $this;
	}

	/**
	 * Register a section type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type   The section type identifier.
	 * @param array  $config The section configuration.
	 *
	 * @return self
	 */
	public function registerSection( string $type, array $config ): self
	{
		$this->sections()->register( $type, $config );

		return $this;
	}

	/**
	 * Register a template.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name   The template name.
	 * @param array  $config The template configuration.
	 *
	 * @return self
	 */
	public function registerTemplate( string $name, array $config ): self
	{
		$this->templates()->register( $name, $config );

		return $this;
	}
}
