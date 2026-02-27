<?php

use ArtisanPackUI\VisualEditor\Models\Revision;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
	/**
	 * The document type for revisions.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $documentType = '';

	/**
	 * The document ID for revisions.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	public int $documentId = 0;

	/**
	 * Mount the component.
	 *
	 * @since 1.0.0
	 *
	 * @param string $documentType The document type.
	 * @param int    $documentId   The document ID.
	 *
	 * @return void
	 */
	public function mount( string $documentType = '', int $documentId = 0 ): void
	{
		$this->documentType = $documentType;
		$this->documentId   = $documentId;
	}

	/**
	 * Get all revisions for this document.
	 *
	 * @since 1.0.0
	 *
	 * @return Collection<int, Revision>
	 */
	#[Computed]
	public function revisions(): Collection
	{
		return Revision::forDocument( $this->documentType, $this->documentId )
			->orderByDesc( 'created_at' )
			->get();
	}

	/**
	 * Create a new revision from the current blocks.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $blocks The block content to save.
	 *
	 * @return void
	 */
	#[On( 've-document-saved' )]
	public function createRevision( array $blocks = [] ): void
	{
		if ( empty( $blocks ) ) {
			return;
		}

		$maxRevisions = config( 'artisanpack.visual-editor.persistence.max_revisions', 50 );

		Revision::create( [
			'document_type' => $this->documentType,
			'document_id'   => $this->documentId,
			'blocks'        => $blocks,
			'user_id'       => auth()->id(),
			'created_at'    => now(),
		] );

		$this->enforceMaxRevisions( $maxRevisions );

		unset( $this->revisions );

		$this->dispatch( 've-revision-created' );
	}

	/**
	 * Restore a revision.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id The revision ID to restore.
	 *
	 * @return void
	 */
	public function restoreRevision( int $id ): void
	{
		$revision = Revision::findOrFail( $id );

		$this->dispatch( 've-revision-restored', blocks: $revision->blocks );
	}

	/**
	 * Delete a revision.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id The revision ID to delete.
	 *
	 * @return void
	 */
	public function deleteRevision( int $id ): void
	{
		Revision::findOrFail( $id )->delete();

		unset( $this->revisions );

		$this->dispatch( 've-revision-deleted' );
	}

	/**
	 * Enforce the maximum number of revisions by deleting the oldest.
	 *
	 * @since 1.0.0
	 *
	 * @param int $maxRevisions The maximum number of revisions to keep.
	 *
	 * @return void
	 */
	private function enforceMaxRevisions( int $maxRevisions ): void
	{
		$count = Revision::forDocument( $this->documentType, $this->documentId )->count();

		if ( $count > $maxRevisions ) {
			Revision::forDocument( $this->documentType, $this->documentId )
				->orderBy( 'created_at' )
				->limit( $count - $maxRevisions )
				->delete();
		}
	}
}; ?>

<div>
	{{-- Revision history is a headless component — no visible UI --}}
</div>
