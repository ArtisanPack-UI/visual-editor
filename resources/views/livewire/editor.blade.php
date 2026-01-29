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
use ArtisanPackUI\VisualEditor\Registries\BlockRegistry;
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
	 * The content blocks data.
	 *
	 * @since 1.1.0
	 *
	 * @var array
	 */
	public array $blocks = [];

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
	 * Whether the settings drawer is visible.
	 *
	 * @since 1.3.0
	 *
	 * @var bool
	 */
	public bool $showSettingsDrawer = false;

	/**
	 * The active tab within the settings drawer.
	 *
	 * @since 1.3.0
	 *
	 * @var string
	 */
	public string $settingsDrawerTab = 'block';

	/**
	 * The content title for page settings.
	 *
	 * @since 1.4.0
	 *
	 * @var string
	 */
	public string $contentTitle = '';

	/**
	 * The content slug for page settings.
	 *
	 * @since 1.4.0
	 *
	 * @var string
	 */
	public string $contentSlug = '';

	/**
	 * The content excerpt for page settings.
	 *
	 * @since 1.4.0
	 *
	 * @var string
	 */
	public string $contentExcerpt = '';

	/**
	 * The content meta title for page settings.
	 *
	 * @since 1.4.0
	 *
	 * @var string
	 */
	public string $contentMetaTitle = '';

	/**
	 * The content meta description for page settings.
	 *
	 * @since 1.4.0
	 *
	 * @var string
	 */
	public string $contentMetaDescription = '';

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
		$this->content                = $content;
		$this->blocks                 = $content->blocks ?? [];
		$this->contentTitle           = $content->title ?? '';
		$this->contentSlug            = $content->slug ?? '';
		$this->contentExcerpt         = $content->excerpt ?? '';
		$this->contentMetaTitle       = $content->meta_title ?? '';
		$this->contentMetaDescription = $content->meta_description ?? '';
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
				'title'            => $this->contentTitle,
				'slug'             => $this->contentSlug,
				'excerpt'          => $this->contentExcerpt,
				'meta_title'       => $this->contentMetaTitle,
				'meta_description' => $this->contentMetaDescription,
				'blocks'           => $this->blocks,
			], auth()->id() );

			$this->content->refresh();

			$this->saveStatus = 'saved';
			$this->isDirty    = false;
			$this->lastSaved  = now()->format( 'g:i A' );
		} catch ( \Throwable $e ) {
			report( $e );
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
		$service                   = app( ContentService::class );
		$this->prePublishChecks    = $service->runPrePublishChecks( $this->content );
		$this->showPrePublishPanel = true;
		$this->showSettingsDrawer  = false;
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

			// Save current content first.
			$service->saveDraft( $this->content, [
				'title'            => $this->contentTitle,
				'slug'             => $this->contentSlug,
				'excerpt'          => $this->contentExcerpt,
				'meta_title'       => $this->contentMetaTitle,
				'meta_description' => $this->contentMetaDescription,
				'blocks'           => $this->blocks,
			], auth()->id() );

			if ( '' !== $this->scheduleDate && '' !== $this->scheduleTime ) {
				try {
					$publishAt = Carbon::parse( $this->scheduleDate . ' ' . $this->scheduleTime );
				} catch ( \Exception $e ) {
					session()->flash( 'error', __( 'Invalid schedule date or time.' ) );
					return;
				}
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
			report( $e );
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
				'blocks' => $this->blocks,
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
				'title'            => $this->contentTitle,
				'slug'             => $this->contentSlug,
				'excerpt'          => $this->contentExcerpt,
				'meta_title'       => $this->contentMetaTitle,
				'meta_description' => $this->contentMetaDescription,
				'blocks'           => $this->blocks,
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
		$this->activeBlockId     = null;
		$this->settingsDrawerTab = 'page';
	}

	/**
	 * Handle the blocks being updated.
	 *
	 * @since 1.1.0
	 *
	 * @param array $blocks The updated blocks data.
	 *
	 * @return void
	 */
	#[On( 'blocks-updated' )]
	public function onBlocksUpdated( array $blocks ): void
	{
		$this->blocks     = $blocks;
		$this->isDirty    = true;
		$this->saveStatus = 'unsaved';
	}

	/**
	 * Toggle the settings drawer open/closed.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function toggleSettingsDrawer(): void
	{
		$this->showSettingsDrawer = !$this->showSettingsDrawer;

		if ( $this->showSettingsDrawer ) {
			$this->showPrePublishPanel = false;
		}
	}

	/**
	 * Set the active settings drawer tab.
	 *
	 * @since 1.3.0
	 *
	 * @param string $tab The tab to activate ('block' or 'page').
	 *
	 * @return void
	 */
	public function setSettingsDrawerTab( string $tab ): void
	{
		$this->settingsDrawerTab = $tab;
	}

	/**
	 * Handle block selection from the canvas.
	 *
	 * Opens the settings drawer and switches to the block tab.
	 *
	 * @since 1.3.0
	 *
	 * @param string $blockId The selected block ID.
	 *
	 * @return void
	 */
	#[On( 'block-selected' )]
	public function onBlockSelected( string $blockId ): void
	{
		$this->activeBlockId      = $blockId;
		$this->showSettingsDrawer = true;
		$this->settingsDrawerTab  = 'block';

		if ( $this->showPrePublishPanel ) {
			$this->showPrePublishPanel = false;
		}
	}

	/**
	 * Handle the sidebar toggle event from the toolbar.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	#[On( 'editor-toggle-sidebar' )]
	public function onToggleSidebar(): void
	{
		$this->toggleSidebar();
	}

	/**
	 * Handle the settings toggle event from the toolbar.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	#[On( 'editor-toggle-settings' )]
	public function onToggleSettings(): void
	{
		$this->toggleSettingsDrawer();
	}

	/**
	 * Get the active block's registry configuration.
	 *
	 * @since 1.4.0
	 *
	 * @return array|null
	 */
	public function getActiveBlockConfig(): ?array
	{
		if ( null === $this->activeBlockId ) {
			return null;
		}

		$block = collect( $this->blocks )->firstWhere( 'id', $this->activeBlockId );

		if ( null === $block ) {
			return null;
		}

		return app( BlockRegistry::class )->get( $block['type'] ?? '' );
	}

	/**
	 * Update a setting on the active block.
	 *
	 * @since 1.4.0
	 *
	 * @param string $key   The setting key.
	 * @param mixed  $value The setting value.
	 *
	 * @return void
	 */
	public function updateBlockSetting( string $key, mixed $value ): void
	{
		if ( null === $this->activeBlockId ) {
			return;
		}

		$blocks = $this->blocks;

		foreach ( $blocks as $index => $block ) {
			if ( ( $block['id'] ?? '' ) === $this->activeBlockId ) {
				$blocks[ $index ]['settings'][ $key ] = $value;

				break;
			}
		}

		$this->blocks     = $blocks;
		$this->isDirty    = true;
		$this->saveStatus = 'unsaved';
	}

	/**
	 * Handle page setting property updates and mark as dirty.
	 *
	 * @since 1.4.0
	 *
	 * @param string $property The property name that was updated.
	 *
	 * @return void
	 */
	public function updatedContentTitle(): void
	{
		$this->isDirty    = true;
		$this->saveStatus = 'unsaved';
	}

	/**
	 * Handle content slug updates and mark as dirty.
	 *
	 * @since 1.4.0
	 *
	 * @return void
	 */
	public function updatedContentSlug(): void
	{
		$this->isDirty    = true;
		$this->saveStatus = 'unsaved';
	}

	/**
	 * Handle content excerpt updates and mark as dirty.
	 *
	 * @since 1.4.0
	 *
	 * @return void
	 */
	public function updatedContentExcerpt(): void
	{
		$this->isDirty    = true;
		$this->saveStatus = 'unsaved';
	}

	/**
	 * Handle content meta title updates and mark as dirty.
	 *
	 * @since 1.4.0
	 *
	 * @return void
	 */
	public function updatedContentMetaTitle(): void
	{
		$this->isDirty    = true;
		$this->saveStatus = 'unsaved';
	}

	/**
	 * Handle content meta description updates and mark as dirty.
	 *
	 * @since 1.4.0
	 *
	 * @return void
	 */
	public function updatedContentMetaDescription(): void
	{
		$this->isDirty    = true;
		$this->saveStatus = 'unsaved';
	}
}; ?>

<div class="ve-editor flex h-screen flex-col overflow-hidden bg-gray-100"
	 data-theme="light"
	 data-autosave-interval="{{ config( 'artisanpack.visual-editor.editor.autosave_interval', 60 ) }}">
	{{-- Top Toolbar --}}
	<livewire:visual-editor::toolbar
		:save-status="$saveStatus"
		:content-title="$content->title"
		:content-status="$content->status"
		:sidebar-open="$sidebarOpen"
		:settings-open="$showSettingsDrawer"
	/>

	{{-- Main Editor Area --}}
	<div class="flex flex-1 overflow-hidden">
		{{-- Left Sidebar --}}
		@if ( $sidebarOpen )
			<livewire:visual-editor::sidebar
				:active-tab="$sidebarTab"
				:blocks="$blocks"
				:active-block-id="$activeBlockId"
			/>
		@endif

		{{-- Canvas Area --}}
		<div class="flex flex-1 flex-col overflow-hidden">
			<livewire:visual-editor::canvas
				:blocks="$blocks"
				:active-block-id="$activeBlockId"
			/>

			{{-- Status Bar --}}
			<livewire:visual-editor::status-bar
				:save-status="$saveStatus"
				:content-status="$content->status"
				:last-saved="$lastSaved"
			/>
		</div>

		{{-- Right Settings Panel (inline) --}}
		@if ( $showSettingsDrawer )
			<div class="ve-settings-panel flex w-72 shrink-0 flex-col border-l border-gray-200 bg-white">
				{{-- Settings Panel Tabs --}}
				<div class="flex border-b border-gray-200">
					@php
						$settingsTabs = [
							'block' => __( 'Block' ),
							'page'  => __( 'Page' ),
						];
					@endphp
					@foreach ( $settingsTabs as $key => $label )
						<button
							wire:click="setSettingsDrawerTab( '{{ $key }}' )"
							class="flex-1 border-b-2 px-3 py-2 text-center text-xs font-medium transition-colors
								{{ $settingsDrawerTab === $key ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
						>
							{{ $label }}
						</button>
					@endforeach
				</div>

				{{-- Tab Content --}}
				<div class="flex-1 overflow-y-auto p-3">
					@if ( 'block' === $settingsDrawerTab )
						@if ( null !== $activeBlockId )
							@php
								$activeBlockConfig  = $this->getActiveBlockConfig();
								$settingsSchema     = $activeBlockConfig['settings_schema'] ?? [];
								$activeBlock        = collect( $blocks )->firstWhere( 'id', $activeBlockId );
								$currentSettings    = $activeBlock['settings'] ?? [];
							@endphp

							@if ( empty( $settingsSchema ) )
								<p class="text-sm text-gray-500">
									{{ __( 'This block has no configurable settings.' ) }}
								</p>
							@else
								<div class="space-y-4">
									@foreach ( $settingsSchema as $settingKey => $schema )
										@php
											$fieldType    = $schema['type'] ?? 'text';
											$fieldLabel   = $schema['label'] ?? ucfirst( str_replace( '_', ' ', $settingKey ) );
											$fieldOptions = $schema['options'] ?? [];
											$fieldDefault = $schema['default'] ?? '';
											$fieldValue   = $currentSettings[ $settingKey ] ?? $fieldDefault;
										@endphp

										@if ( 'select' === $fieldType )
											@php
												$selectOptions = collect( $fieldOptions )->map( fn ( $opt ) => [
													'id'   => $opt,
													'name' => ucfirst( $opt ),
												] )->all();
											@endphp
											<x-artisanpack-select
												:label="__( $fieldLabel )"
												:options="$selectOptions"
												option-value="id"
												option-label="name"
												wire:change="updateBlockSetting( '{{ $settingKey }}', $event.target.value )"
												:selected="$fieldValue"
											/>
										@elseif ( 'alignment' === $fieldType )
											@php
												$alignmentOptions = [
													[ 'id' => 'left', 'name' => __( 'Left' ) ],
													[ 'id' => 'center', 'name' => __( 'Center' ) ],
													[ 'id' => 'right', 'name' => __( 'Right' ) ],
												];
											@endphp
											<x-artisanpack-select
												:label="__( $fieldLabel )"
												:options="$alignmentOptions"
												option-value="id"
												option-label="name"
												wire:change="updateBlockSetting( '{{ $settingKey }}', $event.target.value )"
												:selected="$fieldValue"
											/>
										@elseif ( 'toggle' === $fieldType )
											<x-artisanpack-toggle
												:label="__( $fieldLabel )"
												:checked="(bool) $fieldValue"
												wire:change="updateBlockSetting( '{{ $settingKey }}', $event.target.checked )"
											/>
										@elseif ( 'color_picker' === $fieldType )
											<x-artisanpack-input
												type="color"
												:label="__( $fieldLabel )"
												:value="$fieldValue"
												wire:change="updateBlockSetting( '{{ $settingKey }}', $event.target.value )"
											/>
										@elseif ( 'url' === $fieldType )
											<x-artisanpack-input
												type="url"
												:label="__( $fieldLabel )"
												:value="$fieldValue"
												wire:change="updateBlockSetting( '{{ $settingKey }}', $event.target.value )"
											/>
										@elseif ( 'textarea' === $fieldType )
											<div>
												<label class="mb-1 block text-sm font-medium text-gray-700">{{ __( $fieldLabel ) }}</label>
												<textarea
													wire:change="updateBlockSetting( '{{ $settingKey }}', $event.target.value )"
													class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
													rows="3"
												>{{ $fieldValue }}</textarea>
											</div>
										@else
											<x-artisanpack-input
												:label="__( $fieldLabel )"
												:value="$fieldValue"
												wire:change="updateBlockSetting( '{{ $settingKey }}', $event.target.value )"
											/>
										@endif
									@endforeach
								</div>
							@endif
						@else
							<p class="text-sm text-gray-500">
								{{ __( 'Select a block on the canvas to view its settings.' ) }}
							</p>
						@endif
					@elseif ( 'page' === $settingsDrawerTab )
						<div class="space-y-4">
							<x-artisanpack-input
								wire:model.blur="contentTitle"
								:label="__( 'Title' )"
							/>

							<x-artisanpack-input
								wire:model.blur="contentSlug"
								:label="__( 'Slug' )"
							/>

							<div>
								<label class="mb-1 block text-sm font-medium text-gray-700">{{ __( 'Excerpt' ) }}</label>
								<textarea
									wire:model.blur="contentExcerpt"
									class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
									rows="3"
									placeholder="{{ __( 'A short summary of the content...' ) }}"
								></textarea>
							</div>

							<div class="border-t border-gray-200 pt-4">
								<x-artisanpack-heading level="3" size="text-sm" semibold class="mb-3">
									{{ __( 'SEO' ) }}
								</x-artisanpack-heading>

								<div class="space-y-4">
									<x-artisanpack-input
										wire:model.blur="contentMetaTitle"
										:label="__( 'Meta Title' )"
									/>

									<div>
										<label class="mb-1 block text-sm font-medium text-gray-700">{{ __( 'Meta Description' ) }}</label>
										<textarea
											wire:model.blur="contentMetaDescription"
											class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
											rows="3"
											placeholder="{{ __( 'A description for search engines...' ) }}"
										></textarea>
									</div>
								</div>
							</div>
						</div>
					@endif
				</div>
			</div>
		@endif
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

		if ( modKey && event.shiftKey && ',' === event.key ) {
			event.preventDefault();
			$wire.toggleSettingsDrawer();
		}

		if ( 'Escape' === event.key ) {
			$wire.deselectBlock();
		}
	};

	document.addEventListener( 'keydown', handler );

	// Autosave timer
	const interval = parseInt( $wire.$el.dataset.autosaveInterval, 10 ) || 60;
	const autosaveTimer = setInterval( () => $wire.autosave(), interval * 1000 );

	// Clean up event listeners and timers on navigation
	const cleanup = () => {
		document.removeEventListener( 'keydown', handler );
		clearInterval( autosaveTimer );
	};

	document.addEventListener( 'livewire:navigating', cleanup, { once: true } );
</script>
@endscript
