{{--
 * Insertion Point Component
 *
 * A "+" button displayed between blocks in the editor canvas
 * to trigger the inline block inserter at a specific position.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{ hovered: false }"
	{{ $attributes->merge( [ 'class' => 'relative group py-1' ] ) }}
	x-on:mouseenter="hovered = true"
	x-on:mouseleave="hovered = false"
	role="presentation"
>
	{{-- Horizontal line indicator --}}
	<div
		class="absolute inset-x-0 top-1/2 -translate-y-px h-0.5 bg-primary/0 group-hover:bg-primary/30 transition-colors"
		aria-hidden="true"
	></div>

	{{-- Insert button --}}
	<div class="flex justify-center">
		<button
			type="button"
			class="w-6 h-6 rounded-full bg-primary text-primary-content flex items-center justify-center opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity shadow-sm hover:shadow-md"
			x-on:click="$dispatch( 've-insertion-point-click', { index: {{ Js::from( $index ) }}, uuid: {{ Js::from( $uuid ) }} } )"
			aria-label="{{ $label ?? __( 'visual-editor::ve.add_block_after', [ 'index' => $index ] ) }}"
		>
			<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true" focusable="false">
				<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
			</svg>
		</button>
	</div>
</div>
