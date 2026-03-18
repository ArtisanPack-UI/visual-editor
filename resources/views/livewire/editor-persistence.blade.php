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
	 * If a draft exists in cache, dispatches `ve-draft-found` so the
	 * Alpine store can prompt the user to restore or discard. Does not
	 * auto-restore — the user must explicitly choose to restore.
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
		$this->migrateLegacyDraft();
		$this->hasDraft     = Cache::has( $this->getCacheKey() );

		if ( $this->hasDraft ) {
			$this->dispatch( 've-draft-found' );
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

		$draft = $this->normalizeDraft( $draft );

		$this->dispatch( 've-draft-restored', blocks: $draft['blocks'], meta: $draft['meta'] );
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
	 * Migrate a draft stored under the legacy cache key format.
	 *
	 * Checks for drafts stored under the old `ve-draft-{userId}-{documentId}`
	 * format and moves them to the new `ve-draft-{documentType}-{documentId}-{userId}`
	 * format. The legacy key is deleted after migration.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function migrateLegacyDraft(): void
	{
		$legacyKey = $this->getLegacyCacheKey();

		if ( ! Cache::has( $legacyKey ) ) {
			return;
		}

		if ( ! Cache::has( $this->getCacheKey() ) ) {
			Cache::put(
				$this->getCacheKey(),
				$this->normalizeDraft( Cache::get( $legacyKey ) ),
				config( 'artisanpack.visual-editor.persistence.draft_ttl', 86400 ),
			);
		}

		Cache::forget( $legacyKey );
	}

	/**
	 * Normalize a draft payload into the expected shape.
	 *
	 * Legacy drafts were stored as raw block arrays. This method
	 * detects that format and wraps it into `{ blocks, meta }`.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string|int, mixed> $draft The raw or normalized draft data.
	 *
	 * @return array{blocks: array<int, array<string, mixed>>, meta: array<string, mixed>}
	 */
	private function normalizeDraft( array $draft ): array
	{
		if ( ! array_key_exists( 'blocks', $draft ) ) {
			return [ 'blocks' => $draft, 'meta' => [] ];
		}

		return [
			'blocks' => $draft['blocks'] ?? [],
			'meta'   => $draft['meta'] ?? [],
		];
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
		return 've-draft-' . $this->documentType . '-' . $this->documentId . '-' . $this->getUserId();
	}

	/**
	 * Get the legacy cache key format for migration.
	 *
	 * Format: ve-draft-{userId}-{documentId}
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function getLegacyCacheKey(): string
	{
		return 've-draft-' . $this->getUserId() . '-' . $this->documentId;
	}

	/**
	 * Get the current user identifier for cache key generation.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function getUserId(): string
	{
		return (string) ( auth()->id() ?? 'guest-' . session()->getId() );
	}
}; ?>

<div>
	{{-- Editor persistence is a headless component — no visible UI --}}
</div>
