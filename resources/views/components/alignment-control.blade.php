{{--
 * Alignment Control Component
 *
 * A button group for alignment selection (horizontal, vertical, or matrix mode).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{
		selected: '{{ $value ?? '' }}',
		select( value ) {
			this.selected = value
			$dispatch( 've-alignment-change', { value: value } )
		}
	}"
	{{ $attributes->merge( [ 'class' => 'flex flex-col gap-2' ] ) }}
>
	@if ( $label )
		<label class="text-xs font-medium text-base-content/60">
			{{ $label }}
		</label>
	@endif

	@if ( 'matrix' === $mode )
		{{-- Matrix mode: 3x3 grid --}}
		<div
			class="inline-grid grid-cols-3 gap-1 rounded-lg border border-base-300 bg-base-200 p-2"
			role="radiogroup"
			aria-label="{{ $label ?? __( 'visual-editor::ve.alignment' ) }}"
		>
			@foreach ( $matrixOptions() as $option )
				<button
					type="button"
					x-on:click="select( '{{ $option['value'] }}' )"
					:class="selected === '{{ $option['value'] }}' ? 'bg-primary text-primary-content shadow-sm' : 'text-base-content/40 hover:text-base-content/70 hover:bg-base-300'"
					class="flex h-8 w-8 items-center justify-center rounded-md transition-all"
					role="radio"
					:aria-checked="selected === '{{ $option['value'] }}' ? 'true' : 'false'"
					aria-label="{{ ucfirst( $option['vertical'] ) }} {{ $option['horizontal'] }}"
				>
					<svg class="h-3 w-3" viewBox="0 0 10 10" fill="currentColor">
						<circle cx="5" cy="5" r="3" />
					</svg>
				</button>
			@endforeach
		</div>
	@else
		{{-- Horizontal or vertical mode --}}
		<div
			class="inline-flex rounded-lg border border-base-300 bg-base-200 p-1"
			role="radiogroup"
			aria-label="{{ $label ?? __( 'visual-editor::ve.alignment' ) }}"
		>
			@foreach ( $resolvedOptions() as $option )
				<button
					type="button"
					x-on:click="select( '{{ $option }}' )"
					:class="selected === '{{ $option }}' ? 'bg-primary text-primary-content shadow-sm' : 'text-base-content/50 hover:text-base-content/80 hover:bg-base-300'"
					class="relative flex items-center justify-center w-10 h-10 rounded-md transition-all"
					role="radio"
					:aria-checked="selected === '{{ $option }}' ? 'true' : 'false'"
					aria-label="{{ ucfirst( $option ) }}"
				>
					@switch ( $option )
						@case( 'left' )
							<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-width="2" d="M3 6h18M3 12h12M3 18h16" />
							</svg>
							@break
						@case( 'center' )
							<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-width="2" d="M3 6h18M6 12h12M4 18h16" />
							</svg>
							@break
						@case( 'right' )
							<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-width="2" d="M3 6h18M9 12h12M5 18h16" />
							</svg>
							@break
						@case( 'justify' )
							<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-width="2" d="M3 6h18M3 12h18M3 18h18" />
							</svg>
							@break
						@case( 'top' )
							<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-width="2" d="M3 4h18M12 8v12" />
							</svg>
							@break
						@case( 'bottom' )
							<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-width="2" d="M3 20h18M12 4v12" />
							</svg>
							@break
						@default
							<span class="text-sm font-medium">{{ ucfirst( $option ) }}</span>
					@endswitch
				</button>
			@endforeach
		</div>
	@endif
</div>
