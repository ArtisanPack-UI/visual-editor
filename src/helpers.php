<?php

/**
 * Visual Editor helper functions.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Contracts\BlockInterface;
use ArtisanPackUI\VisualEditor\Rendering\BlockRenderer;
use ArtisanPackUI\VisualEditor\Services\ContentResolver;
use ArtisanPackUI\VisualEditor\Services\SiteIdentityResolver;
use ArtisanPackUI\VisualEditor\VisualEditor;

if ( ! function_exists( 'veDoAction' ) ) {
	/**
	 * Fire a hook action if the hooks package is available.
	 *
	 * Centralizes the function_exists guard so it only needs
	 * to be maintained in one place.
	 *
	 * @since 2.1.0
	 *
	 * @param string $name    The action hook name.
	 * @param mixed  ...$args Arguments to pass to the action callbacks.
	 *
	 * @return void
	 */
	function veDoAction( string $name, mixed ...$args ): void
	{
		if ( function_exists( 'doAction' ) ) {
			doAction( $name, ...$args );
		}
	}
}

if ( ! function_exists( 'veApplyFilters' ) ) {
	/**
	 * Apply a filter hook if the hooks package is available.
	 *
	 * Returns the value unmodified when the hooks package is
	 * not installed.
	 *
	 * @since 2.1.0
	 *
	 * @param string $name    The filter hook name.
	 * @param mixed  $value   The value to filter.
	 * @param mixed  ...$args Additional arguments for filter callbacks.
	 *
	 * @return mixed The filtered value.
	 */
	function veApplyFilters( string $name, mixed $value, mixed ...$args ): mixed
	{
		if ( function_exists( 'applyFilters' ) ) {
			return applyFilters( $name, $value, ...$args );
		}

		return $value;
	}
}

if ( ! function_exists( 'veGateMiddleware' ) ) {
	/**
	 * Build a capability middleware array for a route.
	 *
	 * Returns `['ve.gate:{ability}']` when an ability is configured.
	 * Returns an empty array when no ability is set, allowing the
	 * route to remain accessible with just the base middleware.
	 *
	 * The `ve.gate` middleware (CheckGateIfDefined) only enforces
	 * the authorization check when the gate has been registered.
	 * When no gate is registered (e.g. cms-framework not installed),
	 * the request passes through for graceful degradation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $ability The gate ability to check.
	 *
	 * @return array<int, string>
	 */
	function veGateMiddleware( string $ability ): array
	{
		if ( '' === $ability ) {
			return [];
		}

		return [ 've.gate:' . $ability ];
	}
}

if ( ! function_exists( 'visualEditor' ) ) {
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

if ( ! function_exists( 'veRegisterBlock' ) ) {
	/**
	 * Register a block type with the block registry.
	 *
	 * @since 1.0.0
	 *
	 * @param BlockInterface $block The block instance to register.
	 *
	 * @return void
	 */
	function veRegisterBlock( BlockInterface $block ): void
	{
		app( 'visual-editor.blocks' )->register( $block );
	}
}

if ( ! function_exists( 'veBlockExists' ) ) {
	/**
	 * Check if a block type is registered.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type The block type identifier.
	 *
	 * @return bool
	 */
	function veBlockExists( string $type ): bool
	{
		return app( 'visual-editor.blocks' )->exists( $type );
	}
}

if ( ! function_exists( 'veGetBlock' ) ) {
	/**
	 * Get a registered block by type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type The block type identifier.
	 *
	 * @return BlockInterface|null
	 */
	function veGetBlock( string $type ): ?BlockInterface
	{
		return app( 'visual-editor.blocks' )->get( $type );
	}
}

if ( ! function_exists( 'veSanitizeCssColor' ) ) {
	/**
	 * Sanitize a CSS color value.
	 *
	 * Accepts hex (#fff, #ffffff), rgb/rgba/hsl/hsla functions,
	 * named CSS colors, and special keywords like currentColor, transparent,
	 * inherit, initial, unset.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $value   The color value to sanitize.
	 * @param string|null $default The default value if sanitization fails.
	 *
	 * @return string|null The sanitized color or default.
	 */
	function veSanitizeCssColor( ?string $value, ?string $default = null ): ?string
	{
		if ( null === $value || '' === $value ) {
			return $default;
		}

		$value   = trim( $value );
		$pattern = '/^(#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})|(?:rgb|rgba|hsl|hsla)\([^)]+\)|[a-zA-Z\-]+)$/';

		return preg_match( $pattern, $value ) ? $value : $default;
	}
}

if ( ! function_exists( 'veSanitizeCssDimension' ) ) {
	/**
	 * Sanitize a CSS dimension value (e.g., "10px", "1.5rem", "50%", "0").
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $value   The dimension value to sanitize.
	 * @param string      $default The default value if sanitization fails.
	 *
	 * @return string The sanitized dimension or default.
	 */
	function veSanitizeCssDimension( ?string $value, string $default = '0' ): string
	{
		if ( null === $value || '' === $value ) {
			return $default;
		}

		$value   = trim( $value );
		$pattern = '/^-?\d+(\.\d+)?(px|em|rem|%|vh|vw|vmin|vmax|ch|ex|cm|mm|in|pt|pc)?$/';

		if ( preg_match( $pattern, $value ) ) {
			return $value;
		}

		if ( in_array( $value, [ 'auto', 'inherit', 'initial', 'unset', '0' ], true ) ) {
			return $value;
		}

		return $default;
	}
}

if ( ! function_exists( 'veSanitizeCssNumber' ) ) {
	/**
	 * Sanitize a numeric CSS value (no unit attached).
	 *
	 * Use this instead of veSanitizeCssDimension when the unit is appended
	 * separately (e.g., border width/radius where unit comes from a different field).
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $value   The numeric value to sanitize.
	 * @param string      $default The default value if sanitization fails.
	 *
	 * @return string The sanitized numeric string or default.
	 */
	function veSanitizeCssNumber( ?string $value, string $default = '0' ): string
	{
		if ( null === $value || '' === $value ) {
			return $default;
		}

		$value = trim( $value );

		if ( preg_match( '/^-?\d+(\.\d+)?$/', $value ) ) {
			return $value;
		}

		return $default;
	}
}

if ( ! function_exists( 'veSanitizeCssUnit' ) ) {
	/**
	 * Sanitize a CSS unit string, falling back to a default if empty or invalid.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $value   The unit to sanitize.
	 * @param string      $default The default unit.
	 *
	 * @return string The sanitized unit.
	 */
	function veSanitizeCssUnit( ?string $value, string $default = 'px' ): string
	{
		$allowed = [ 'px', 'em', 'rem', '%', 'vh', 'vw', 'vmin', 'vmax', 'ch', 'ex', 'cm', 'mm', 'in', 'pt', 'pc' ];

		if ( null === $value || '' === $value || ! in_array( $value, $allowed, true ) ) {
			return $default;
		}

		return $value;
	}
}

if ( ! function_exists( 'veSanitizeBorderStyle' ) ) {
	/**
	 * Sanitize a CSS border-style value.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $value   The border style to sanitize.
	 * @param string      $default The default border style.
	 *
	 * @return string The sanitized border style.
	 */
	function veSanitizeBorderStyle( ?string $value, string $default = 'solid' ): string
	{
		$allowed = [ 'none', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge', 'inset', 'outset', 'hidden' ];

		if ( null === $value || '' === $value || ! in_array( $value, $allowed, true ) ) {
			return $default;
		}

		return $value;
	}
}

if ( ! function_exists( 'veRenderBlocks' ) ) {
	/**
	 * Render an array of block data into front-end HTML.
	 *
	 * Convenience wrapper around the BlockRenderer service for
	 * rendering blocks outside of an Eloquent model context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $blocks The block data array.
	 *
	 * @return string The rendered HTML string.
	 */
	function veRenderBlocks( array $blocks ): string
	{
		return app( BlockRenderer::class )->render( $blocks );
	}
}

if ( ! function_exists( 'veRegisterTemplate' ) ) {
	/**
	 * Register a template definition with the template manager.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $slug   The unique template slug.
	 * @param array<string, mixed> $config The template configuration.
	 *
	 * @return void
	 */
	function veRegisterTemplate( string $slug, array $config ): void
	{
		app( 'visual-editor.templates' )->register( $slug, $config );
	}
}

if ( ! function_exists( 'veGetTemplate' ) ) {
	/**
	 * Resolve a template by slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The template slug to resolve.
	 *
	 * @return array<string, mixed>|ArtisanPackUI\VisualEditor\Models\Template|null
	 */
	function veGetTemplate( string $slug ): ArtisanPackUI\VisualEditor\Models\Template|array|null
	{
		return app( 'visual-editor.templates' )->resolve( $slug );
	}
}

if ( ! function_exists( 'veGetTemplatesForType' ) ) {
	/**
	 * Get all active templates for a content type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $contentType The content type to filter by.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	function veGetTemplatesForType( string $contentType ): array
	{
		return app( 'visual-editor.templates' )->forContentType( $contentType );
	}
}

if ( ! function_exists( 'veTemplateExists' ) ) {
	/**
	 * Check if a template exists by slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The template slug to check.
	 *
	 * @return bool
	 */
	function veTemplateExists( string $slug ): bool
	{
		return app( 'visual-editor.templates' )->exists( $slug );
	}
}

if ( ! function_exists( 'veRegisterTemplatePart' ) ) {
	/**
	 * Register a template part definition with the template part manager.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $slug   The unique template part slug.
	 * @param array<string, mixed> $config The template part configuration.
	 *
	 * @return void
	 */
	function veRegisterTemplatePart( string $slug, array $config ): void
	{
		app( 'visual-editor.template-parts' )->register( $slug, $config );
	}
}

if ( ! function_exists( 'veGetTemplatePart' ) ) {
	/**
	 * Resolve a template part by slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The template part slug to resolve.
	 *
	 * @return array<string, mixed>|ArtisanPackUI\VisualEditor\Models\TemplatePart|null
	 */
	function veGetTemplatePart( string $slug ): ArtisanPackUI\VisualEditor\Models\TemplatePart|array|null
	{
		return app( 'visual-editor.template-parts' )->resolve( $slug );
	}
}

if ( ! function_exists( 'veGetTemplatePartsForArea' ) ) {
	/**
	 * Get all active template parts for a specific area.
	 *
	 * @since 1.0.0
	 *
	 * @param string $area The area to filter by (header, footer, sidebar, custom).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	function veGetTemplatePartsForArea( string $area ): array
	{
		return app( 'visual-editor.template-parts' )->forArea( $area );
	}
}

if ( ! function_exists( 'veTemplatePartExists' ) ) {
	/**
	 * Check if a template part exists by slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The template part slug to check.
	 *
	 * @return bool
	 */
	function veTemplatePartExists( string $slug ): bool
	{
		return app( 'visual-editor.template-parts' )->exists( $slug );
	}
}

if ( ! function_exists( 'veAssignTemplate' ) ) {
	/**
	 * Assign a default template to a content type.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $contentType The content type identifier.
	 * @param int      $templateId  The template ID to assign.
	 * @param int|null $userId      The user making the assignment.
	 *
	 * @return ArtisanPackUI\VisualEditor\Models\TemplateAssignment
	 */
	function veAssignTemplate( string $contentType, int $templateId, ?int $userId = null ): ArtisanPackUI\VisualEditor\Models\TemplateAssignment
	{
		return app( 'visual-editor.template-assignments' )->assign( $contentType, $templateId, $userId );
	}
}

if ( ! function_exists( 'veUnassignTemplate' ) ) {
	/**
	 * Remove the default template assignment for a content type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $contentType The content type to unassign.
	 *
	 * @return bool
	 */
	function veUnassignTemplate( string $contentType ): bool
	{
		return app( 'visual-editor.template-assignments' )->unassign( $contentType );
	}
}

if ( ! function_exists( 'veGetDefaultTemplateFor' ) ) {
	/**
	 * Get the default template for a content type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $contentType The content type to look up.
	 *
	 * @return ArtisanPackUI\VisualEditor\Models\Template|null
	 */
	function veGetDefaultTemplateFor( string $contentType ): ?ArtisanPackUI\VisualEditor\Models\Template
	{
		return app( 'visual-editor.template-assignments' )->defaultFor( $contentType );
	}
}

if ( ! function_exists( 'veResolveTemplate' ) ) {
	/**
	 * Resolve the template for content using the hierarchy.
	 *
	 * Resolution order: page-specific -> content type default -> site default.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $contentType    The content type.
	 * @param int|null $pageTemplateId Optional page-specific template ID override.
	 *
	 * @return array<string, mixed>|ArtisanPackUI\VisualEditor\Models\Template|null
	 */
	function veResolveTemplate( string $contentType, ?int $pageTemplateId = null ): ArtisanPackUI\VisualEditor\Models\Template|array|null
	{
		return app( 'visual-editor.template-assignments' )->resolveTemplate( $contentType, $pageTemplateId );
	}
}

if ( ! function_exists( 'veValidateTemplateAssignment' ) ) {
	/**
	 * Validate that a template can be assigned to a content type.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $templateId  The template ID to validate.
	 * @param string $contentType The target content type.
	 *
	 * @return bool
	 */
	function veValidateTemplateAssignment( int $templateId, string $contentType ): bool
	{
		return app( 'visual-editor.template-assignments' )->validateAssignment( $templateId, $contentType );
	}
}

if ( ! function_exists( 'veBulkAssignTemplate' ) ) {
	/**
	 * Bulk assign a template to multiple content entities.
	 *
	 * @since 1.0.0
	 *
	 * @param int             $templateId  The template ID to assign.
	 * @param string          $modelClass  The fully qualified model class name.
	 * @param string          $contentType The content type for validation.
	 * @param array<int, int> $entityIds   The entity IDs to update.
	 *
	 * @return int The number of entities updated.
	 */
	function veBulkAssignTemplate( int $templateId, string $modelClass, string $contentType, array $entityIds ): int
	{
		return app( 'visual-editor.template-assignments' )->bulkAssign( $templateId, $modelClass, $contentType, $entityIds );
	}
}

if ( ! function_exists( 'veRegisterPreset' ) ) {
	/**
	 * Register a template preset definition with the preset manager.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $slug   The unique preset slug.
	 * @param array<string, mixed> $config The preset configuration.
	 *
	 * @return void
	 */
	function veRegisterPreset( string $slug, array $config ): void
	{
		app( 'visual-editor.template-presets' )->register( $slug, $config );
	}
}

if ( ! function_exists( 'veGetPreset' ) ) {
	/**
	 * Resolve a template preset by slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The preset slug to resolve.
	 *
	 * @return array<string, mixed>|ArtisanPackUI\VisualEditor\Models\TemplatePreset|null
	 */
	function veGetPreset( string $slug ): ArtisanPackUI\VisualEditor\Models\TemplatePreset|array|null
	{
		return app( 'visual-editor.template-presets' )->resolve( $slug );
	}
}

if ( ! function_exists( 'veGetPresetsForCategory' ) ) {
	/**
	 * Get all presets for a specific category.
	 *
	 * @since 1.0.0
	 *
	 * @param string $category The category to filter by.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	function veGetPresetsForCategory( string $category ): array
	{
		return app( 'visual-editor.template-presets' )->forCategory( $category );
	}
}

if ( ! function_exists( 'vePresetExists' ) ) {
	/**
	 * Check if a template preset exists by slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The preset slug to check.
	 *
	 * @return bool
	 */
	function vePresetExists( string $slug ): bool
	{
		return app( 'visual-editor.template-presets' )->exists( $slug );
	}
}

if ( ! function_exists( 'veCreateTemplateFromPreset' ) ) {
	/**
	 * Create a template from a preset.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $presetSlug The preset slug to use.
	 * @param string               $slug       The slug for the new template.
	 * @param string               $name       The name for the new template.
	 * @param array<string, mixed> $overrides  Optional template data overrides.
	 *
	 * @return ArtisanPackUI\VisualEditor\Models\Template|null
	 */
	function veCreateTemplateFromPreset( string $presetSlug, string $slug, string $name, array $overrides = [] ): ?ArtisanPackUI\VisualEditor\Models\Template
	{
		return app( 'visual-editor.template-presets' )->createTemplateFromPreset( $presetSlug, $slug, $name, $overrides );
	}
}

if ( ! function_exists( 'veGetPresetCategories' ) ) {
	/**
	 * Get all available preset categories.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	function veGetPresetCategories(): array
	{
		return app( 'visual-editor.template-presets' )->categories();
	}
}

if ( ! function_exists( 'veGetTemplateVariations' ) ) {
	/**
	 * Get all variations of a specific template.
	 *
	 * @since 1.0.0
	 *
	 * @param int $parentId The parent template ID.
	 *
	 * @return array<int, ArtisanPackUI\VisualEditor\Models\Template>
	 */
	function veGetTemplateVariations( int $parentId ): array
	{
		return app( 'visual-editor.templates' )->variationsOf( $parentId );
	}
}

if ( ! function_exists( 'veCreateTemplateVariation' ) ) {
	/**
	 * Create a variation of an existing template.
	 *
	 * @since 1.0.0
	 *
	 * @param ArtisanPackUI\VisualEditor\Models\Template $template  The base template.
	 * @param string                                      $slug      The slug for the variation.
	 * @param string                                      $name      The name for the variation.
	 * @param array<string, mixed>                        $overrides Attribute overrides.
	 *
	 * @return ArtisanPackUI\VisualEditor\Models\Template
	 */
	function veCreateTemplateVariation( ArtisanPackUI\VisualEditor\Models\Template $template, string $slug, string $name, array $overrides = [] ): ArtisanPackUI\VisualEditor\Models\Template
	{
		return app( 'visual-editor.templates' )->createVariation( $template, $slug, $name, $overrides );
	}
}

if ( ! function_exists( 'veGetTypographyPresets' ) ) {
	/**
	 * Get the current typography presets (font families and elements).
	 *
	 * @since 1.0.0
	 *
	 * @return array{fontFamilies: array<string, string>, elements: array<string, array<string, string>>}
	 */
	function veGetTypographyPresets(): array
	{
		return app( 'visual-editor.typography-presets' )->toStoreFormat();
	}
}

if ( ! function_exists( 'veGetFontFamily' ) ) {
	/**
	 * Get a font family value by slot.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slot The font family slot (heading, body, mono).
	 *
	 * @return string|null
	 */
	function veGetFontFamily( string $slot ): ?string
	{
		return app( 'visual-editor.typography-presets' )->getFontFamily( $slot );
	}
}

if ( ! function_exists( 'veGetTypographyElement' ) ) {
	/**
	 * Get typography styles for a specific element.
	 *
	 * @since 1.0.0
	 *
	 * @param string $element The element key (h1-h6, body, small, caption, blockquote, code).
	 *
	 * @return array<string, string>|null
	 */
	function veGetTypographyElement( string $element ): ?array
	{
		return app( 'visual-editor.typography-presets' )->getElement( $element );
	}
}

if ( ! function_exists( 'veGenerateTypographyCss' ) ) {
	/**
	 * Generate the full CSS :root block for typography custom properties.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	function veGenerateTypographyCss(): string
	{
		return app( 'visual-editor.typography-presets' )->generateCssBlock();
	}
}

if ( ! function_exists( 'veRegisterGoogleFont' ) ) {
	/**
	 * Register a Google Font for loading.
	 *
	 * @since 1.0.0
	 *
	 * @param string             $family   The font family name.
	 * @param array<int, string> $weights  Font weights to load.
	 * @param array<int, string> $styles   Font styles to load.
	 * @param string             $category Font category: 'all', 'heading', or 'body'.
	 *
	 * @return void
	 */
	function veRegisterGoogleFont( string $family, array $weights = [ '400', '700' ], array $styles = [ 'normal' ], string $category = 'all' ): void
	{
		app( 'visual-editor.typography-presets' )->registerGoogleFont( $family, $weights, $styles, $category );
	}
}

if ( ! function_exists( 'veRegisterFont' ) ) {
	/**
	 * Register a font in the typography collection.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug     Unique slug for the font.
	 * @param string $name     Display name.
	 * @param string $family   CSS font-family stack.
	 * @param string $category Font category: 'all', 'heading', or 'body'.
	 * @param string $source   Font source: 'system', 'custom', or 'google'.
	 *
	 * @return void
	 */
	function veRegisterFont( string $slug, string $name, string $family, string $category = 'all', string $source = 'custom' ): void
	{
		app( 'visual-editor.typography-presets' )->registerFont( $slug, $name, $family, $category, $source );
	}
}

if ( ! function_exists( 'veGetAvailableFonts' ) ) {
	/**
	 * Get all available fonts, optionally filtered by category.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $category Optional category filter: 'heading', 'body', or null for all.
	 *
	 * @return array<string, array{name: string, family: string, category: string, source: string}>
	 */
	function veGetAvailableFonts( ?string $category = null ): array
	{
		return app( 'visual-editor.typography-presets' )->getAvailableFonts( $category );
	}
}

if ( ! function_exists( 'veGetFontOptions' ) ) {
	/**
	 * Get available fonts as options for a dropdown (family => name).
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $category Optional category filter.
	 *
	 * @return array<string, string>
	 */
	function veGetFontOptions( ?string $category = null ): array
	{
		return app( 'visual-editor.typography-presets' )->getFontOptions( $category );
	}
}

if ( ! function_exists( 'veRegisterCustomFont' ) ) {
	/**
	 * Register a custom font for @font-face generation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $family   The font family name.
	 * @param string $src      The font source URL.
	 * @param string $weight   The font weight (default: '400').
	 * @param string $style    The font style (default: 'normal').
	 * @param string $category Font category: 'all', 'heading', or 'body'.
	 *
	 * @return void
	 */
	function veRegisterCustomFont( string $family, string $src, string $weight = '400', string $style = 'normal', string $category = 'all' ): void
	{
		app( 'visual-editor.typography-presets' )->registerCustomFont( $family, $src, $weight, $style, $category );
	}
}

if ( ! function_exists( 'veGetSpacingScale' ) ) {
	/**
	 * Get the current spacing scale (all steps including custom).
	 *
	 * @since 1.0.0
	 *
	 * @return array{scale: array<int, array{name: string, slug: string, value: string}>, blockGap: string, customSteps: array<int, array{name: string, slug: string, value: string}>}
	 */
	function veGetSpacingScale(): array
	{
		return app( 'visual-editor.spacing-scale' )->toStoreFormat();
	}
}

if ( ! function_exists( 'veGetSpacingStep' ) ) {
	/**
	 * Get a spacing step value by slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The spacing step slug (e.g. 'md', 'lg').
	 *
	 * @return string|null The CSS value or null.
	 */
	function veGetSpacingStep( string $slug ): ?string
	{
		return app( 'visual-editor.spacing-scale' )->getStepValue( $slug );
	}
}

if ( ! function_exists( 'veGetBlockGap' ) ) {
	/**
	 * Get the current block gap CSS value.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null The CSS value or null.
	 */
	function veGetBlockGap(): ?string
	{
		return app( 'visual-editor.spacing-scale' )->getBlockGapValue();
	}
}

if ( ! function_exists( 'veGenerateSpacingCss' ) ) {
	/**
	 * Generate the full CSS :root block for spacing custom properties.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	function veGenerateSpacingCss(): string
	{
		return app( 'visual-editor.spacing-scale' )->generateCssBlock();
	}
}

if ( ! function_exists( 'veCompileGlobalStyles' ) ) {
	/**
	 * Compile all global styles (colors, typography, spacing) into a CSS string.
	 *
	 * @since 1.0.0
	 *
	 * @return string The compiled CSS.
	 */
	function veCompileGlobalStyles(): string
	{
		return app( 'visual-editor.global-styles' )->compile();
	}
}

if ( ! function_exists( 'veGlobalStylesInline' ) ) {
	/**
	 * Get the compiled global styles as an inline <style> tag.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $forEditor When true, forces :root selector regardless of config.
	 *
	 * @return string The HTML <style> element.
	 */
	function veGlobalStylesInline( bool $forEditor = false ): string
	{
		return app( 'visual-editor.global-styles' )->toInlineStyle( $forEditor );
	}
}

if ( ! function_exists( 'veGlobalStylesCached' ) ) {
	/**
	 * Get cached compiled global styles CSS.
	 *
	 * @since 1.0.0
	 *
	 * @return string The compiled CSS string.
	 */
	function veGlobalStylesCached(): string
	{
		return app( 'visual-editor.global-styles' )->getCached();
	}
}

if ( ! function_exists( 'veInvalidateGlobalStylesCache' ) ) {
	/**
	 * Invalidate the global styles CSS cache.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	function veInvalidateGlobalStylesCache(): void
	{
		app( 'visual-editor.global-styles' )->invalidateCache();
	}
}

if ( ! function_exists( 'veCompileScopedStyles' ) ) {
	/**
	 * Compile scoped CSS for a template with overrides.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $slug      The template slug.
	 * @param array<string, mixed> $overrides The style overrides.
	 *
	 * @return string The scoped CSS string.
	 */
	function veCompileScopedStyles( string $slug, array $overrides ): string
	{
		return app( 'visual-editor.global-styles' )->compileScoped( $slug, $overrides );
	}
}

if ( ! function_exists( 'veGlobalStylesOutput' ) ) {
	/**
	 * Output global styles based on the configured output mode.
	 *
	 * @since 1.0.0
	 *
	 * @return string The output result (inline style tag or file path).
	 */
	function veGlobalStylesOutput(): string
	{
		return app( 'visual-editor.global-styles' )->output();
	}
}

// ─── Style Cascade Helpers ──────────────────────────────────────────

if ( ! function_exists( 'veResolveStyleCascade' ) ) {
	/**
	 * Resolve the computed styles for a block through the cascade.
	 *
	 * Merges global ← template ← block styles so the most specific wins.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $blockStyles    The block-level style overrides.
	 * @param array<string, mixed> $templateStyles The template-level style overrides.
	 *
	 * @return array<string, mixed> The fully resolved computed styles.
	 */
	function veResolveStyleCascade( array $blockStyles = [], array $templateStyles = [] ): array
	{
		return app( 'visual-editor.style-cascade' )->resolve( $blockStyles, $templateStyles );
	}
}

if ( ! function_exists( 'veResolveInheritedStyles' ) ) {
	/**
	 * Resolve the inherited styles (global + template) without block overrides.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $templateStyles The template-level style overrides.
	 *
	 * @return array<string, mixed> The inherited styles.
	 */
	function veResolveInheritedStyles( array $templateStyles = [] ): array
	{
		return app( 'visual-editor.style-cascade' )->resolveInherited( $templateStyles );
	}
}

if ( ! function_exists( 'veGetStyleSource' ) ) {
	/**
	 * Determine which cascade level provides a specific style property.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $path           Dot-notation path to the property.
	 * @param array<string, mixed> $blockStyles    The block-level style overrides.
	 * @param array<string, mixed> $templateStyles The template-level style overrides.
	 *
	 * @return string The source level: 'global', 'template', or 'block'.
	 */
	function veGetStyleSource( string $path, array $blockStyles = [], array $templateStyles = [] ): string
	{
		return app( 'visual-editor.style-cascade' )->getSource( $path, $blockStyles, $templateStyles );
	}
}

if ( ! function_exists( 'veGetStyleSourceMap' ) ) {
	/**
	 * Get a source map for all properties in the resolved styles.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $blockStyles    The block-level style overrides.
	 * @param array<string, mixed> $templateStyles The template-level style overrides.
	 *
	 * @return array<string, string> Map of property path => source level.
	 */
	function veGetStyleSourceMap( array $blockStyles = [], array $templateStyles = [] ): array
	{
		return app( 'visual-editor.style-cascade' )->getSourceMap( $blockStyles, $templateStyles );
	}
}

if ( ! function_exists( 'veGetInheritedStyleValue' ) ) {
	/**
	 * Get the value a property would have if the block override were removed.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $path           Dot-notation path to the property.
	 * @param array<string, mixed> $templateStyles The template-level style overrides.
	 *
	 * @return mixed The inherited value, or null if not set.
	 */
	function veGetInheritedStyleValue( string $path, array $templateStyles = [] ): mixed
	{
		return app( 'visual-editor.style-cascade' )->getInheritedValue( $path, $templateStyles );
	}
}

if ( ! function_exists( 'veGetGlobalStyles' ) ) {
	/**
	 * Get the current global styles from all managers.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> The global styles array.
	 */
	function veGetGlobalStyles(): array
	{
		return app( 'visual-editor.style-cascade' )->getGlobalStyles();
	}
}

if ( ! function_exists( 'veSanitizeHtmlId' ) ) {
	/**
	 * Sanitize a value for use as an HTML id attribute.
	 *
	 * Strips characters not valid in HTML IDs, ensures it doesn't start
	 * with a digit.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $value The ID value to sanitize.
	 *
	 * @return string|null The sanitized ID or null if empty after sanitization.
	 */
	function veSanitizeHtmlId( ?string $value ): ?string
	{
		if ( null === $value || '' === $value ) {
			return null;
		}

		$sanitized = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $value );

		if ( '' === $sanitized ) {
			return null;
		}

		if ( preg_match( '/^\d/', $sanitized ) ) {
			$sanitized = 'id-' . $sanitized;
		}

		return $sanitized;
	}
}

if ( ! function_exists( 'veGetSiteTitle' ) ) {
	/**
	 * Get the site title from the site identity resolver.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	function veGetSiteTitle(): string
	{
		return app( SiteIdentityResolver::class )->getTitle();
	}
}

if ( ! function_exists( 'veGetSiteTagline' ) ) {
	/**
	 * Get the site tagline from the site identity resolver.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	function veGetSiteTagline(): string
	{
		return app( SiteIdentityResolver::class )->getTagline();
	}
}

if ( ! function_exists( 'veGetSiteLogoUrl' ) ) {
	/**
	 * Get the site logo URL from the site identity resolver.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	function veGetSiteLogoUrl(): string
	{
		return app( SiteIdentityResolver::class )->getLogoUrl();
	}
}

if ( ! function_exists( 'veGetSiteLogoAlt' ) ) {
	/**
	 * Get the site logo alt text from the site identity resolver.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	function veGetSiteLogoAlt(): string
	{
		return app( SiteIdentityResolver::class )->getLogoAlt();
	}
}

if ( ! function_exists( 'veGetSiteHomeUrl' ) ) {
	/**
	 * Get the site home URL from the site identity resolver.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	function veGetSiteHomeUrl(): string
	{
		return app( SiteIdentityResolver::class )->getHomeUrl();
	}
}

if ( ! function_exists( 'veGetContentTitle' ) ) {
	/**
	 * Get the content title from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return string
	 */
	function veGetContentTitle( array $context = [] ): string
	{
		return app( ContentResolver::class )->getTitle( $context );
	}
}

if ( ! function_exists( 'veGetContentBody' ) ) {
	/**
	 * Get the content body from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return string
	 */
	function veGetContentBody( array $context = [] ): string
	{
		return app( ContentResolver::class )->getBody( $context );
	}
}

if ( ! function_exists( 'veGetContentExcerpt' ) ) {
	/**
	 * Get the content excerpt from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return string
	 */
	function veGetContentExcerpt( array $context = [] ): string
	{
		return app( ContentResolver::class )->getExcerpt( $context );
	}
}

if ( ! function_exists( 'veGetContentDate' ) ) {
	/**
	 * Get the content publish date from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return string The date as an ISO 8601 string, or empty string.
	 */
	function veGetContentDate( array $context = [] ): string
	{
		return app( ContentResolver::class )->getDate( $context );
	}
}

if ( ! function_exists( 'veGetContentModifiedDate' ) ) {
	/**
	 * Get the content modified date from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return string The date as an ISO 8601 string, or empty string.
	 */
	function veGetContentModifiedDate( array $context = [] ): string
	{
		return app( ContentResolver::class )->getModifiedDate( $context );
	}
}

if ( ! function_exists( 'veGetContentFeaturedImageUrl' ) ) {
	/**
	 * Get the content featured image URL from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return string
	 */
	function veGetContentFeaturedImageUrl( array $context = [] ): string
	{
		return app( ContentResolver::class )->getFeaturedImageUrl( $context );
	}
}

if ( ! function_exists( 'veGetContentFeaturedImageAlt' ) ) {
	/**
	 * Get the content featured image alt text from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return string
	 */
	function veGetContentFeaturedImageAlt( array $context = [] ): string
	{
		return app( ContentResolver::class )->getFeaturedImageAlt( $context );
	}
}

if ( ! function_exists( 'veGetContentPermalink' ) ) {
	/**
	 * Get the content permalink from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return string
	 */
	function veGetContentPermalink( array $context = [] ): string
	{
		return app( ContentResolver::class )->getPermalink( $context );
	}
}

if ( ! function_exists( 'veGetContentAuthorName' ) ) {
	/**
	 * Get the content author name from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return string
	 */
	function veGetContentAuthorName( array $context = [] ): string
	{
		return app( ContentResolver::class )->getAuthorName( $context );
	}
}

if ( ! function_exists( 'veGetContentAuthorBio' ) ) {
	/**
	 * Get the content author biography from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return string
	 */
	function veGetContentAuthorBio( array $context = [] ): string
	{
		return app( ContentResolver::class )->getAuthorBio( $context );
	}
}

if ( ! function_exists( 'veGetContentAuthorAvatarUrl' ) ) {
	/**
	 * Get the content author avatar URL from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return string
	 */
	function veGetContentAuthorAvatarUrl( array $context = [] ): string
	{
		return app( ContentResolver::class )->getAuthorAvatarUrl( $context );
	}
}

if ( ! function_exists( 'veGetContentAuthorUrl' ) ) {
	/**
	 * Get the content author archive/profile URL from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return string
	 */
	function veGetContentAuthorUrl( array $context = [] ): string
	{
		return app( ContentResolver::class )->getAuthorUrl( $context );
	}
}

if ( ! function_exists( 'veGetContentTerms' ) ) {
	/**
	 * Get the content taxonomy terms from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param string               $taxonomy The taxonomy slug (e.g. 'category', 'tag').
	 * @param array<string, mixed> $context  Optional context (e.g. from query loop).
	 *
	 * @return array<int, array{name: string, url: string, slug: string}>
	 */
	function veGetContentTerms( string $taxonomy, array $context = [] ): array
	{
		return app( ContentResolver::class )->getTerms( $taxonomy, $context );
	}
}

if ( ! function_exists( 'veGetContentCommentsCount' ) ) {
	/**
	 * Get the content comments count from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return int
	 */
	function veGetContentCommentsCount( array $context = [] ): int
	{
		return app( ContentResolver::class )->getCommentsCount( $context );
	}
}

if ( ! function_exists( 'veGetContentCommentsUrl' ) ) {
	/**
	 * Get the URL to the content comments section from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return string
	 */
	function veGetContentCommentsUrl( array $context = [] ): string
	{
		return app( ContentResolver::class )->getCommentsUrl( $context );
	}
}

if ( ! function_exists( 'veGetContentWordCount' ) ) {
	/**
	 * Get the content word count from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return int
	 */
	function veGetContentWordCount( array $context = [] ): int
	{
		return app( ContentResolver::class )->getWordCount( $context );
	}
}

if ( ! function_exists( 'veGetContentPreviousPostUrl' ) ) {
	/**
	 * Get the previous post URL from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. taxonomy scope).
	 *
	 * @return string
	 */
	function veGetContentPreviousPostUrl( array $context = [] ): string
	{
		return app( ContentResolver::class )->getPreviousPostUrl( $context );
	}
}

if ( ! function_exists( 'veGetContentPreviousPostTitle' ) ) {
	/**
	 * Get the previous post title from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. taxonomy scope).
	 *
	 * @return string
	 */
	function veGetContentPreviousPostTitle( array $context = [] ): string
	{
		return app( ContentResolver::class )->getPreviousPostTitle( $context );
	}
}

if ( ! function_exists( 'veGetContentNextPostUrl' ) ) {
	/**
	 * Get the next post URL from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. taxonomy scope).
	 *
	 * @return string
	 */
	function veGetContentNextPostUrl( array $context = [] ): string
	{
		return app( ContentResolver::class )->getNextPostUrl( $context );
	}
}

if ( ! function_exists( 'veGetContentNextPostTitle' ) ) {
	/**
	 * Get the next post title from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. taxonomy scope).
	 *
	 * @return string
	 */
	function veGetContentNextPostTitle( array $context = [] ): string
	{
		return app( ContentResolver::class )->getNextPostTitle( $context );
	}
}

if ( ! function_exists( 'veGetContentComments' ) ) {
	/**
	 * Get the list of comments from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	function veGetContentComments( array $context = [] ): array
	{
		return app( ContentResolver::class )->getComments( $context );
	}
}

if ( ! function_exists( 'veGetContentCommentAuthorName' ) ) {
	/**
	 * Get the comment author name from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Comment context with comment data.
	 *
	 * @return string
	 */
	function veGetContentCommentAuthorName( array $context = [] ): string
	{
		return app( ContentResolver::class )->getCommentAuthorName( $context );
	}
}

if ( ! function_exists( 'veGetContentCommentAuthorAvatarUrl' ) ) {
	/**
	 * Get the comment author avatar URL from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Comment context with comment data.
	 *
	 * @return string
	 */
	function veGetContentCommentAuthorAvatarUrl( array $context = [] ): string
	{
		return app( ContentResolver::class )->getCommentAuthorAvatarUrl( $context );
	}
}

if ( ! function_exists( 'veGetContentCommentAuthorUrl' ) ) {
	/**
	 * Get the comment author URL from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Comment context with comment data.
	 *
	 * @return string
	 */
	function veGetContentCommentAuthorUrl( array $context = [] ): string
	{
		return app( ContentResolver::class )->getCommentAuthorUrl( $context );
	}
}

if ( ! function_exists( 'veGetContentCommentContent' ) ) {
	/**
	 * Get the comment content from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Comment context with comment data.
	 *
	 * @return string
	 */
	function veGetContentCommentContent( array $context = [] ): string
	{
		return app( ContentResolver::class )->getCommentContent( $context );
	}
}

if ( ! function_exists( 'veGetContentCommentDate' ) ) {
	/**
	 * Get the comment date from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Comment context with comment data.
	 *
	 * @return string
	 */
	function veGetContentCommentDate( array $context = [] ): string
	{
		return app( ContentResolver::class )->getCommentDate( $context );
	}
}

if ( ! function_exists( 'veGetContentCommentReplyUrl' ) ) {
	/**
	 * Get the comment reply URL from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Comment context with comment data.
	 *
	 * @return string
	 */
	function veGetContentCommentReplyUrl( array $context = [] ): string
	{
		return app( ContentResolver::class )->getCommentReplyUrl( $context );
	}
}

if ( ! function_exists( 'veGetContentCommentEditUrl' ) ) {
	/**
	 * Get the comment edit URL from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Comment context with comment data.
	 *
	 * @return string
	 */
	function veGetContentCommentEditUrl( array $context = [] ): string
	{
		return app( ContentResolver::class )->getCommentEditUrl( $context );
	}
}

if ( ! function_exists( 'veGetContentCommentsPagination' ) ) {
	/**
	 * Get comments pagination data from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context.
	 *
	 * @return array{totalPages: int, currentPage: int, previousUrl: string, nextUrl: string, perPage: int}
	 */
	function veGetContentCommentsPagination( array $context = [] ): array
	{
		return app( ContentResolver::class )->getCommentsPagination( $context );
	}
}

// ──────────────────────────────────────────────────────────
// Query loop helpers
// ──────────────────────────────────────────────────────────

if ( ! function_exists( 'veGetQueryResults' ) ) {
	/**
	 * Get query results from the content resolver.
	 *
	 * Returns an array with 'items' and 'total' keys. Applications
	 * register a filter on 've.query.results' to execute the actual
	 * query against their models.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context     Optional context (e.g. page context for inherit).
	 * @param array<string, mixed> $queryParams Query parameters (queryType, perPage, orderBy, etc.).
	 *
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	function veGetQueryResults( array $context = [], array $queryParams = [] ): array
	{
		return app( ContentResolver::class )->getQueryResults( $context, $queryParams );
	}
}

if ( ! function_exists( 'veGetQueryPagination' ) ) {
	/**
	 * Get query pagination data from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context.
	 *
	 * @return array{totalPages: int, currentPage: int, previousUrl: string, nextUrl: string}
	 */
	function veGetQueryPagination( array $context = [] ): array
	{
		return app( ContentResolver::class )->getQueryPagination( $context );
	}
}

if ( ! function_exists( 'veGetQueryTitle' ) ) {
	/**
	 * Get the contextual query title from the content resolver.
	 *
	 * Returns a title appropriate for the current query context,
	 * such as "Search results for: X" or "Category: Technology".
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context    Optional context.
	 * @param string               $prefixType The prefix type (archive, search).
	 * @param bool                 $showPrefix Whether to include the prefix.
	 *
	 * @return string
	 */
	function veGetQueryTitle( array $context = [], string $prefixType = 'archive', bool $showPrefix = true ): string
	{
		return app( ContentResolver::class )->getQueryTitle( $context, $prefixType, $showPrefix );
	}
}

if ( ! function_exists( 'veGetQueryTotal' ) ) {
	/**
	 * Get the total number of query results from the content resolver.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context.
	 *
	 * @return int
	 */
	function veGetQueryTotal( array $context = []): int
	{
		return app( ContentResolver::class )->getQueryTotal( $context );
	}
}
