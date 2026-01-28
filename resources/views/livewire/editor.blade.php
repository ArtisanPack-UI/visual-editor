<?php

declare( strict_types=1 );

/**
 * Visual Editor - Main Editor Shell
 *
 * The top-level page component that serves as the main container for
 * all editor functionality including the sidebar, toolbar, canvas,
 * and status bar. Manages save, publish, autosave, and scheduling
 * workflows.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire
 *
 * @since      1.0.0
 */

use ArtisanPackUI\VisualEditor\Models\Content;
use ArtisanPackUI\VisualEditor\Services\ContentService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
	/**
	 * The content being edited.
	 *
	 * @since 1.0.0
	 *
	 * @var Content
	 */
	public Content $content;

	/**
	 * The content sections data.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public array $sections = [];

	/**
	 * Whether the sidebar panel is open.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	public bool $sidebarOpen = true;

	/**
	 * The active sidebar tab.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $sidebarTab = 'blocks';

	/**
	 * The ID of the currently active block.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	public ?string $activeBlockId = null;

	/**
	 * Whether the content has unsaved changes.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	public bool $isDirty = false;

	/**
	 * The current save status.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $saveStatus = 'saved';

	/**
	 * The last saved time as a human-readable string.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $lastSaved = '';

	/**
	 * Whether the pre-publish panel is visible.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	public bool $showPrePublishPanel = false;

	/**
	 * The pre-publish check results.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public array $prePublishChecks = [];

	/**
	 * The scheduled publish date.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $scheduleDate = '';

	/**
	 * The scheduled publish time.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $scheduleTime = '';

	/**
	 * Mount the component with the given content.
	 *
	 * @since 1.0.0
	 *
	 * @param Content $content The content to edit.
	 *
	 * @return void
	 */
	public function mount( Content $content ): void
	{
		$this->content  = $content;
		$this->sections = $content->sections ?? [];
	}

	/**
	 * Save the content as a draft.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	#[On( 'editor-save' )]
	public function save(): void
	{
		$this->saveStatus = 'saving';

		try {
			$service = app( ContentService::class );

			$service->saveDraft( $this->content, [
				'sections' => $this->sections,
			], auth()->id() );

			$this->content->refresh();

			$this->saveStatus = 'saved';
			$this->isDirty    = false;
			$this->lastSaved  = now()->format( 'g:i A' );
		} catch ( \Throwable $e ) {
			$this->saveStatus = 'error';
			session()->flash( 'error', __( 'Failed to save content.' ) );
		}
	}

	/**
	 * Opens the pre-publish checklist panel.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	#[On( 'editor-publish' )]
	public function publish(): void
	{
		$service                  = app( ContentService::class );
		$this->prePublishChecks   = $service->runPrePublishChecks( $this->content );
		$this->showPrePublishPanel = true;
	}

	/**
	 * Confirms publishing after the pre-publish checklist.
	 *
	 * If schedule date and time are set, schedules instead of
	 * publishing immediately.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function confirmPublish(): void
	{
		try {
			$service = app( ContentService::class );

			// Save current sections first.
			$service->saveDraft( $this->content, [
				'sections' => $this->sections,
			], auth()->id() );

			if ( '' !== $this->scheduleDate && '' !== $this->scheduleTime ) {
				$publishAt = Carbon::parse( $this->scheduleDate . ' ' . $this->scheduleTime );
				$service->schedule( $this->content, $publishAt, auth()->id() );
			} else {
				$service->publish( $this->content, auth()->id() );
			}

			$this->content->refresh();
			$this->showPrePublishPanel = false;
			$this->isDirty             = false;
			$this->saveStatus          = 'saved';
			$this->lastSaved           = now()->format( 'g:i A' );
			$this->scheduleDate        = '';
			$this->scheduleTime        = '';
		} catch ( \Throwable $e ) {
			$this->saveStatus = 'error';
			session()->flash( 'error', __( 'Failed to publish content.' ) );
		}
	}

	/**
	 * Unpublishes content by reverting to draft status.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	#[On( 'editor-unpublish' )]
	public function unpublish(): void
	{
		try {
			$service = app( ContentService::class );
			$service->unpublish( $this->content, auth()->id() );
			$this->content->refresh();
		} catch ( \Throwable $e ) {
			session()->flash( 'error', __( 'Failed to unpublish content.' ) );
		}
	}

	/**
	 * Autosaves the current editor state if dirty.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function autosave(): void
	{
		if ( !$this->isDirty ) {
			return;
		}

		try {
			$service = app( ContentService::class );
			$service->autosave( $this->content, [
				'sections' => $this->sections,
			], auth()->id() );
		} catch ( \Throwable $e ) {
			// Autosave failures are silent to not disrupt editing.
		}
	}

	/**
	 * Submits content for review.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function submitForReview(): void
	{
		try {
			$service = app( ContentService::class );

			$service->saveDraft( $this->content, [
				'sections' => $this->sections,
			], auth()->id() );

			$service->submitForReview( $this->content, auth()->id() );
			$this->content->refresh();
			$this->isDirty   = false;
			$this->saveStatus = 'saved';
			$this->lastSaved  = now()->format( 'g:i A' );
		} catch ( \Throwable $e ) {
			session()->flash( 'error', __( 'Failed to submit for review.' ) );
		}
	}

	/**
	 * Closes the pre-publish checklist panel.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function closePrePublishPanel(): void
	{
		$this->showPrePublishPanel = false;
		$this->scheduleDate        = '';
		$this->scheduleTime        = '';
	}

	/**
	 * Toggle the sidebar open/closed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function toggleSidebar(): void
	{
		$this->sidebarOpen = !$this->sidebarOpen;
	}

	/**
	 * Set the active sidebar tab.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tab The tab to activate.
	 *
	 * @return void
	 */
	public function setSidebarTab( string $tab ): void
	{
		$this->sidebarTab = $tab;
	}

	/**
	 * Deselect the active block.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function deselectBlock(): void
	{
		$this->activeBlockId = null;
	}

	/**
	 * Handle the sections being updated.
	 *
	 * @since 1.0.0
	 *
	 * @param array $sections The updated sections data.
	 *
	 * @return void
	 */
	#[On( 'sections-updated' )]
	public function onSectionsUpdated( array $sections ): void
	{
		$this->sections = $sections;
		$this->isDirty  = true;
		$this->saveStatus = 'unsaved';
	}
}; ?>

<div class="ve-editor flex h-screen flex-col overflow-hidden bg-gray-100"
	 data-autosave-interval="{{ config( 'artisanpack.visual-editor.editor.autosave_interval', 60 ) }}">
	{{-- Top Toolbar --}}
	<livewire:visual-editor::toolbar
		:save-status="$saveStatus"
		:content-title="$content->title"
		:content-status="$content->status"
	/>

	{{-- Main Editor Area --}}
	<div class="flex flex-1 overflow-hidden">
		{{-- Left Sidebar --}}
		@if ( $sidebarOpen )
			<livewire:visual-editor::sidebar
				:active-tab="$sidebarTab"
			/>
		@endif

		{{-- Canvas Area --}}
		<div class="flex flex-1 flex-col overflow-hidden">
			<livewire:visual-editor::canvas
				:sections="$sections"
				:active-block-id="$activeBlockId"
			/>

			{{-- Status Bar --}}
			<livewire:visual-editor::status-bar
				:save-status="$saveStatus"
				:content-status="$content->status"
				:last-saved="$lastSaved"
			/>
		</div>
	</div>

	{{-- Pre-Publish Checklist Panel --}}
	<x-artisanpack-drawer
		wire:model="showPrePublishPanel"
		right
		:title="__( 'Pre-Publish Checklist' )"
		separator
		with-close-button
		close-on-escape
		class="z-50"
	>
		{{-- Checklist Items --}}
		<div class="space-y-3">
			@foreach ( $prePublishChecks as $check )
				<div class="flex items-start gap-3 rounded-md border border-gray-200 p-3">
					@if ( 'pass' === $check['status'] )
						<x-artisanpack-icon name="o-check-circle" class="mt-0.5 h-5 w-5 shrink-0 text-green-500" />
					@elseif ( 'fail' === $check['status'] )
						<x-artisanpack-icon name="o-x-circle" class="mt-0.5 h-5 w-5 shrink-0 text-red-500" />
					@else
						<x-artisanpack-icon name="o-exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-yellow-500" />
					@endif
					<div>
						<p class="text-sm font-medium text-gray-900">{{ $check['label'] }}</p>
						<p class="text-xs text-gray-500">{{ $check['message'] }}</p>
					</div>
				</div>
			@endforeach
		</div>

		{{-- Schedule Section --}}
		<div class="mt-6">
			<x-artisanpack-heading level="3" size="text-sm" semibold class="mb-2">
				{{ __( 'Schedule (optional)' ) }}
			</x-artisanpack-heading>
			<div class="flex gap-2">
				<x-artisanpack-input
					wire:model="scheduleDate"
					type="date"
					class="flex-1"
				/>
				<x-artisanpack-input
					wire:model="scheduleTime"
					type="time"
					class="flex-1"
				/>
			</div>
			<p class="mt-1 text-xs text-gray-400">
				{{ __( 'Leave empty to publish immediately.' ) }}
			</p>
		</div>

		{{-- Panel Footer --}}
		<x-slot:actions>
			@php
				$hasFailures = collect( $prePublishChecks )->contains( 'status', 'fail' );
			@endphp
			<x-artisanpack-button
				wire:click="confirmPublish"
				:label="'' !== $scheduleDate && '' !== $scheduleTime ? __( 'Schedule' ) : __( 'Publish' )"
				color="primary"
				class="w-full"
				:disabled="$hasFailures"
			/>
			@if ( $hasFailures )
				<x-artisanpack-alert
					:title="__( 'Fix all required checks before publishing.' )"
					color="error"
					icon="o-exclamation-triangle"
					class="mt-2"
				/>
			@endif
		</x-slot:actions>
	</x-artisanpack-drawer>
</div>

@script
<script>
	const handler = ( event ) => {
		const isMac = navigator.platform.toUpperCase().indexOf( 'MAC' ) >= 0;
		const modKey = isMac ? event.metaKey : event.ctrlKey;

		if ( modKey && 's' === event.key ) {
			event.preventDefault();
			$wire.save();
		}

		if ( modKey && !event.shiftKey && 'z' === event.key ) {
			event.preventDefault();
			$wire.dispatch( 'editor-undo' );
		}

		if ( modKey && event.shiftKey && 'z' === event.key ) {
			event.preventDefault();
			$wire.dispatch( 'editor-redo' );
		}

		if ( modKey && '\\' === event.key ) {
			event.preventDefault();
			$wire.toggleSidebar();
		}

		if ( 'Escape' === event.key ) {
			$wire.deselectBlock();
		}
	};

	document.addEventListener( 'keydown', handler );

	// Autosave timer
	const interval = parseInt( $wire.$el.dataset.autosaveInterval, 10 ) || 60;
	const autosaveTimer = setInterval( () => $wire.autosave(), interval * 1000 );

	$cleanup( () => {
		document.removeEventListener( 'keydown', handler );
		clearInterval( autosaveTimer );
	} );
</script>
@endscript
