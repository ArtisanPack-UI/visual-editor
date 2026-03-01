{{--
 * Editor Layout Component
 *
 * The top-level layout shell for the visual editor, arranging
 * the toolbar, canvas, sidebar, and status bar.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{
		get showSidebar() {
			return Alpine.store( 'editor' ) ? Alpine.store( 'editor' ).showSidebar : {{ Js::from( true ) }};
		},
		get showInserter() {
			return Alpine.store( 'editor' ) ? Alpine.store( 'editor' ).showInserter : false;
		},
	}"
	{{ $attributes->merge( [ 'class' => 'flex flex-col h-full bg-base-200' ] ) }}
	aria-label="{{ $label ?? __( 'visual-editor::ve.visual_editor' ) }}"
>
	{{-- Top toolbar --}}
	<div class="shrink-0">
		{{ $toolbar ?? '' }}
	</div>

	{{-- Main content area: left sidebar + canvas + right sidebar --}}
	<div class="flex flex-1 min-h-0 {{ 'left' === $sidebarPosition ? 'flex-row-reverse' : '' }}">
		{{-- Left Sidebar (inserter panel) --}}
		@if ( isset( $leftSidebar ) )
			<div
				x-show="showInserter"
				x-transition:enter="transition ease-out duration-200"
				x-transition:enter-start="opacity-0 -translate-x-4"
				x-transition:enter-end="opacity-100 translate-x-0"
				x-transition:leave="transition ease-in duration-150"
				x-transition:leave-start="opacity-100 translate-x-0"
				x-transition:leave-end="opacity-0 -translate-x-4"
				class="shrink-0 overflow-hidden border-r border-base-300"
				style="width: {{ $leftSidebarWidth }}"
			>
				{{ $leftSidebar }}
			</div>
		@endif

		{{-- Canvas area --}}
		<div class="flex-1 min-w-0 overflow-auto">
			{{ $canvas ?? '' }}
		</div>

		{{-- Sidebar --}}
		@if ( $sidebarCollapsible )
			<div
				x-show="showSidebar"
				x-transition:enter="transition ease-out duration-200"
				x-transition:enter-start="opacity-0 translate-x-4"
				x-transition:enter-end="opacity-100 translate-x-0"
				x-transition:leave="transition ease-in duration-150"
				x-transition:leave-start="opacity-100 translate-x-0"
				x-transition:leave-end="opacity-0 translate-x-4"
				class="shrink-0 overflow-hidden"
				style="width: {{ $sidebarWidth }}"
			>
				{{ $sidebar ?? '' }}
			</div>
		@else
			<div
				class="shrink-0 overflow-hidden"
				style="width: {{ $sidebarWidth }}"
			>
				{{ $sidebar ?? '' }}
			</div>
		@endif
	</div>

	{{-- Status bar --}}
	<div class="shrink-0">
		{{ $statusbar ?? '' }}
	</div>

	{{-- Media picker bridge (connects inspector fields to the media library modal) --}}
	@if ( class_exists( \ArtisanPackUI\MediaLibrary\Livewire\Components\MediaModal::class ) )
		@livewire( 'visual-editor::media-picker' )
	@endif
</div>
