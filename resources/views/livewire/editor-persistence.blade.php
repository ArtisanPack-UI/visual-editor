<?php

use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
	/**
	 * The model class for the document being edited.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $documentType = '';

	/**
	 * The document identifier for draft storage.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $documentId = '';

	/**
	 * Whether a draft exists for this document.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	public bool $hasDraft = false;

	/**
	 * Mount the component and check for existing drafts.
	 *
	 * If a draft exists in cache, dispatches `ve-draft-restored` with the
	 * stored blocks and meta so the Alpine store can prompt the user.
	 *
	 * @since 1.0.0
	 *
	 * @param string $documentType The model class (e.g., App\Models\Post).
	 * @param string $documentId   The model ID being edited.
	 *
	 * @return void
	 */
	public function mount( string $documentType = '', string $documentId = '' ): void
	{
		if ( '' === trim( $documentType ) ) {
			throw new \InvalidArgumentException( __( 'visual-editor::ve.persistence_document_type_required' ) );
		}

		if ( '' === trim( $documentId ) ) {
			throw new \InvalidArgumentException( __( 'visual-editor::ve.persistence_document_id_required' ) );
		}

		$this->documentType = trim( $documentType );
		$this->documentId   = trim( $documentId );
		$this->hasDraft     = Cache::has( $this->getCacheKey() );

		if ( $this->hasDraft ) {
			$this->loadDraft();
		}
	}

	/**
	 * Save a draft of the current blocks and meta to cache.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $blocks The block content to save.
	 * @param array<string, mixed>             $meta   Additional metadata to save with the draft.
	 *
	 * @return void
	 */
	#[On( 've-autosave' )]
	public function saveDraft( array $blocks, array $meta = [] ): void
	{
		$ttl = config( 'artisanpack.visual-editor.persistence.draft_ttl', 86400 );

		Cache::put( $this->getCacheKey(), [ 'blocks' => $blocks, 'meta' => $meta ], $ttl );

		$this->hasDraft = true;

		$this->dispatch( 've-draft-saved' );
	}

	/**
	 * Load and dispatch a saved draft.
	 *
	 * Checks cache for an existing draft and dispatches `ve-draft-restored`
	 * with the stored blocks and meta if found.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function loadDraft(): void
	{
		$draft = Cache::get( $this->getCacheKey() );

		if ( null === $draft ) {
			return;
		}

		$this->dispatch( 've-draft-restored', blocks: $draft['blocks'] ?? [], meta: $draft['meta'] ?? [] );
	}

	/**
	 * Restore a saved draft.
	 *
	 * Alias for loadDraft that can be called from the UI
	 * when the user accepts the restore prompt.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function restoreDraft(): void
	{
		$this->loadDraft();
	}

	/**
	 * Clear the draft from cache.
	 *
	 * Called after a successful save to remove stale drafts.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	#[On( 've-document-saved' )]
	public function clearDraft(): void
	{
		Cache::forget( $this->getCacheKey() );

		$this->hasDraft = false;
	}

	/**
	 * Discard a saved draft.
	 *
	 * Removes the draft from cache and dispatches `ve-draft-discarded`
	 * so the Alpine store can update its state.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function discardDraft(): void
	{
		Cache::forget( $this->getCacheKey() );

		$this->hasDraft = false;

		$this->dispatch( 've-draft-discarded' );
	}

	/**
	 * Get the cache key for this document's draft.
	 *
	 * Format: ve-draft-{documentType}-{documentId}-{userId}
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function getCacheKey(): string
	{
		$userId = auth()->id() ?? 'guest-' . session()->getId();

		return 've-draft-' . $this->documentType . '-' . $this->documentId . '-' . $userId;
	}
}; ?>

<div>
	{{-- Editor persistence is a headless component — no visible UI --}}
</div>
