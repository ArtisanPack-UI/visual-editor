{{--
 * Canvas Empty State Component
 *
 * Displays a placeholder when the editor canvas has no blocks,
 * with a message and an inserter trigger button.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data
	{{ $attributes->merge( [ 'class' => 'flex flex-col items-center justify-center py-16 text-base-content/40' ] ) }}
	role="status"
>
	<svg
		class="w-12 h-12 mb-4"
		fill="none"
		viewBox="0 0 24 24"
		stroke="currentColor"
		stroke-width="1.5"
		aria-hidden="true"
		focusable="false"
	>
		<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
	</svg>

	<h3 class="text-lg font-semibold text-base-content/60 mb-1">
		{{ $title ?? __( 'visual-editor::ve.empty_canvas_title' ) }}
	</h3>

	<p class="text-sm mb-6">
		{{ $description ?? __( 'visual-editor::ve.empty_canvas_description' ) }}
	</p>

	<button
		type="button"
		class="btn btn-primary btn-sm gap-2"
		x-on:click="if ( Alpine.store( 'editor' ) ) { Alpine.store( 'editor' ).showInserter = true; }"
		aria-label="{{ $buttonLabel ?? __( 'visual-editor::ve.add_block' ) }}"
	>
		@if ( $icon )
			<x-artisanpack-icon :name="$icon" class="w-4 h-4" />
		@else
			<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
				<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
			</svg>
		@endif
		{{ $buttonLabel ?? __( 'visual-editor::ve.add_block' ) }}
	</button>
</div>
