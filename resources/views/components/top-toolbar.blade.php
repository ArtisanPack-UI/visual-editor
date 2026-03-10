{{--
 * Top Toolbar Component
 *
 * The main editor toolbar at the top of the editor layout.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

@php
	use ArtisanPackUI\VisualEditor\View\Components\EditorState;

	$savingStatus = EditorState::saveStatusMap()['SAVING'];
@endphp

<div
	id="{{ $uuid }}"
	x-data
	{{ $attributes->merge( [ 'class' => 'flex items-center justify-between px-3 py-1.5 border-b border-base-300 bg-base-100' ] ) }}
	role="toolbar"
	aria-label="{{ $label ?? __( 'visual-editor::ve.editor_toolbar' ) }}"
	aria-orientation="horizontal"
>
	{{-- Left section: Inserter + Undo/Redo --}}
	<div class="flex items-center gap-1">
		@if ( $showInserterToggle )
			<button
				type="button"
				class="btn btn-ghost btn-sm btn-square"
				x-on:click="if ( Alpine.store( 'editor' ) ) { Alpine.store( 'editor' ).leftSidebarTab = 'blocks'; Alpine.store( 'editor' ).toggleInserter(); }"
				aria-label="{{ __( 'visual-editor::ve.toggle_inserter' ) }}"
				:aria-pressed="Alpine.store( 'editor' ) ? Alpine.store( 'editor' ).showInserter : false"
			>
				<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
					<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
				</svg>
			</button>

			<button
				type="button"
				class="btn btn-ghost btn-sm btn-square"
				x-on:click="if ( Alpine.store( 'editor' ) ) { Alpine.store( 'editor' ).openLayers(); }"
				aria-label="{{ __( 'visual-editor::ve.open_layers' ) }}"
				x-bind:class="Alpine.store( 'editor' ) && Alpine.store( 'editor' ).showInserter && 'layers' === Alpine.store( 'editor' ).leftSidebarTab ? 'bg-base-200' : ''"
			>
				<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false">
					<path stroke-linecap="round" stroke-linejoin="round" d="M6.429 9.75 2.25 12l4.179 2.25m0-4.5 5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L12 12.75l-5.571-3m11.142 0 4.179 2.25L12 17.25l-9.75-5.25 4.179-2.25m11.142 0 4.179 2.25L12 22.5l-9.75-5.25 4.179-2.25" />
				</svg>
			</button>
		@endif

		@if ( $showUndoRedo )
			<div class="w-px h-5 bg-base-300 mx-0.5" aria-hidden="true"></div>
			<button
				type="button"
				class="btn btn-ghost btn-sm btn-square"
				x-on:click="if ( Alpine.store( 'editor' ) ) { Alpine.store( 'editor' ).undo(); }"
				:disabled="! ( Alpine.store( 'editor' ) && Alpine.store( 'editor' ).canUndo() )"
				aria-label="{{ __( 'visual-editor::ve.undo' ) }}"
			>
				<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
					<path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
				</svg>
			</button>
			<button
				type="button"
				class="btn btn-ghost btn-sm btn-square"
				x-on:click="if ( Alpine.store( 'editor' ) ) { Alpine.store( 'editor' ).redo(); }"
				:disabled="! ( Alpine.store( 'editor' ) && Alpine.store( 'editor' ).canRedo() )"
				aria-label="{{ __( 'visual-editor::ve.redo' ) }}"
			>
				<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
					<path stroke-linecap="round" stroke-linejoin="round" d="m15 15 6-6m0 0-6-6m6 6H9a6 6 0 0 0 0 12h3" />
				</svg>
			</button>
		@endif
	</div>

	{{-- Center section: custom slot --}}
	<div class="flex items-center">
		{{ $center ?? '' }}
	</div>

	{{-- Right section: Device preview + Save + Settings --}}
	<div class="flex items-center gap-1">
		@if ( $showDevicePreview )
			<x-ve-device-preview-buttons />
		@endif

		@if ( $showSaveButton )
			<div class="w-px h-5 bg-base-300 mx-0.5" aria-hidden="true"></div>
			<button
				type="button"
				class="btn btn-primary btn-sm"
				x-on:click="$dispatch( 've-save-request' )"
				:disabled="Alpine.store( 'editor' ) && {{ Js::from( $savingStatus ) }} === Alpine.store( 'editor' ).saveStatus"
			>
				<span
					x-show="! ( Alpine.store( 'editor' ) && {{ Js::from( $savingStatus ) }} === Alpine.store( 'editor' ).saveStatus )"
				>{{ __( 'visual-editor::ve.save' ) }}</span>
				<span
					x-show="Alpine.store( 'editor' ) && {{ Js::from( $savingStatus ) }} === Alpine.store( 'editor' ).saveStatus"
					x-cloak
				>{{ __( 'visual-editor::ve.saving' ) }}</span>
			</button>
		@endif

		@if ( $showSettingsToggle )
			<button
				type="button"
				class="btn btn-ghost btn-sm btn-square"
				x-on:click="if ( Alpine.store( 'editor' ) ) { Alpine.store( 'editor' ).toggleSidebar(); }"
				aria-label="{{ __( 'visual-editor::ve.toggle_sidebar' ) }}"
				:aria-pressed="Alpine.store( 'editor' ) ? Alpine.store( 'editor' ).showSidebar : false"
			>
				<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false">
					<path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.212-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
					<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
				</svg>
			</button>
		@endif
	</div>

	@if ( function_exists( 'doAction' ) )
		@action('ap.visualEditor.toolbar.rendered')
	@endif
</div>
