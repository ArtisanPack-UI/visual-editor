<?php

/**
 * Content Service.
 *
 * Handles all save, publish, and revision operations for
 * visual editor content.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Services
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services;

use ArtisanPackUI\VisualEditor\Models\Content;
use ArtisanPackUI\VisualEditor\Models\ContentRevision;
use Illuminate\Support\Carbon;

/**
 * Content service class.
 *
 * Provides methods for saving drafts, autosaving, publishing,
 * unpublishing, scheduling, and running pre-publish checks.
 *
 * @since 1.0.0
 */
class ContentService
{
	/**
	 * Saves content as a draft and creates a manual revision.
	 *
	 * @since 1.0.0
	 *
	 * @param Content $content The content to save.
	 * @param array   $data    The data to update on the content.
	 * @param int     $userId  The ID of the user saving.
	 *
	 * @return Content
	 */
	public function saveDraft( Content $content, array $data, int $userId ): Content
	{
		$fillableFields = [
			'title',
			'sections',
			'settings',
			'excerpt',
			'template',
			'template_overrides',
			'meta_title',
			'meta_description',
			'og_image',
			'featured_media_id',
		];

		foreach ( $fillableFields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$content->$field = $data[ $field ];
			}
		}

		$content->save();

		$this->createRevision( $content, 'manual', $userId );

		return $content;
	}

	/**
	 * Creates an autosave revision without modifying the content record.
	 *
	 * @since 1.0.0
	 *
	 * @param Content $content The content to autosave.
	 * @param array   $data    The current editor data to snapshot.
	 * @param int     $userId  The ID of the user.
	 *
	 * @return ContentRevision
	 */
	public function autosave( Content $content, array $data, int $userId ): ContentRevision
	{
		$revision = ContentRevision::create( [
			'content_id' => $content->id,
			'user_id'    => $userId,
			'type'       => 'autosave',
			'data'       => $data,
			'created_at' => now(),
		] );

		$this->pruneAutosaves( $content );

		return $revision;
	}

	/**
	 * Publishes content.
	 *
	 * Sets the status to published, records the published_at timestamp,
	 * and creates a publish revision.
	 *
	 * @since 1.0.0
	 *
	 * @param Content $content The content to publish.
	 * @param int     $userId  The ID of the user publishing.
	 *
	 * @return Content
	 */
	public function publish( Content $content, int $userId ): Content
	{
		$content->status       = 'published';
		$content->published_at = now();
		$content->scheduled_at = null;
		$content->save();

		$this->createRevision( $content, 'publish', $userId );

		return $content;
	}

	/**
	 * Unpublishes content by reverting it to draft status.
	 *
	 * @since 1.0.0
	 *
	 * @param Content $content The content to unpublish.
	 * @param int     $userId  The ID of the user unpublishing.
	 *
	 * @return Content
	 */
	public function unpublish( Content $content, int $userId ): Content
	{
		$content->status       = 'draft';
		$content->published_at = null;
		$content->save();

		$this->createRevision( $content, 'manual', $userId );

		return $content;
	}

	/**
	 * Schedules content for future publication.
	 *
	 * @since 1.0.0
	 *
	 * @param Content $content   The content to schedule.
	 * @param Carbon  $publishAt The date and time to publish.
	 * @param int     $userId    The ID of the user scheduling.
	 *
	 * @return Content
	 */
	public function schedule( Content $content, Carbon $publishAt, int $userId ): Content
	{
		$content->status       = 'scheduled';
		$content->scheduled_at = $publishAt;
		$content->save();

		$this->createRevision( $content, 'manual', $userId );

		return $content;
	}

	/**
	 * Submits content for review by setting status to pending.
	 *
	 * @since 1.0.0
	 *
	 * @param Content $content The content to submit.
	 * @param int     $userId  The ID of the user submitting.
	 *
	 * @return Content
	 */
	public function submitForReview( Content $content, int $userId ): Content
	{
		$content->status = 'pending';
		$content->save();

		$this->createRevision( $content, 'manual', $userId );

		return $content;
	}

	/**
	 * Runs pre-publish checks on content.
	 *
	 * Returns an array of check results, each with a key, label,
	 * status (pass, fail, warning), and message.
	 *
	 * @since 1.0.0
	 *
	 * @param Content $content The content to check.
	 *
	 * @return array<int, array{key: string, label: string, status: string, message: string}>
	 */
	public function runPrePublishChecks( Content $content ): array
	{
		$checks = [];

		// Title check
		$hasTitle  = '' !== trim( $content->title ?? '' );
		$checks[]  = [
			'key'     => 'title',
			'label'   => __( 'Title' ),
			'status'  => $hasTitle ? 'pass' : 'fail',
			'message' => $hasTitle ? __( 'Title is set.' ) : __( 'Title is required before publishing.' ),
		];

		// Featured image check
		$hasFeatured = null !== $content->featured_media_id;
		$isRequired  = config( 'artisanpack.visual-editor.content.require_featured_image', false );
		$checks[]    = [
			'key'     => 'featured_image',
			'label'   => __( 'Featured Image' ),
			'status'  => $hasFeatured ? 'pass' : ( $isRequired ? 'fail' : 'warning' ),
			'message' => $hasFeatured ? __( 'Featured image is set.' ) : __( 'No featured image selected.' ),
		];

		// Meta title check
		$hasMetaTitle = '' !== trim( $content->meta_title ?? '' );
		$checks[]     = [
			'key'     => 'meta_title',
			'label'   => __( 'Meta Title' ),
			'status'  => $hasMetaTitle ? 'pass' : 'warning',
			'message' => $hasMetaTitle ? __( 'Meta title is set.' ) : __( 'No meta title set for SEO.' ),
		];

		// Meta description check
		$hasMetaDesc = '' !== trim( $content->meta_description ?? '' );
		$checks[]    = [
			'key'     => 'meta_description',
			'label'   => __( 'Meta Description' ),
			'status'  => $hasMetaDesc ? 'pass' : 'warning',
			'message' => $hasMetaDesc ? __( 'Meta description is set.' ) : __( 'No meta description set for SEO.' ),
		];

		return $checks;
	}

	/**
	 * Creates a content revision snapshot.
	 *
	 * @since 1.0.0
	 *
	 * @param Content     $content The content to snapshot.
	 * @param string      $type    The revision type (autosave, manual, named, publish, pre_restore).
	 * @param int         $userId  The ID of the user.
	 * @param string|null $name    Optional name for named revisions.
	 *
	 * @return ContentRevision
	 */
	protected function createRevision( Content $content, string $type, int $userId, ?string $name = null ): ContentRevision
	{
		return ContentRevision::create( [
			'content_id'     => $content->id,
			'user_id'        => $userId,
			'type'           => $type,
			'name'           => $name,
			'data'           => [
				'title'              => $content->title,
				'sections'           => $content->sections,
				'settings'           => $content->settings,
				'excerpt'            => $content->excerpt,
				'template'           => $content->template,
				'template_overrides' => $content->template_overrides,
				'status'             => $content->status,
				'meta_title'         => $content->meta_title,
				'meta_description'   => $content->meta_description,
				'og_image'           => $content->og_image,
				'featured_media_id'  => $content->featured_media_id,
			],
			'change_summary' => null,
			'created_at'     => now(),
		] );
	}

	/**
	 * Prunes old autosave revisions exceeding the configured limit.
	 *
	 * @since 1.0.0
	 *
	 * @param Content $content The content whose autosaves to prune.
	 *
	 * @return void
	 */
	protected function pruneAutosaves( Content $content ): void
	{
		$maxAutosaves = (int) config( 'artisanpack.visual-editor.revisions.max_autosaves_per_content', 10 );

		$autosaveIds = $content->revisions()
			->where( 'type', 'autosave' )
			->orderByDesc( 'created_at' )
			->pluck( 'id' );

		if ( $autosaveIds->count() <= $maxAutosaves ) {
			return;
		}

		$idsToDelete = $autosaveIds->slice( $maxAutosaves );

		ContentRevision::whereIn( 'id', $idsToDelete->all() )->delete();
	}
}
