{{--
 * Unit Control Component
 *
 * A value input paired with a unit dropdown (px, em, rem, %, vw, vh).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{
		value: {{ Js::from( $value ) }},
		unit: {{ Js::from( $unit ) }},
		dispatch() {
			$dispatch( 've-unit-change', { value: this.value, unit: this.unit } )
		}
	}"
	{{ $attributes->merge( [ 'class' => 'flex flex-col gap-1' ] ) }}
	role="group"
	@if ( $label )
		aria-label="{{ $label }}"
	@endif
>
	@if ( $label )
		<label class="text-xs font-medium text-base-content/60">
			{{ $label }}
		</label>
	@endif

	<div class="flex items-stretch gap-1">
		<div class="flex-1">
			<x-artisanpack-input
				type="number"
				x-model.number="value"
				x-on:input="dispatch()"
				:min="$min"
				:max="$max"
				:step="$step"
				size="sm"
				:aria-label="$label ? $label . ' ' . __( 'value' ) : __( 'Value' )"
			/>
		</div>

		<div class="w-20">
			<x-artisanpack-select
				:options="$unitOptions()"
				option-value="id"
				option-label="name"
				x-model="unit"
				x-on:change="dispatch()"
				size="sm"
				:aria-label="__( 'Unit' )"
			/>
		</div>
	</div>

	@if ( $hint )
		<div class="{{ $hintClass }}">{{ $hint }}</div>
	@endif
</div>
