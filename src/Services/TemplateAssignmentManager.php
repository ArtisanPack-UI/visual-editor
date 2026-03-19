<?php

/**
 * Template Assignment Manager Service.
 *
 * Manages template assignments to content types and provides
 * template hierarchy resolution for the visual editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Services
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services;

use ArtisanPackUI\VisualEditor\Models\Template;
use ArtisanPackUI\VisualEditor\Models\TemplateAssignment;
use InvalidArgumentException;

/**
 * Service for assigning templates to content types and resolving
 * the template hierarchy.
 *
 * Resolution order:
 * 1. Page-specific template (passed as parameter)
 * 2. Content type default (from assignments table)
 * 3. Site default template (from config)
 * 4. Fallback (first active template or null)
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Services
 *
 * @since      1.0.0
 */
class TemplateAssignmentManager
{
	/**
	 * Assign a default template to a content type.
	 *
	 * Creates or updates the assignment for the given content type.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $contentType The content type identifier.
	 * @param int      $templateId  The template ID to assign.
	 * @param int|null $userId      The user making the assignment.
	 *
	 * @throws InvalidArgumentException If the template does not exist.
	 *
	 * @return TemplateAssignment
	 */
	public function assign( string $contentType, int $templateId, ?int $userId = null ): TemplateAssignment
	{
		$template = Template::find( $templateId );

		if ( null === $template ) {
			throw new InvalidArgumentException(
				__( 'Template with ID :id does not exist.', [ 'id' => $templateId ] ),
			);
		}

		$assignment = TemplateAssignment::updateOrCreate(
			[ 'content_type' => $contentType ],
			[
				'template_id' => $templateId,
				'user_id'     => $userId,
			],
		);

		veDoAction( 'ap.visualEditor.templateAssigned', $assignment, $template, $contentType );

		return $assignment;
	}

	/**
	 * Remove the default template assignment for a content type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $contentType The content type to unassign.
	 *
	 * @return bool True if an assignment was removed, false if none existed.
	 */
	public function unassign( string $contentType ): bool
	{
		$assignment = TemplateAssignment::where( 'content_type', $contentType )->first();

		if ( null === $assignment ) {
			return false;
		}

		veDoAction( 'ap.visualEditor.templateUnassigned', $assignment, $contentType );

		return (bool) $assignment->delete();
	}

	/**
	 * Get the default template for a content type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $contentType The content type to look up.
	 *
	 * @return Template|null The assigned template or null.
	 */
	public function defaultFor( string $contentType ): ?Template
	{
		$assignment = TemplateAssignment::where( 'content_type', $contentType )
			->with( 'template' )
			->first();

		if ( null === $assignment || null === $assignment->template ) {
			return null;
		}

		return $assignment->template;
	}

	/**
	 * Resolve the template for content using the hierarchy.
	 *
	 * Resolution order:
	 * 1. Page-specific template ID (if provided and valid)
	 * 2. Content type default assignment
	 * 3. Site default template (from config)
	 * 4. Null if nothing found
	 *
	 * @since 1.0.0
	 *
	 * @param string   $contentType    The content type.
	 * @param int|null $pageTemplateId Optional page-specific template ID override.
	 *
	 * @return array<string, mixed>|Template|null The resolved template.
	 */
	public function resolveTemplate( string $contentType, ?int $pageTemplateId = null ): Template|array|null
	{
		// 1. Page-specific template.
		if ( null !== $pageTemplateId ) {
			$pageTemplate = Template::find( $pageTemplateId );

			if ( null !== $pageTemplate ) {
				return $pageTemplate;
			}
		}

		// 2. Content type default.
		$contentTypeDefault = $this->defaultFor( $contentType );

		if ( null !== $contentTypeDefault ) {
			return $contentTypeDefault;
		}

		// 3. Site default template (from config).
		$siteDefaultSlug = config( 'artisanpack.visual-editor.templates.default_template', 'blank' );
		$templateManager = app( 'visual-editor.templates' );

		return $templateManager->resolve( $siteDefaultSlug );
	}

	/**
	 * Validate that a template can be assigned to a content type.
	 *
	 * A template is compatible if its `for_content_type` is null
	 * (universal) or matches the target content type.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $templateId  The template ID to validate.
	 * @param string $contentType The target content type.
	 *
	 * @return bool True if the assignment is valid.
	 */
	public function validateAssignment( int $templateId, string $contentType ): bool
	{
		$template = Template::find( $templateId );

		if ( null === $template ) {
			return false;
		}

		$isValid = null === $template->for_content_type || $contentType === $template->for_content_type;

		return veApplyFilters( 'ap.visualEditor.validateTemplateAssignment', $isValid, $template, $contentType );
	}

	/**
	 * Bulk assign a template to multiple content entities.
	 *
	 * Accepts a model class and array of entity IDs. Sets the
	 * `template_id` column on each entity. The model must have
	 * a `template_id` column.
	 *
	 * @since 1.0.0
	 *
	 * @param int                $templateId  The template ID to assign.
	 * @param string             $modelClass  The fully qualified model class name.
	 * @param array<int, int>    $entityIds   The entity IDs to update.
	 *
	 * @throws InvalidArgumentException If the template does not exist.
	 *
	 * @return int The number of entities updated.
	 */
	public function bulkAssign( int $templateId, string $modelClass, array $entityIds ): int
	{
		$template = Template::find( $templateId );

		if ( null === $template ) {
			throw new InvalidArgumentException(
				__( 'Template with ID :id does not exist.', [ 'id' => $templateId ] ),
			);
		}

		$count = $modelClass::whereIn( 'id', $entityIds )
			->update( [ 'template_id' => $templateId ] );

		veDoAction( 'ap.visualEditor.templateBulkAssigned', $template, $modelClass, $entityIds, $count );

		return $count;
	}

	/**
	 * Get all content type assignments.
	 *
	 * @since 1.0.0
	 *
	 * @return \Illuminate\Database\Eloquent\Collection<int, TemplateAssignment>
	 */
	public function allAssignments(): \Illuminate\Database\Eloquent\Collection
	{
		return TemplateAssignment::with( 'template' )->get();
	}

	/**
	 * Get the assignment record for a specific content type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $contentType The content type to look up.
	 *
	 * @return TemplateAssignment|null
	 */
	public function getAssignment( string $contentType ): ?TemplateAssignment
	{
		return TemplateAssignment::where( 'content_type', $contentType )->first();
	}
}
