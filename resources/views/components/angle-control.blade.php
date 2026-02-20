{{--
 * Angle Control Component
 *
 * A circular angle picker for rotations and gradients with synced number input.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{
		angle: {{ $value }},
		dragging: false,
		svgEl: null,
		init() {
			this.svgEl = this.$refs.circle
		},
		startDrag( event ) {
			this.dragging = true
			this.updateAngle( event )
		},
		onDrag( event ) {
			if ( ! this.dragging ) return
			this.updateAngle( event )
		},
		stopDrag() {
			this.dragging = false
		},
		updateAngle( event ) {
			const rect = this.svgEl.getBoundingClientRect()
			const centerX = rect.left + rect.width / 2
			const centerY = rect.top + rect.height / 2

			const clientX = event.touches ? event.touches[0].clientX : event.clientX
			const clientY = event.touches ? event.touches[0].clientY : event.clientY

			let degrees = Math.atan2( clientY - centerY, clientX - centerX ) * ( 180 / Math.PI ) + 90
			if ( degrees < 0 ) degrees += 360

			this.angle = Math.round( degrees / {{ $step }} ) * {{ $step }}
			if ( this.angle >= {{ $max }} ) this.angle = {{ $min }}

			$dispatch( 've-angle-change', { angle: this.angle } )
		},
		onInput() {
			this.angle = Math.max( {{ $min }}, Math.min( {{ $max }} - 1, Number( this.angle ) || 0 ) )
			$dispatch( 've-angle-change', { angle: this.angle } )
		},
		get handleX() {
			const rad = ( this.angle - 90 ) * ( Math.PI / 180 )
			return 24 + 16 * Math.cos( rad )
		},
		get handleY() {
			const rad = ( this.angle - 90 ) * ( Math.PI / 180 )
			return 24 + 16 * Math.sin( rad )
		}
	}"
	{{ $attributes->merge( [ 'class' => 'flex flex-col gap-1' ] ) }}
	x-on:angle-drag-move.window="onDrag( $event.detail )"
	x-on:angle-drag-end.window="stopDrag()"
>
	@if ( $label )
		<label class="text-xs font-medium text-gray-600">
			{{ $label }}
		</label>
	@endif

	<div class="flex items-center gap-3">
		{{-- Circular Picker --}}
		<svg
			x-ref="circle"
			width="48"
			height="48"
			viewBox="0 0 48 48"
			class="cursor-pointer"
			role="slider"
			:aria-valuenow="angle"
			aria-valuemin="{{ $min }}"
			aria-valuemax="{{ $max }}"
			:aria-label="'{{ $label ?? __( 'Angle' ) }}'"
			tabindex="0"
			x-on:mousedown.prevent="startDrag( $event )"
			x-on:touchstart.prevent="startDrag( $event )"
			x-on:keydown.up.prevent="angle = ( angle + {{ $step }} ) % {{ $max }}; $dispatch( 've-angle-change', { angle: angle } )"
			x-on:keydown.right.prevent="angle = ( angle + {{ $step }} ) % {{ $max }}; $dispatch( 've-angle-change', { angle: angle } )"
			x-on:keydown.down.prevent="angle = ( angle - {{ $step }} + {{ $max }} ) % {{ $max }}; $dispatch( 've-angle-change', { angle: angle } )"
			x-on:keydown.left.prevent="angle = ( angle - {{ $step }} + {{ $max }} ) % {{ $max }}; $dispatch( 've-angle-change', { angle: angle } )"
		>
			{{-- Background circle --}}
			<circle cx="24" cy="24" r="20" fill="none" stroke="currentColor" stroke-width="1.5" class="text-gray-200" />

			{{-- Angle line --}}
			<line
				x1="24"
				y1="24"
				:x2="handleX"
				:y2="handleY"
				stroke="currentColor"
				stroke-width="2"
				class="text-blue-500"
			/>

			{{-- Center dot --}}
			<circle cx="24" cy="24" r="2" fill="currentColor" class="text-gray-400" />

			{{-- Handle dot --}}
			<circle
				:cx="handleX"
				:cy="handleY"
				r="4"
				fill="currentColor"
				class="text-blue-500"
			/>
		</svg>

		{{-- Number Input --}}
		<div class="flex items-center gap-1">
			<div class="w-16">
				<x-artisanpack-input
					type="number"
					x-model.number="angle"
					x-on:input="onInput()"
					:min="$min"
					:max="$max - 1"
					:step="$step"
					size="sm"
					:aria-label="$label ? $label . ' ' . __( 'degrees' ) : __( 'Angle in degrees' )"
				/>
			</div>
			<span class="text-xs text-gray-500">&deg;</span>
		</div>
	</div>

	@if ( $hint )
		<div class="{{ $hintClass }}">{{ $hint }}</div>
	@endif
</div>

{{-- Global event listeners for drag --}}
@once
	<div
		x-data
		x-on:mousemove.window="$dispatch( 'angle-drag-move', $event )"
		x-on:mouseup.window="$dispatch( 'angle-drag-end' )"
		x-on:touchmove.window="$dispatch( 'angle-drag-move', $event )"
		x-on:touchend.window="$dispatch( 'angle-drag-end' )"
		class="hidden"
	></div>
@endonce
