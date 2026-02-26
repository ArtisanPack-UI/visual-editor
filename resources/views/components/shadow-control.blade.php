<div
	id="{{ $uuid }}"
	x-data="{
		value: {{ Js::from( $value ) }},
		showCustom: false,
		presets: {{ Js::from( $presets ) }},
		selectPreset( key ) {
			this.value = this.presets[key] ?? null;
			this.dispatch();
		},
		updateCustom( val ) {
			this.value = val;
			this.dispatch();
		},
		dispatch() {
			if ( '{{ $blockId }}' ) {
				$dispatch( 've-field-change', {
					blockId: '{{ $blockId }}',
					field: 'shadow',
					value: this.value
				} );
			}
		},
		isActive( presetValue ) {
			return this.value === presetValue;
		}
	}"
	class="ve-shadow-control"
>
	<div class="mb-2 flex items-center justify-between">
		<label class="text-sm font-medium">{{ $label }}</label>
		<button
			type="button"
			class="text-xs text-base-content/60 hover:text-base-content"
			x-on:click="showCustom = !showCustom"
			:aria-expanded="showCustom"
		>
			{{ __( 'visual-editor::ve.custom' ) }}
		</button>
	</div>

	<div class="flex flex-wrap gap-2">
		@foreach ( $presets as $key => $preset )
			<button
				type="button"
				class="rounded-md border px-3 py-1.5 text-xs transition-colors"
				:class="isActive( {{ Js::from( $preset ) }} )
					? 'border-primary bg-primary/10 text-primary'
					: 'border-base-300 hover:border-base-content/30'"
				x-on:click="selectPreset( '{{ $key }}' )"
			>
				{{ ucfirst( $key ) }}
			</button>
		@endforeach
	</div>

	<div x-show="showCustom" x-cloak class="mt-2">
		<input
			type="text"
			class="input input-bordered input-sm w-full"
			:value="value"
			x-on:change="updateCustom( $el.value )"
			placeholder="0 4px 6px -1px rgb(0 0 0 / 0.1)"
		/>
	</div>
</div>
