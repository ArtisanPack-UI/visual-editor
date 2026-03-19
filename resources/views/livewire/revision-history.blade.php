<?php

use ArtisanPackUI\VisualEditor\Models\Revision;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
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
	 * Whether the revision history panel is visible.
	 *
	 * When embedded inside a parent container (e.g. a collapsible panel
	 * body), set this to true on mount so the list renders immediately.
	 * When used standalone, togglePanel() controls visibility.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	public bool $showPanel = false;

	/**
	 * Mount the component.
	 *
	 * @since 1.0.0
	 *
	 * @param string $documentType The document type.
	 * @param int    $documentId   The document ID.
	 * @param bool   $inline       Whether to show the list immediately (no toggle needed).
	 *
	 * @return void
	 */
	public function mount( string $documentType = '', int $documentId = 0, bool $inline = false ): void
	{
		$this->documentType = $documentType;
		$this->documentId   = $documentId;
		$this->showPanel    = $inline;
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
	 * Toggle the revision history panel visibility.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function togglePanel(): void
	{
		$this->showPanel = ! $this->showPanel;

		if ( $this->showPanel ) {
			unset( $this->revisions );
		}
	}

	/**
	 * Refresh the revision list after a document save.
	 *
	 * Revision creation is handled by the HasVisualEditorContent trait;
	 * this listener simply invalidates the cached computed property so
	 * the UI reflects the latest revisions.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	#[On( 've-document-saved' )]
	public function refreshRevisions(): void
	{
		unset( $this->revisions );
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
		$revision = Revision::forDocument( $this->documentType, $this->documentId )
			->where( 'id', $id )
			->firstOrFail();

		if ( Gate::getPolicyFor( $revision ) ) {
			Gate::authorize( 'restore', $revision );
		} elseif ( class_exists( $this->documentType ) ) {
			$document = $this->documentType::findOrFail( $this->documentId );
			if ( Gate::getPolicyFor( $document ) ) {
				Gate::authorize( 'update', $document );
			}
		}

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
		$revision = Revision::forDocument( $this->documentType, $this->documentId )
			->where( 'id', $id )
			->firstOrFail();

		if ( Gate::getPolicyFor( $revision ) ) {
			Gate::authorize( 'delete', $revision );
		} elseif ( class_exists( $this->documentType ) ) {
			$document = $this->documentType::findOrFail( $this->documentId );
			if ( Gate::getPolicyFor( $document ) ) {
				Gate::authorize( 'update', $document );
			}
		}

		$revision->delete();

		unset( $this->revisions );

		$this->dispatch( 've-revision-deleted' );
	}
}; ?>

<div>
	@if ( $showPanel )
		<div class="flex flex-col gap-0 max-h-80 overflow-y-auto">
			@forelse ( $this->revisions as $revision )
				<div
					wire:key="revision-{{ $revision->id }}"
					class="flex items-center justify-between px-3 py-2 border-b border-base-300 last:border-b-0 hover:bg-base-200/30 transition-colors"
				>
					<div class="flex flex-col gap-0.5 min-w-0">
						<time
							class="text-xs font-medium truncate"
							datetime="{{ $revision->created_at->toIso8601String() }}"
						>
							{{ $revision->created_at->diffForHumans() }}
						</time>
						<span class="text-xs opacity-60 truncate">
							@if ( $revision->user )
								{{ __( 'visual-editor::ve.revision_by', [ 'name' => $revision->user->name ] ) }}
							@else
								{{ __( 'visual-editor::ve.revision_by', [ 'name' => __( 'visual-editor::ve.revision_guest' ) ] ) }}
							@endif
						</span>
					</div>

					<div class="flex items-center gap-1 shrink-0">
						<button
							type="button"
							wire:click="restoreRevision( {{ $revision->id }} )"
							wire:confirm="{{ __( 'visual-editor::ve.confirm_restore_revision' ) }}"
							class="p-1 rounded hover:bg-base-300 transition-colors"
							title="{{ __( 'visual-editor::ve.restore_revision' ) }}"
							aria-label="{{ __( 'visual-editor::ve.restore_revision' ) }}"
						>
							<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
								<path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
							</svg>
						</button>
						<button
							type="button"
							wire:click="deleteRevision( {{ $revision->id }} )"
							wire:confirm="{{ __( 'visual-editor::ve.confirm_delete_revision' ) }}"
							class="p-1 rounded hover:bg-error/10 hover:text-error transition-colors"
							title="{{ __( 'visual-editor::ve.delete_revision' ) }}"
							aria-label="{{ __( 'visual-editor::ve.delete_revision' ) }}"
						>
							<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
								<path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
							</svg>
						</button>
					</div>
				</div>
			@empty
				<div class="px-3 py-6 text-center text-sm opacity-60">
					{{ __( 'visual-editor::ve.no_revisions' ) }}
				</div>
			@endforelse
		</div>
	@endif
</div>
