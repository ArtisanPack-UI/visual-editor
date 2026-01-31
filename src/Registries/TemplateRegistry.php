<?php

declare( strict_types=1 );

/**
 * Template Registry
 *
 * Manages the registration and retrieval of page templates for the visual editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Registries
 *
 * @since      1.0.0
 */

namespace ArtisanPackUI\VisualEditor\Registries;

use Illuminate\Support\Collection;

/**
 * Template Registry class.
 *
 * Provides a centralized registry for managing page templates in the visual editor.
 * Templates define the overall structure of a page including header, footer, and
 * content areas.
 *
 * @since 1.0.0
 */
class TemplateRegistry
{
	/**
	 * The registered templates.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array>
	 */
	protected array $templates = [];

	/**
	 * The registered template parts.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array>
	 */
	protected array $templateParts = [];

	/**
	 * Register a template.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name   The template identifier.
	 * @param array  $config The template configuration.
	 *
	 * @return self
	 */
	public function register( string $name, array $config ): self
	{
		$this->templates[ $name ] = array_merge( [
			'name'        => $name,
			'description' => '',
			'icon'        => 'fas.file',
			'parts'       => config( 'artisanpack.visual-editor.templates.default_template_parts', [ 'header', 'footer' ] ),
			'supports'    => [ 'title', 'editor', 'thumbnail' ],
		], $config );

		return $this;
	}

	/**
	 * Unregister a template.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name The template identifier.
	 *
	 * @return self
	 */
	public function unregister( string $name ): self
	{
		unset( $this->templates[ $name ] );

		return $this;
	}

	/**
	 * Check if a template is registered.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name The template identifier.
	 *
	 * @return bool
	 */
	public function has( string $name ): bool
	{
		return isset( $this->templates[ $name ] );
	}

	/**
	 * Get a template configuration.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name The template identifier.
	 *
	 * @return array|null
	 */
	public function get( string $name ): ?array
	{
		return $this->templates[ $name ] ?? null;
	}

	/**
	 * Get all registered templates.
	 *
	 * @since 1.0.0
	 *
	 * @return Collection
	 */
	public function all(): Collection
	{
		return collect( $this->templates );
	}

	/**
	 * Register a template part.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name   The template part identifier.
	 * @param array  $config The template part configuration.
	 *
	 * @return self
	 */
	public function registerPart( string $name, array $config ): self
	{
		$this->templateParts[ $name ] = array_merge( [
			'name'        => $name,
			'description' => '',
			'area'        => 'uncategorized',
		], $config );

		return $this;
	}

	/**
	 * Get a template part configuration.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name The template part identifier.
	 *
	 * @return array|null
	 */
	public function getPart( string $name ): ?array
	{
		return $this->templateParts[ $name ] ?? null;
	}

	/**
	 * Get all template parts.
	 *
	 * @since 1.0.0
	 *
	 * @return Collection
	 */
	public function allParts(): Collection
	{
		return collect( $this->templateParts );
	}

	/**
	 * Get template parts by area.
	 *
	 * @since 1.0.0
	 *
	 * @param string $area The area name (header, footer, sidebar, etc.).
	 *
	 * @return Collection
	 */
	public function getPartsByArea( string $area ): Collection
	{
		return $this->allParts()->filter( fn ( $part ) => ( $part['area'] ?? '' ) === $area );
	}

	/**
	 * Get the default template name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function getDefault(): string
	{
		return config( 'artisanpack.visual-editor.editor.default_template', 'default' );
	}

	/**
	 * Register the default templates.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function registerDefaults(): void
	{
		// Default templates
		$this->register( 'default', [
			'name'        => __( 'Default' ),
			'description' => __( 'Standard page template with header and footer' ),
			'icon'        => 'fas.file',
			'parts'       => [ 'header', 'footer' ],
			'supports'    => [ 'title', 'editor', 'thumbnail' ],
		] );

		$this->register( 'full-width', [
			'name'        => __( 'Full Width' ),
			'description' => __( 'Full width page without content container' ),
			'icon'        => 'fas.up-right-and-down-left-from-center',
			'parts'       => [ 'header', 'footer' ],
			'supports'    => [ 'title', 'editor', 'thumbnail' ],
		] );

		$this->register( 'landing', [
			'name'        => __( 'Landing Page' ),
			'description' => __( 'Minimal header, focused on conversions' ),
			'icon'        => 'fas.rocket',
			'parts'       => [ 'header-minimal', 'footer-minimal' ],
			'supports'    => [ 'title', 'editor', 'thumbnail' ],
		] );

		$this->register( 'blank', [
			'name'        => __( 'Blank Canvas' ),
			'description' => __( 'No header or footer, complete freedom' ),
			'icon'        => 'fas.clone',
			'parts'       => [],
			'supports'    => [ 'title', 'editor', 'thumbnail' ],
		] );

		$this->register( 'sidebar-left', [
			'name'        => __( 'Sidebar Left' ),
			'description' => __( 'Page with left sidebar' ),
			'icon'        => 'fas.columns',
			'parts'       => [ 'header', 'sidebar', 'footer' ],
			'supports'    => [ 'title', 'editor', 'thumbnail' ],
		] );

		$this->register( 'sidebar-right', [
			'name'        => __( 'Sidebar Right' ),
			'description' => __( 'Page with right sidebar' ),
			'icon'        => 'fas.columns',
			'parts'       => [ 'header', 'sidebar', 'footer' ],
			'supports'    => [ 'title', 'editor', 'thumbnail' ],
		] );

		// Default template parts
		$this->registerPart( 'header', [
			'name'        => __( 'Header' ),
			'description' => __( 'Site header with navigation' ),
			'area'        => 'header',
		] );

		$this->registerPart( 'header-minimal', [
			'name'        => __( 'Minimal Header' ),
			'description' => __( 'Simple header with logo only' ),
			'area'        => 'header',
		] );

		$this->registerPart( 'footer', [
			'name'        => __( 'Footer' ),
			'description' => __( 'Site footer with links and copyright' ),
			'area'        => 'footer',
		] );

		$this->registerPart( 'footer-minimal', [
			'name'        => __( 'Minimal Footer' ),
			'description' => __( 'Simple footer with copyright only' ),
			'area'        => 'footer',
		] );

		$this->registerPart( 'sidebar', [
			'name'        => __( 'Sidebar' ),
			'description' => __( 'Widget area for sidebar content' ),
			'area'        => 'sidebar',
		] );
	}
}
