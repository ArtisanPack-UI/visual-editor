{{--
 * Font Size Picker Component
 *
 * A preset size picker with optional custom input mode.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{
		value: {{ Js::from( $value ?? '' ) }},
		mode: {{ Js::from( null !== $activePreset() ? 'preset' : ( $value ? 'custom' : 'preset' ) ) }},
		customValue: '',
		customUnit: {{ Js::from( $unit ) }},
		presets: {{ Js::from( $presets ) }},
		selectPreset( key ) {
			this.value = this.presets[key]
			this.mode = 'preset'
			$dispatch( 've-font-size-change', { value: this.value, preset: key, isCustom: false } )
		},
		toggleCustom() {
			this.mode = this.mode === 'custom' ? 'preset' : 'custom'
		},
		onCustomInput() {
			this.value = this.customValue + this.customUnit
			$dispatch( 've-font-size-change', { value: this.value, preset: null, isCustom: true } )
		},
		isActivePreset( key ) {
			return this.mode === 'preset' && this.value === this.presets[key]
		}
	}"
	{{ $attributes->merge( [ 'class' => 'flex flex-col gap-2' ] ) }}
>
	@if ( $label )
		<label class="text-xs font-medium text-base-content/60">
			{{ $label }}
		</label>
	@endif

	<div class="flex items-center gap-2">
		{{-- Preset Buttons --}}
		<div
			class="inline-flex rounded-lg border border-base-300 bg-base-200 p-1"
			role="radiogroup"
			aria-label="{{ $label ?? __( 'visual-editor::ve.font_size' ) }}"
		>
			@foreach ( $presets as $presetKey => $presetValue )
				<button
					type="button"
					x-on:click="selectPreset( '{{ $presetKey }}' )"
					:class="isActivePreset( '{{ $presetKey }}' ) ? 'bg-primary text-primary-content shadow-sm' : 'text-base-content/50 hover:text-base-content/80 hover:bg-base-300'"
					class="flex items-center justify-center min-w-[2.25rem] h-9 px-3 rounded-md text-sm font-medium transition-all"
					role="radio"
					:aria-checked="isActivePreset( '{{ $presetKey }}' ) ? 'true' : 'false'"
					aria-label="{{ __( 'visual-editor::ve.font_size' ) }} {{ $presetKey }}"
					title="{{ $presetValue }}"
				>
					{{ $presetKey }}
				</button>
			@endforeach
		</div>

		@if ( $showCustom )
			{{-- Custom Toggle --}}
			<button
				type="button"
				x-on:click="toggleCustom()"
				:class="mode === 'custom' ? 'text-primary' : 'text-base-content/40 hover:text-base-content/70'"
				class="flex items-center justify-center h-9 w-9 rounded-md transition-colors"
				aria-label="{{ __( 'visual-editor::ve.custom_size' ) }}"
				title="{{ __( 'visual-editor::ve.custom_size' ) }}"
			>
				<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
				</svg>
			</button>
		@endif
	</div>

	@if ( $showCustom )
		{{-- Custom Input --}}
		<div
			x-show="mode === 'custom'"
			x-collapse
			class="flex items-center gap-1"
		>
			<div class="flex-1">
				<x-artisanpack-input
					type="number"
					x-model="customValue"
					x-on:input="onCustomInput()"
					:placeholder="__( 'visual-editor::ve.size' )"
					size="sm"
					:aria-label="__( 'visual-editor::ve.custom_font_size_value' )"
				/>
			</div>
			<div class="w-16">
				<x-artisanpack-select
					:options="array_map( fn( $u ) => [ 'id' => $u, 'name' => $u ], $units )"
					option-value="id"
					option-label="name"
					x-model="customUnit"
					x-on:change="onCustomInput()"
					size="sm"
					:aria-label="__( 'visual-editor::ve.font_size_unit' )"
				/>
			</div>
		</div>
	@endif

	@if ( $hint )
		<div class="{{ $hintClass }}">{{ $hint }}</div>
	@endif
</div>
