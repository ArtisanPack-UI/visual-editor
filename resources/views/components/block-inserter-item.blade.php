{{--
 * Block Inserter Item Component
 *
 * A single block entry within the inserter list, showing icon,
 * name, and description. Supports click-to-insert and drag-to-insert.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data
	{{ $attributes->merge( [ 'class' => 'flex flex-col items-center gap-1 p-2 rounded-lg cursor-pointer hover:bg-base-200 transition-colors text-center' ] ) }}
	role="option"
	tabindex="0"
	@if ( $draggable )
		draggable="true"
		x-on:dragstart="
			$event.dataTransfer.setData( 'application/ve-block', JSON.stringify( {
				type: {{ Js::from( $name ) }},
				attributes: {},
				innerBlocks: [],
			} ) );
			$event.dataTransfer.effectAllowed = 'copy';
		"
		title="{{ __( 'visual-editor::ve.drag_to_insert' ) }}"
	@endif
	x-on:click="
		$dispatch( 've-block-inserter-select', {
			type: {{ Js::from( $name ) }},
			label: {{ Js::from( $label ?? $name ) }},
			category: {{ Js::from( $category ) }},
		} );
	"
	x-on:keydown.enter="
		$dispatch( 've-block-inserter-select', {
			type: {{ Js::from( $name ) }},
			label: {{ Js::from( $label ?? $name ) }},
			category: {{ Js::from( $category ) }},
		} );
	"
	aria-label="{{ __( 'visual-editor::ve.click_to_insert', [ 'block' => $label ?? $name ] ) }}"
>
	{{-- Block icon --}}
	<div class="w-10 h-10 flex items-center justify-center rounded bg-base-200 text-base-content/60">
		@if ( $renderedIcon )
			{!! $renderedIcon !!}
		@else
			<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false">
				<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6z" />
			</svg>
		@endif
	</div>

	{{-- Block name --}}
	<span class="text-xs font-medium text-base-content leading-tight">
		{{ $label ?? $name }}
	</span>

	{{-- Description tooltip --}}
	@if ( $description )
		<span class="sr-only">{{ $description }}</span>
	@endif
</div>
