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
