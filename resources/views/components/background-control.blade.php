<div
	id="{{ $uuid }}"
	x-data="{
		values: {{ Js::from( $value ) }},
		updateField( field, val ) {
			this.values[field] = val;
			if ( '{{ $blockId }}' ) {
				$dispatch( 've-field-change', {
					blockId: '{{ $blockId }}',
					field: field,
					value: val
				} );
			}
		}
	}"
	class="ve-background-control space-y-3"
>
	<label class="text-sm font-medium">{{ $label }}</label>

	@if ( $hasControl( 'backgroundImage' ) )
		<div>
			<label class="mb-1 block text-xs text-base-content/60">
				{{ __( 'visual-editor::ve.background_image' ) }}
			</label>
			<input
				type="url"
				class="input input-bordered input-sm w-full"
				:value="values.backgroundImage ?? ''"
				x-on:change="updateField( 'backgroundImage', $el.value )"
				placeholder="https://..."
			/>
		</div>
	@endif

	@if ( $hasControl( 'backgroundSize' ) )
		<div>
			<label class="mb-1 block text-xs text-base-content/60">
				{{ __( 'visual-editor::ve.background_size' ) }}
			</label>
			<select
				class="select select-bordered select-sm w-full"
				:value="values.backgroundSize ?? 'cover'"
				x-on:change="updateField( 'backgroundSize', $el.value )"
			>
				<option value="cover">Cover</option>
				<option value="contain">Contain</option>
				<option value="auto">Auto</option>
			</select>
		</div>
	@endif

	@if ( $hasControl( 'backgroundPosition' ) )
		<div>
			<label class="mb-1 block text-xs text-base-content/60">
				{{ __( 'visual-editor::ve.background_position' ) }}
			</label>
			<select
				class="select select-bordered select-sm w-full"
				:value="values.backgroundPosition ?? 'center'"
				x-on:change="updateField( 'backgroundPosition', $el.value )"
			>
				<option value="center">{{ __( 'visual-editor::ve.center' ) }}</option>
				<option value="top">{{ __( 'visual-editor::ve.top' ) }}</option>
				<option value="bottom">{{ __( 'visual-editor::ve.bottom' ) }}</option>
				<option value="left">{{ __( 'visual-editor::ve.left' ) }}</option>
				<option value="right">{{ __( 'visual-editor::ve.right' ) }}</option>
				<option value="top left">{{ __( 'visual-editor::ve.top_left' ) }}</option>
				<option value="top right">{{ __( 'visual-editor::ve.top_right' ) }}</option>
				<option value="bottom left">{{ __( 'visual-editor::ve.bottom_left' ) }}</option>
				<option value="bottom right">{{ __( 'visual-editor::ve.bottom_right' ) }}</option>
			</select>
		</div>
	@endif

	@if ( $hasControl( 'backgroundGradient' ) )
		<div>
			<label class="mb-1 block text-xs text-base-content/60">
				{{ __( 'visual-editor::ve.background_gradient' ) }}
			</label>
			<input
				type="text"
				class="input input-bordered input-sm w-full"
				:value="values.backgroundGradient ?? ''"
				x-on:change="updateField( 'backgroundGradient', $el.value )"
				placeholder="linear-gradient(135deg, #667eea 0%, #764ba2 100%)"
			/>
		</div>
	@endif
</div>
