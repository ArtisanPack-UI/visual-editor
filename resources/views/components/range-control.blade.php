{{--
 * Range Control Component
 *
 * A range slider synced with a number input and optional reset button.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{
		value: {{ null !== $value ? $value : $min }},
		defaultValue: {{ null !== $defaultValue ? $defaultValue : 'null' }},
		get hasChanged() {
			return this.defaultValue !== null && this.value !== this.defaultValue
		},
		reset() {
			if ( this.defaultValue !== null ) {
				this.value = this.defaultValue
				this.dispatch()
			}
		},
		dispatch() {
			$dispatch( 've-range-change', { value: this.value } )
		}
	}"
	{{ $attributes->merge( [ 'class' => 'flex flex-col gap-1' ] ) }}
	role="group"
	@if ( $label )
		aria-label="{{ $label }}"
	@endif
>
	@if ( $label )
		<div class="flex items-center justify-between">
			<label class="text-xs font-medium text-gray-600">
				{{ $label }}
			</label>

			@if ( $showReset )
				<x-artisanpack-button
					x-show="hasChanged"
					x-on:click="reset()"
					size="xs"
					color="ghost"
					:title="__( 'Reset to default' )"
					:aria-label="__( 'Reset to default' )"
				>
					<svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
					</svg>
				</x-artisanpack-button>
			@endif
		</div>
	@endif

	<div class="flex items-center gap-2">
		<div class="flex-1">
			<x-artisanpack-range
				x-model.number="value"
				x-on:input="dispatch()"
				:min="$min"
				:max="$max"
				:step="$step"
				size="sm"
				aria-valuenow="value"
				:aria-valuemin="$min"
				:aria-valuemax="$max"
				:aria-label="$label ?? __( 'Range' )"
			/>
		</div>

		@if ( $showInput )
			<div class="w-16">
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
		@endif
	</div>

	@if ( $hint )
		<div class="{{ $hintClass }}">{{ $hint }}</div>
	@endif
</div>
