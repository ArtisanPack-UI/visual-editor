<?php

use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
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
	 * Mount the component.
	 *
	 * @since 1.0.0
	 *
	 * @param string $documentId The document identifier.
	 *
	 * @return void
	 */
	public function mount( string $documentId = '' ): void
	{
		$this->documentId = $documentId;
		$this->hasDraft   = Cache::has( $this->getCacheKey() );
	}

	/**
	 * Save a draft of the current blocks.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $blocks The block content to save.
	 *
	 * @return void
	 */
	#[On( 've-autosave' )]
	public function saveDraft( array $blocks ): void
	{
		$ttl = config( 'artisanpack.visual-editor.persistence.draft_ttl', 86400 );

		Cache::put( $this->getCacheKey(), $blocks, $ttl );

		$this->hasDraft = true;

		$this->dispatch( 've-draft-saved' );
	}

	/**
	 * Restore a saved draft.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function restoreDraft(): void
	{
		$blocks = Cache::get( $this->getCacheKey() );

		if ( null === $blocks ) {
			return;
		}

		$this->dispatch( 've-draft-restored', blocks: $blocks );
	}

	/**
	 * Discard a saved draft.
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
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function getCacheKey(): string
	{
		return 've-draft-' . $this->documentId;
	}
}; ?>

<div>
	{{-- Editor persistence is a headless component — no visible UI --}}
</div>
