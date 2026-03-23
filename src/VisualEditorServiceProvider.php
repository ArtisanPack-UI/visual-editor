<?php

/**
 * Visual Editor Service Provider.
 *
 * Bootstraps the Visual Editor package by registering configuration,
 * views, and Blade components.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor;

use ArtisanPackUI\VisualEditor\Blocks\BlockDiscoveryService;
use ArtisanPackUI\VisualEditor\Blocks\BlockRegistry;
use ArtisanPackUI\VisualEditor\Blocks\BlockTransformService;
use ArtisanPackUI\VisualEditor\Console\Commands\BlockCacheCommand;
use ArtisanPackUI\VisualEditor\Console\Commands\BlockClearCommand;
use ArtisanPackUI\VisualEditor\Http\Middleware\CheckGateIfDefined;
use ArtisanPackUI\VisualEditor\Inspector\BlockMetadataService;
use ArtisanPackUI\VisualEditor\Inspector\SupportsPanelRegistry;
use ArtisanPackUI\VisualEditor\Livewire\SiteEditor\GlobalStylesPage;
use ArtisanPackUI\VisualEditor\Livewire\SiteEditor\HubPage;
use ArtisanPackUI\VisualEditor\Livewire\SiteEditor\PatternListingPage;
use ArtisanPackUI\VisualEditor\Livewire\SiteEditor\TemplateListingPage;
use ArtisanPackUI\VisualEditor\Livewire\SiteEditor\TemplatePartListingPage;
use ArtisanPackUI\VisualEditor\Models\Pattern;
use ArtisanPackUI\VisualEditor\Models\Template;
use ArtisanPackUI\VisualEditor\Models\TemplatePart;
use ArtisanPackUI\VisualEditor\Policies\PatternSiteEditorPolicy;
use ArtisanPackUI\VisualEditor\Policies\TemplatePartSiteEditorPolicy;
use ArtisanPackUI\VisualEditor\Policies\TemplateSiteEditorPolicy;
use ArtisanPackUI\VisualEditor\Rendering\BlockRenderer;
use ArtisanPackUI\VisualEditor\Services\ColorPaletteManager;
use ArtisanPackUI\VisualEditor\Services\GlobalStylesCompiler;
use ArtisanPackUI\VisualEditor\Services\GlobalStylesRepository;
use ArtisanPackUI\VisualEditor\Services\OEmbedService;
use ArtisanPackUI\VisualEditor\Services\SpacingScaleManager;
use ArtisanPackUI\VisualEditor\Services\StyleCascadeResolver;
use ArtisanPackUI\VisualEditor\Services\TemplateAssignmentManager;
use ArtisanPackUI\VisualEditor\Services\TemplateManager;
use ArtisanPackUI\VisualEditor\Services\TemplatePartManager;
use ArtisanPackUI\VisualEditor\Services\TemplatePresetManager;
use ArtisanPackUI\VisualEditor\Services\TypographyPresetsManager;
use ArtisanPackUI\VisualEditor\View\Components;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Service provider for the Visual Editor package.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.0.0
 */
class VisualEditorServiceProvider extends ServiceProvider
{
	/**
	 * Blade components to register.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, class-string>
	 */
	protected array $bladeComponents = [
		// Phase 1: Primitive Controls
		'unit-control'             => Components\UnitControl::class,
		'box-control'              => Components\BoxControl::class,
		'alignment-control'        => Components\AlignmentControl::class,
		'block-alignment-control'  => Components\BlockAlignmentControl::class,
		'color-system'             => Components\ColorSystem::class,
		'link-control'             => Components\LinkControl::class,
		'link-popover'             => Components\LinkPopover::class,
		'range-control'            => Components\RangeControl::class,
		'responsive-range-control' => Components\ResponsiveRangeControl::class,
		'angle-control'            => Components\AngleControl::class,
		'font-size-picker'         => Components\FontSizePicker::class,
		'border-control'           => Components\BorderControl::class,

		'color-picker'       => Components\ColorPicker::class,

		// Phase 2: Editor Infrastructure
		'animate-presence'   => Components\AnimatePresence::class,
		'aria-live-region'   => Components\AriaLiveRegion::class,
		'focus-trap'         => Components\FocusTrap::class,
		'keyboard-shortcuts' => Components\KeyboardShortcuts::class,
		'popover'            => Components\Popover::class,
		'panel'              => Components\Panel::class,
		'panel-header'       => Components\PanelHeader::class,
		'panel-body'         => Components\PanelBody::class,
		'panel-row'          => Components\PanelRow::class,
		'toolbar'            => Components\Toolbar::class,
		'toolbar-group'      => Components\ToolbarGroup::class,
		'toolbar-button'     => Components\ToolbarButton::class,
		'toolbar-dropdown'   => Components\ToolbarDropdown::class,
		'slot-container'     => Components\SlotContainer::class,
		'fill'               => Components\Fill::class,
		'drop-zone'          => Components\DropZone::class,
		'selection-manager'  => Components\SelectionManager::class,

		// Phase 3: Editor Shell Assembly
		'editor-state'            => Components\EditorState::class,
		'canvas-empty-state'      => Components\CanvasEmptyState::class,
		'insertion-point'         => Components\InsertionPoint::class,
		'device-preview'          => Components\DevicePreview::class,
		'editor-canvas'           => Components\EditorCanvas::class,
		'block-inserter-category' => Components\BlockInserterCategory::class,
		'block-inserter-item'     => Components\BlockInserterItem::class,
		'block-inserter'          => Components\BlockInserter::class,
		'device-preview-buttons'  => Components\DevicePreviewButtons::class,
		'status-bar'              => Components\StatusBar::class,
		'block-toolbar'           => Components\BlockToolbar::class,
		'top-toolbar'             => Components\TopToolbar::class,
		'editor-sidebar'          => Components\EditorSidebar::class,
		'editor-layout'           => Components\EditorLayout::class,

		// Phase 4: Left Sidebar, Patterns, Layers & Hooks
		'left-sidebar'            => Components\LeftSidebar::class,
		'pattern-browser'         => Components\PatternBrowser::class,
		'pattern-modal'           => Components\PatternModal::class,
		'layer-panel'             => Components\LayerPanel::class,
		'document-status'         => Components\DocumentStatus::class,
		'document-title'          => Components\DocumentTitle::class,
		'document-excerpt'        => Components\DocumentExcerpt::class,
		'document-permalink'      => Components\DocumentPermalink::class,
		'document-featured-image' => Components\DocumentFeaturedImage::class,
		'document-taxonomies'     => Components\DocumentTaxonomies::class,

		// Phase 5: Inspector & Block Enhancements
		'inspector-field'          => Components\InspectorField::class,
		'slash-command-inserter'   => Components\SlashCommandInserter::class,

		// Phase 6: Inspector Controls & New Support Controls
		'inspector-controls' => Components\InspectorControls::class,
		'inspector-section'  => Components\InspectorSection::class,
		'shadow-control'     => Components\ShadowControl::class,
		'background-control' => Components\BackgroundControl::class,

		// Phase 7: Inner Blocks
		'inner-blocks' => Components\InnerBlocks::class,

		// Phase 8: Block Placeholder
		'block-placeholder' => Components\BlockPlaceholder::class,

		// Phase 10: Template Editor
		'template-editor'          => Components\TemplateEditor::class,
		'template-part-slot'       => Components\TemplatePartSlot::class,
		'template-switcher'        => Components\TemplateSwitcher::class,
		'template-structure-panel' => Components\TemplateStructurePanel::class,

		// Phase 7: Global Styles
		'color-palette-editor'        => Components\ColorPaletteEditor::class,
		'typography-presets-editor'   => Components\TypographyPresetsEditor::class,
		'spacing-scale-editor'        => Components\SpacingScaleEditor::class,
		'style-source-indicator'      => Components\StyleSourceIndicator::class,
		'global-styles-state'         => Components\GlobalStylesState::class,
		'template-parts-manager'      => Components\TemplatePartsManager::class,

		// Phase 9: Editor Assembly
		'icon'   => Components\Icon::class,
		'editor' => Components\Editor::class,

		// Site Editor
		'site-editor-layout' => Components\SiteEditorLayout::class,
		'listing-table'      => Components\ListingTable::class,
		'listing-grid'       => Components\ListingGrid::class,
	];

	/**
	 * Register any application services.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void
	{
		$this->mergeConfigFrom(
			__DIR__ . '/../config/visual-editor.php',
			'artisanpack-visual-editor-temp',
		);

		$this->app['router']->aliasMiddleware( 've.gate', CheckGateIfDefined::class );

		$this->app->singleton( 'visual-editor', function () {
			return new VisualEditor();
		} );

		$this->app->singleton( 'visual-editor.blocks', function () {
			return new BlockRegistry();
		} );

		$this->app->singleton( BlockTransformService::class, function ( $app ) {
			return new BlockTransformService( $app->make( 'visual-editor.blocks' ) );
		} );

		$this->app->singleton( SupportsPanelRegistry::class, function () {
			return new SupportsPanelRegistry();
		} );

		$this->app->singleton( BlockMetadataService::class, function ( $app ) {
			return new BlockMetadataService(
				$app->make( 'visual-editor.blocks' ),
				$app->make( SupportsPanelRegistry::class ),
			);
		} );

		$this->app->singleton( BlockDiscoveryService::class, function () {
			return new BlockDiscoveryService();
		} );

		$this->app->singleton( OEmbedService::class, function () {
			return new OEmbedService();
		} );

		$this->app->singleton( 'visual-editor.templates', function () {
			return new TemplateManager();
		} );

		$this->app->singleton( TemplateManager::class, function ( $app ) {
			return $app->make( 'visual-editor.templates' );
		} );

		$this->app->singleton( 'visual-editor.template-parts', function () {
			return new TemplatePartManager();
		} );

		$this->app->singleton( TemplatePartManager::class, function ( $app ) {
			return $app->make( 'visual-editor.template-parts' );
		} );

		$this->app->singleton( 'visual-editor.template-assignments', function () {
			return new TemplateAssignmentManager();
		} );

		$this->app->singleton( TemplateAssignmentManager::class, function ( $app ) {
			return $app->make( 'visual-editor.template-assignments' );
		} );

		$this->app->singleton( 'visual-editor.template-presets', function () {
			return new TemplatePresetManager();
		} );

		$this->app->singleton( TemplatePresetManager::class, function ( $app ) {
			return $app->make( 'visual-editor.template-presets' );
		} );

		// Config is read lazily (on first resolve, after boot merges config).
		$this->app->singleton( 'visual-editor.color-palette', function () {
			$configPalette = config( 'artisanpack.visual-editor.color_palette', [] );

			return new ColorPaletteManager( $configPalette );
		} );

		$this->app->singleton( ColorPaletteManager::class, function ( $app ) {
			return $app->make( 'visual-editor.color-palette' );
		} );

		$this->app->singleton( 'visual-editor.typography-presets', function () {
			$configTypography = config( 'artisanpack.visual-editor.typography_presets', [] );

			return new TypographyPresetsManager( $configTypography );
		} );

		$this->app->singleton( TypographyPresetsManager::class, function ( $app ) {
			return $app->make( 'visual-editor.typography-presets' );
		} );

		$this->app->singleton( 'visual-editor.spacing-scale', function () {
			$configSpacing = config( 'artisanpack.visual-editor.spacing_scale', [] );

			return new SpacingScaleManager( $configSpacing );
		} );

		$this->app->singleton( SpacingScaleManager::class, function ( $app ) {
			return $app->make( 'visual-editor.spacing-scale' );
		} );

		$this->app->singleton( BlockRenderer::class, function ( $app ) {
			return new BlockRenderer(
				$app->make( 'visual-editor.blocks' ),
				(string) config( 'artisanpack.visual-editor.rendering.class_prefix', 've-block-' ),
				(int) config( 'artisanpack.visual-editor.rendering.max_depth', BlockRenderer::DEFAULT_MAX_DEPTH ),
			);
		} );

		$this->app->singleton( 'visual-editor.global-styles', function ( $app ) {
			return new GlobalStylesCompiler(
				$app->make( 'visual-editor.color-palette' ),
				$app->make( 'visual-editor.typography-presets' ),
				$app->make( 'visual-editor.spacing-scale' ),
				(array) config( 'artisanpack.visual-editor.global_styles', [] ),
			);
		} );

		$this->app->singleton( GlobalStylesCompiler::class, function ( $app ) {
			return $app->make( 'visual-editor.global-styles' );
		} );

		$this->app->singleton( 'visual-editor.style-cascade', function ( $app ) {
			return new StyleCascadeResolver(
				$app->make( 'visual-editor.color-palette' ),
				$app->make( 'visual-editor.typography-presets' ),
				$app->make( 'visual-editor.spacing-scale' ),
			);
		} );

		$this->app->singleton( StyleCascadeResolver::class, function ( $app ) {
			return $app->make( 'visual-editor.style-cascade' );
		} );

		$this->app->singleton( 'visual-editor.global-styles-repository', function ( $app ) {
			return new GlobalStylesRepository(
				$app->make( 'visual-editor.color-palette' ),
				$app->make( 'visual-editor.typography-presets' ),
				$app->make( 'visual-editor.spacing-scale' ),
			);
		} );

		$this->app->singleton( GlobalStylesRepository::class, function ( $app ) {
			return $app->make( 'visual-editor.global-styles-repository' );
		} );
	}

	/**
	 * Bootstrap any application services.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function boot(): void
	{
		$this->mergeConfiguration();
		$this->publishConfiguration();
		$this->registerTranslations();
		$this->registerViews();
		$this->registerBladeComponents();
		$this->registerLivewireNamespace();
		$this->registerMigrations();
		$this->registerPermissions();
		$this->registerPolicies();
		$this->registerRoutes();
		$this->registerCoreBlocks();
		$this->registerDefaultTemplates();
		$this->registerDefaultTemplateParts();
		$this->registerDefaultPresets();
		$this->registerConsoleCommands();
		$this->publishBlockViews();
		$this->registerAdminMenu();
	}

	/**
	 * Register site editor permissions with the CMS framework.
	 *
	 * Permissions are only registered when the cms-framework's
	 * `ap_register_permission` function is available.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerPermissions(): void
	{
		if ( ! function_exists( 'ap_register_permission' ) ) {
			return;
		}

		$permissions = [
			'visual-editor.access-site-editor'    => [
				'name'        => __( 'visual-editor::ve.permission_access_site_editor' ),
				'description' => __( 'visual-editor::ve.permission_access_site_editor_desc' ),
			],
			'visual-editor.manage-styles'         => [
				'name'        => __( 'visual-editor::ve.permission_manage_styles' ),
				'description' => __( 'visual-editor::ve.permission_manage_styles_desc' ),
			],
			'visual-editor.manage-templates'      => [
				'name'        => __( 'visual-editor::ve.permission_manage_templates' ),
				'description' => __( 'visual-editor::ve.permission_manage_templates_desc' ),
			],
			'visual-editor.manage-parts'          => [
				'name'        => __( 'visual-editor::ve.permission_manage_parts' ),
				'description' => __( 'visual-editor::ve.permission_manage_parts_desc' ),
			],
			'visual-editor.manage-patterns'       => [
				'name'        => __( 'visual-editor::ve.permission_manage_patterns' ),
				'description' => __( 'visual-editor::ve.permission_manage_patterns_desc' ),
			],
			'visual-editor.manage-template-styles' => [
				'name'        => __( 'visual-editor::ve.permission_manage_template_styles' ),
				'description' => __( 'visual-editor::ve.permission_manage_template_styles_desc' ),
			],
			'visual-editor.lock-content'          => [
				'name'        => __( 'visual-editor::ve.permission_lock_content' ),
				'description' => __( 'visual-editor::ve.permission_lock_content_desc' ),
			],
		];

		foreach ( $permissions as $slug => $meta ) {
			ap_register_permission( $slug, $meta['name'], $meta['description'] );
		}
	}

	/**
	 * Register authorization policies for site editor models.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerPolicies(): void
	{
		Gate::policy( Template::class, TemplateSiteEditorPolicy::class );
		Gate::policy( TemplatePart::class, TemplatePartSiteEditorPolicy::class );
		Gate::policy( Pattern::class, PatternSiteEditorPolicy::class );
	}

	/**
	 * Register the site editor as an admin page with the CMS framework.
	 *
	 * Only registers when the cms-framework's `apAddAdminPage`
	 * function is available.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerAdminMenu(): void
	{
		if ( ! function_exists( 'apAddAdminPage' ) ) {
			return;
		}

		$prefix = (string) config( 'artisanpack.visual-editor.site_editor.route_prefix', 'site-editor' );

		apAddAdminPage(
			title: __( 'visual-editor::ve.site_editor' ),
			slug: 'site-editor',
			sectionSlug: 'appearance',
			options: [
				'capability' => 'visual-editor.access-site-editor',
				'action'     => fn () => redirect( url( $prefix ) ),
			],
		);
	}

	/**
	 * Register package routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerRoutes(): void
	{
		$this->loadRoutesFrom( __DIR__ . '/../routes/api.php' );
		$this->loadRoutesFrom( __DIR__ . '/../routes/web.php' );
	}

	/**
	 * Merges the package's default configuration with the user's customizations.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function mergeConfiguration(): void
	{
		$packageDefaults = config( 'artisanpack-visual-editor-temp', [] );
		$userConfig      = config( 'artisanpack.visual-editor', [] );
		$mergedConfig    = array_replace_recursive( $packageDefaults, $userConfig );

		config( [ 'artisanpack.visual-editor' => $mergedConfig ] );
		config( [ 'artisanpack-visual-editor-temp' => [] ] );
	}

	/**
	 * Publish the configuration file.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function publishConfiguration(): void
	{
		if ( $this->app->runningInConsole() ) {
			$this->publishes( [
				__DIR__ . '/../config/visual-editor.php' => config_path( 'artisanpack/visual-editor.php' ),
			], 'artisanpack-visual-editor-config' );
		}
	}

	/**
	 * Register the package translations.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerTranslations(): void
	{
		$this->loadTranslationsFrom( __DIR__ . '/../resources/lang', 'visual-editor' );

		if ( $this->app->runningInConsole() ) {
			$this->publishes( [
				__DIR__ . '/../resources/lang' => $this->app->langPath( 'vendor/visual-editor' ),
			], 'visual-editor-lang' );
		}
	}

	/**
	 * Register the package views.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerViews(): void
	{
		$this->loadViewsFrom( __DIR__ . '/../resources/views', 'visual-editor' );

		if ( $this->app->runningInConsole() ) {
			$this->publishes( [
				__DIR__ . '/../resources/views' => resource_path( 'views/vendor/visual-editor' ),
			], 'visual-editor-views' );
		}
	}

	/**
	 * Register the Livewire namespace for package components.
	 *
	 * Block-specific Livewire components are now auto-registered
	 * by `BlockRegistry::register()` for dynamic blocks.
	 *
	 * @since 2.1.0
	 *
	 * @return void
	 */
	protected function registerLivewireNamespace(): void
	{
		if ( ! $this->app->bound( 'livewire' ) ) {
			return;
		}

		Livewire::addNamespace(
			namespace: 'visual-editor',
			viewPath: __DIR__ . '/../resources/views/livewire',
			classNamespace: 'ArtisanPackUI\\VisualEditor\\Livewire',
		);

		Livewire::component( 'site-editor.hub-page', HubPage::class );
		Livewire::component( 'site-editor.global-styles-page', GlobalStylesPage::class );
		Livewire::component( 'site-editor.template-listing-page', TemplateListingPage::class );
		Livewire::component( 'site-editor.template-part-listing-page', TemplatePartListingPage::class );
		Livewire::component( 'site-editor.pattern-listing-page', PatternListingPage::class );
		Livewire::component( 'template-parts-crud', \ArtisanPackUI\VisualEditor\Livewire\TemplatePartsCrud::class );
	}

	/**
	 * Register and publish database migrations.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	protected function registerMigrations(): void
	{
		$this->loadMigrationsFrom( __DIR__ . '/../database/migrations' );

		if ( $this->app->runningInConsole() ) {
			$this->publishes( [
				__DIR__ . '/../database/migrations' => database_path( 'migrations' ),
			], 'visual-editor-migrations' );
		}
	}

	/**
	 * Register all Blade components with the ve- prefix.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerBladeComponents(): void
	{
		$prefix = 've';

		foreach ( $this->bladeComponents as $alias => $class ) {
			Blade::component( $class, $prefix . '-' . $alias );
		}
	}

	/**
	 * Register all core block types with the block registry.
	 *
	 * Core blocks are discovered via `BlockDiscoveryService` and
	 * registered with `BlockRegistry`. View namespaces and Livewire
	 * components are automatically set up during registration.
	 *
	 * After core blocks are registered, the `ap.visualEditor.blocksInit`
	 * action fires so third-party packages can register their own blocks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerCoreBlocks(): void
	{
		$registry   = $this->app->make( 'visual-editor.blocks' );
		$discovery  = $this->app->make( BlockDiscoveryService::class );
		$coreConfig = config( 'artisanpack.visual-editor.blocks.core', [] );
		$disabled   = config( 'artisanpack.visual-editor.blocks.disabled', [] );

		$blocks = $discovery->loadManifest() ?? $discovery->discover();

		foreach ( $blocks as $entry ) {
			$type  = $entry['type'];
			$class = $entry['class'];

			if ( false === ( $coreConfig[ $type ] ?? true ) ) {
				continue;
			}

			if ( in_array( $type, $disabled, true ) ) {
				continue;
			}

			if ( class_exists( $class ) ) {
				$registry->register( new $class() );
			}
		}

		veDoAction( 'ap.visualEditor.blocksInit' );
	}

	/**
	 * Register the default built-in templates.
	 *
	 * Registers blank, full-width, sidebar-left, and sidebar-right
	 * templates with the template manager. Third-party packages can
	 * register additional templates via the `ap.visualEditor.templatesInit` action.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerDefaultTemplates(): void
	{
		$manager = $this->app->make( 'visual-editor.templates' );

		$manager->register( 'blank', [
			'name'                  => __( 'Blank' ),
			'description'           => __( 'A blank template with no predefined content.' ),
			'type'                  => 'page',
			'content'               => [],
			'is_custom'             => false,
			'content_area_settings' => [
				'max_width' => 'full',
				'padding'   => 'none',
			],
		] );

		$manager->register( 'full-width', [
			'name'                  => __( 'Full Width' ),
			'description'           => __( 'A full-width template with no sidebar.' ),
			'type'                  => 'page',
			'content'               => [],
			'is_custom'             => false,
			'content_area_settings' => [
				'max_width' => 'full',
				'padding'   => 'large',
			],
		] );

		$manager->register( 'sidebar-left', [
			'name'                  => __( 'Sidebar Left' ),
			'description'           => __( 'A layout with a sidebar on the left and content on the right.' ),
			'type'                  => 'page',
			'content'               => [],
			'is_custom'             => false,
			'content_area_settings' => [
				'max_width'     => 'container',
				'padding'       => 'large',
				'layout'        => 'sidebar-left',
				'sidebar_width' => '300px',
			],
		] );

		$manager->register( 'sidebar-right', [
			'name'                  => __( 'Sidebar Right' ),
			'description'           => __( 'A layout with content on the left and a sidebar on the right.' ),
			'type'                  => 'page',
			'content'               => [],
			'is_custom'             => false,
			'content_area_settings' => [
				'max_width'     => 'container',
				'padding'       => 'large',
				'layout'        => 'sidebar-right',
				'sidebar_width' => '300px',
			],
		] );

		veDoAction( 'ap.visualEditor.templatesInit' );
	}

	/**
	 * Register the default built-in template parts.
	 *
	 * Registers header, footer, and sidebar template parts with the
	 * template part manager. Third-party packages can register additional
	 * template parts via the `ap.visualEditor.templatePartsInit` action.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerDefaultTemplateParts(): void
	{
		$manager         = $this->app->make( 'visual-editor.template-parts' );
		$configuredAreas = (array) config( 'artisanpack.visual-editor.template_parts.areas', [ 'header', 'footer', 'sidebar', 'custom' ] );

		$defaults = [
			'header'  => [
				'name'        => __( 'Header' ),
				'description' => __( 'A default header area for site-wide navigation and branding.' ),
			],
			'footer'  => [
				'name'        => __( 'Footer' ),
				'description' => __( 'A default footer area for site-wide links and information.' ),
			],
			'sidebar' => [
				'name'        => __( 'Sidebar' ),
				'description' => __( 'A default sidebar area for supplementary content and widgets.' ),
			],
		];

		foreach ( $defaults as $area => $config ) {
			if ( in_array( $area, $configuredAreas, true ) ) {
				$manager->register( $area, array_merge( $config, [
					'area'      => $area,
					'content'   => [],
					'is_custom' => false,
				] ) );
			}
		}

		veDoAction( 'ap.visualEditor.templatePartsInit' );
	}

	/**
	 * Register the default built-in template presets.
	 *
	 * Registers starter presets organized by category that users can
	 * select when creating a new template. Third-party packages can
	 * register additional presets via the `ap.visualEditor.templatePresetsInit` action.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerDefaultPresets(): void
	{
		$manager = $this->app->make( 'visual-editor.template-presets' );

		$manager->register( 'blog-post', [
			'name'                  => __( 'Blog Post' ),
			'description'           => __( 'A standard blog post layout with a featured image, title, and content area.' ),
			'category'              => 'blog',
			'type'                  => 'page',
			'content'               => [
				[
					'type'       => 'image',
					'attributes' => [ 'className' => 've-featured-image' ],
				],
				[
					'type'       => 'heading',
					'attributes' => [ 'level' => 1 ],
				],
				[
					'type'       => 'paragraph',
					'attributes' => [],
				],
			],
			'content_area_settings' => [
				'max_width' => 'container',
				'padding'   => 'large',
			],
			'template_parts'        => [
				'header' => 'header',
				'footer' => 'footer',
			],
		] );

		$manager->register( 'blog-sidebar', [
			'name'                  => __( 'Blog Post with Sidebar' ),
			'description'           => __( 'A blog post layout with a right sidebar for widgets and navigation.' ),
			'category'              => 'blog',
			'type'                  => 'page',
			'content'               => [
				[
					'type'       => 'heading',
					'attributes' => [ 'level' => 1 ],
				],
				[
					'type'       => 'paragraph',
					'attributes' => [],
				],
			],
			'content_area_settings' => [
				'max_width'     => 'container',
				'padding'       => 'large',
				'layout'        => 'sidebar-right',
				'sidebar_width' => '300px',
			],
			'template_parts'        => [
				'header'  => 'header',
				'footer'  => 'footer',
				'sidebar' => 'sidebar',
			],
		] );

		$manager->register( 'landing-page', [
			'name'                  => __( 'Landing Page' ),
			'description'           => __( 'A full-width landing page layout for marketing and promotional content.' ),
			'category'              => 'marketing',
			'type'                  => 'page',
			'content'               => [
				[
					'type'       => 'heading',
					'attributes' => [ 'level' => 1, 'className' => 've-hero-heading' ],
				],
				[
					'type'       => 'paragraph',
					'attributes' => [ 'className' => 've-hero-text' ],
				],
				[
					'type'       => 'columns',
					'attributes' => [ 'columns' => 3 ],
				],
			],
			'content_area_settings' => [
				'max_width' => 'full',
				'padding'   => 'none',
			],
			'template_parts'        => [
				'header' => 'header',
				'footer' => 'footer',
			],
		] );

		$manager->register( 'single-page', [
			'name'                  => __( 'Single Page' ),
			'description'           => __( 'A simple single page layout with title and content.' ),
			'category'              => 'general',
			'type'                  => 'page',
			'content'               => [
				[
					'type'       => 'heading',
					'attributes' => [ 'level' => 1 ],
				],
				[
					'type'       => 'paragraph',
					'attributes' => [],
				],
			],
			'content_area_settings' => [
				'max_width' => 'container',
				'padding'   => 'large',
			],
			'template_parts'        => [
				'header' => 'header',
				'footer' => 'footer',
			],
		] );

		$manager->register( 'archive', [
			'name'                  => __( 'Archive' ),
			'description'           => __( 'A template for displaying lists of posts or content items.' ),
			'category'              => 'blog',
			'type'                  => 'page',
			'content'               => [
				[
					'type'       => 'heading',
					'attributes' => [ 'level' => 1 ],
				],
				[
					'type'       => 'paragraph',
					'attributes' => [ 'className' => 've-archive-description' ],
				],
			],
			'content_area_settings' => [
				'max_width' => 'container',
				'padding'   => 'large',
			],
			'template_parts'        => [
				'header' => 'header',
				'footer' => 'footer',
			],
		] );

		$manager->register( 'portfolio', [
			'name'                  => __( 'Portfolio' ),
			'description'           => __( 'A portfolio template for showcasing work with a grid layout.' ),
			'category'              => 'portfolio',
			'type'                  => 'page',
			'content'               => [
				[
					'type'       => 'heading',
					'attributes' => [ 'level' => 1 ],
				],
				[
					'type'       => 'grid',
					'attributes' => [ 'columns' => 3 ],
				],
			],
			'content_area_settings' => [
				'max_width' => 'container',
				'padding'   => 'large',
			],
			'template_parts'        => [
				'header' => 'header',
				'footer' => 'footer',
			],
		] );

		veDoAction( 'ap.visualEditor.templatePresetsInit' );
	}

	/**
	 * Register console commands for the package.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	protected function registerConsoleCommands(): void
	{
		if ( $this->app->runningInConsole() ) {
			$this->commands( [
				BlockCacheCommand::class,
				BlockClearCommand::class,
			] );
		}
	}

	/**
	 * Publish co-located block views for customization.
	 *
	 * Scans all core block directories and publishes their view
	 * files to resources/views/vendor/visual-editor/blocks/{type}/.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	protected function publishBlockViews(): void
	{
		if ( ! $this->app->runningInConsole() ) {
			return;
		}

		$publishMap = $this->scanBlockViewDirectories();

		if ( ! empty( $publishMap ) ) {
			$this->publishes( $publishMap, 'visual-editor-block-views' );
		}
	}

	/**
	 * Scan core block directories for co-located view folders.
	 *
	 * Returns a map of source view directory => publish destination.
	 *
	 * @since 2.1.0
	 *
	 * @return array<string, string>
	 */
	protected function scanBlockViewDirectories(): array
	{
		$blocksDir  = __DIR__ . '/Blocks';
		$categories = [ 'Text', 'Media', 'Layout', 'Interactive', 'Embed', 'Dynamic' ];
		$publishMap = [];

		foreach ( $categories as $category ) {
			$categoryDir = $blocksDir . '/' . $category;

			if ( ! is_dir( $categoryDir ) ) {
				continue;
			}

			$entries = scandir( $categoryDir );

			if ( false === $entries ) {
				continue;
			}

			foreach ( $entries as $entry ) {
				if ( '.' === $entry || '..' === $entry ) {
					continue;
				}

				$viewsDir = $categoryDir . '/' . $entry . '/views';

				if ( ! is_dir( $viewsDir ) ) {
					continue;
				}

				$blockJsonPath = $categoryDir . '/' . $entry . '/block.json';
				$type          = null;

				if ( file_exists( $blockJsonPath ) ) {
					$json = json_decode( (string) file_get_contents( $blockJsonPath ), true );
					$type = $json['type'] ?? null;
				}

				if ( null === $type ) {
					$type = strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $entry ) ?? $entry );
				}

				$publishMap[ $viewsDir ] = resource_path( 'views/vendor/visual-editor/blocks/' . $type );
			}
		}

		return $publishMap;
	}
}
