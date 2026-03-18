<?php

/**
 * Has Visual Editor Content Trait.
 *
 * Provides default implementations for the EditorContent interface.
 * Add this trait to any Eloquent model that stores visual editor blocks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Concerns
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Concerns;

use ArtisanPackUI\VisualEditor\Models\Revision;
use ArtisanPackUI\VisualEditor\Rendering\BlockRenderer;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Trait for models that store visual editor content.
 *
 * Provides block getters/setters, save pipeline with automatic
 * revision creation, revision management, and hooks integration.
 *
 * Requires a `blocks` JSON column on the model's table.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Concerns
 *
 * @since      1.0.0
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasVisualEditorContent
{
	/**
	 * Initialize the trait by merging the blocks cast.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function initializeHasVisualEditorContent(): void
	{
		$this->mergeCasts( [
			'blocks' => 'array',
		] );
	}

	/**
	 * Get the block content array.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getBlocks(): array
	{
		return $this->blocks ?? [];
	}

	/**
	 * Set the block content array.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $blocks The block content array.
	 *
	 * @return void
	 */
	public function setBlocks( array $blocks ): void
	{
		$this->blocks = $blocks;
	}

	/**
	 * Save the model from editor metadata.
	 *
	 * Handles blocks and status by default. Override this method
	 * to handle custom fields like title, excerpt, taxonomies, etc.
	 *
	 * Automatically creates a revision after saving.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $meta The editor metadata.
	 *
	 * @return void
	 */
	public function saveFromEditor( array $meta ): void
	{
		$meta = applyFilters( 'ap.visualEditor.beforeSave', $meta, $this );

		$this->blocks = $meta['blocks'] ?? [];

		if ( isset( $meta['status'] ) ) {
			$this->status = $meta['status'];
		}

		if ( isset( $meta['scheduledDate'] ) ) {
			$this->scheduled_at = $meta['scheduledDate'];
		}

		$this->save();

		$this->createRevision();

		doAction( 'ap.visualEditor.afterSave', $this, $meta );
	}

	/**
	 * Render the stored blocks as front-end HTML.
	 *
	 * Delegates to the BlockRenderer service which walks the block
	 * tree and produces clean, semantic HTML for front-end display.
	 *
	 * @since 1.0.0
	 *
	 * @return string The rendered HTML output.
	 */
	public function renderBlocks(): string
	{
		return app( BlockRenderer::class )->render( $this->getBlocks() );
	}

	/**
	 * Get the revisions for this model.
	 *
	 * @since 1.0.0
	 *
	 * @return MorphMany
	 */
	public function revisions(): MorphMany
	{
		return $this->morphMany(
			Revision::class,
			'document',
			'document_type',
			'document_id',
		);
	}

	/**
	 * Get the latest revision.
	 *
	 * @since 1.0.0
	 *
	 * @return Revision|null
	 */
	public function latestRevision(): ?Revision
	{
		return $this->revisions()->latest( 'id' )->first();
	}

	/**
	 * Restore the model's block content from a specific revision.
	 *
	 * Only restores the blocks array — status, scheduled_at, and other
	 * model attributes are left unchanged. A new revision is created
	 * after the restore to record the state change.
	 *
	 * @since 1.0.0
	 *
	 * @param int $revisionId The revision ID to restore.
	 *
	 * @return void
	 */
	public function restoreRevision( int $revisionId ): void
	{
		$revision = $this->revisions()->findOrFail( $revisionId );

		$this->blocks = $revision->blocks;
		$this->save();

		$this->createRevision();
	}

	/**
	 * Create a revision snapshot of the current block content.
	 *
	 * Respects the max_revisions configuration, pruning the oldest
	 * revisions when the limit is exceeded.
	 *
	 * @since 1.0.0
	 *
	 * @return Revision
	 */
	protected function createRevision(): Revision
	{
		$revision = $this->revisions()->create( [
			'blocks'     => $this->getBlocks(),
			'user_id'    => Auth::id(),
			'created_at' => now(),
		] );

		$this->pruneRevisions();

		return $revision;
	}

	/**
	 * Prune old revisions beyond the configured maximum.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function pruneRevisions(): void
	{
		$maxRevisions = (int) config( 'artisanpack.visual-editor.persistence.max_revisions', 50 );

		if ( $maxRevisions <= 0 ) {
			return;
		}

		$count = $this->revisions()->count();

		if ( $count <= $maxRevisions ) {
			return;
		}

		$idsToKeep = $this->revisions()
			->latest( 'id' )
			->limit( $maxRevisions )
			->pluck( 'id' );

		$this->revisions()
			->whereNotIn( 'id', $idsToKeep )
			->delete();
	}
}
