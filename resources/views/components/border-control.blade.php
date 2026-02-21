{{--
 * Border Control Component
 *
 * A composite control for border editing: width, style, color, per-side toggle, and border radius.
 * When per-side is active, shows individual controls for top/right/bottom/left borders.
 * When per-corner is active, shows individual radius controls for each corner.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{
		width: {{ Js::from( $width ) }},
		widthUnit: {{ Js::from( $widthUnit ) }},
		style: {{ Js::from( $style ) }},
		color: {{ Js::from( $color ) }},
		perSide: {{ Js::from( $perSide ) }},
		sides: {
			top:    { width: {{ Js::from( $width ) }}, widthUnit: {{ Js::from( $widthUnit ) }}, style: {{ Js::from( $style ) }}, color: {{ Js::from( $color ) }} },
			right:  { width: {{ Js::from( $width ) }}, widthUnit: {{ Js::from( $widthUnit ) }}, style: {{ Js::from( $style ) }}, color: {{ Js::from( $color ) }} },
			bottom: { width: {{ Js::from( $width ) }}, widthUnit: {{ Js::from( $widthUnit ) }}, style: {{ Js::from( $style ) }}, color: {{ Js::from( $color ) }} },
			left:   { width: {{ Js::from( $width ) }}, widthUnit: {{ Js::from( $widthUnit ) }}, style: {{ Js::from( $style ) }}, color: {{ Js::from( $color ) }} }
		},
		activeSide: 'top',
		radius: {{ Js::from( $radius ) }},
		radiusUnit: {{ Js::from( $radiusUnit ) }},
		perCorner: {{ Js::from( $perCorner ) }},
		corners: {
			topLeft:     { radius: {{ Js::from( $radius ) }}, radiusUnit: {{ Js::from( $radiusUnit ) }} },
			topRight:    { radius: {{ Js::from( $radius ) }}, radiusUnit: {{ Js::from( $radiusUnit ) }} },
			bottomRight: { radius: {{ Js::from( $radius ) }}, radiusUnit: {{ Js::from( $radiusUnit ) }} },
			bottomLeft:  { radius: {{ Js::from( $radius ) }}, radiusUnit: {{ Js::from( $radiusUnit ) }} }
		},
		togglePerSide() {
			this.perSide = !this.perSide
			if ( this.perSide ) {
				['top', 'right', 'bottom', 'left'].forEach( side => {
					this.sides[side] = {
						width: this.width,
						widthUnit: this.widthUnit,
						style: this.style,
						color: this.color
					}
				} )
			} else {
				this.width = this.sides[this.activeSide].width
				this.widthUnit = this.sides[this.activeSide].widthUnit
				this.style = this.sides[this.activeSide].style
				this.color = this.sides[this.activeSide].color
			}
			this.dispatch()
		},
		togglePerCorner() {
			this.perCorner = !this.perCorner
			if ( this.perCorner ) {
				['topLeft', 'topRight', 'bottomRight', 'bottomLeft'].forEach( corner => {
					this.corners[corner] = {
						radius: this.radius,
						radiusUnit: this.radiusUnit
					}
				} )
			} else {
				this.radius = this.corners.topLeft.radius
				this.radiusUnit = this.corners.topLeft.radiusUnit
			}
			this.dispatch()
		},
		dispatch() {
			let detail = {}

			if ( this.perSide ) {
				detail.perSide = true
				detail.sides = JSON.parse( JSON.stringify( this.sides ) )
			} else {
				detail.width = this.width
				detail.widthUnit = this.widthUnit
				detail.style = this.style
				detail.color = this.color
				detail.perSide = false
			}

			if ( this.perCorner ) {
				detail.perCorner = true
				detail.corners = JSON.parse( JSON.stringify( this.corners ) )
			} else {
				detail.radius = this.radius
				detail.radiusUnit = this.radiusUnit
				detail.perCorner = false
			}

			$dispatch( 've-border-change', detail )
		},
		get previewStyle() {
			let style = ''

			if ( this.perSide ) {
				style += `border-top: ${this.sides.top.width}${this.sides.top.widthUnit} ${this.sides.top.style} ${this.sides.top.color};`
				style += `border-right: ${this.sides.right.width}${this.sides.right.widthUnit} ${this.sides.right.style} ${this.sides.right.color};`
				style += `border-bottom: ${this.sides.bottom.width}${this.sides.bottom.widthUnit} ${this.sides.bottom.style} ${this.sides.bottom.color};`
				style += `border-left: ${this.sides.left.width}${this.sides.left.widthUnit} ${this.sides.left.style} ${this.sides.left.color};`
			} else {
				style += `border: ${this.width}${this.widthUnit} ${this.style} ${this.color};`
			}

			if ( this.perCorner ) {
				style += `border-top-left-radius: ${this.corners.topLeft.radius}${this.corners.topLeft.radiusUnit};`
				style += `border-top-right-radius: ${this.corners.topRight.radius}${this.corners.topRight.radiusUnit};`
				style += `border-bottom-right-radius: ${this.corners.bottomRight.radius}${this.corners.bottomRight.radiusUnit};`
				style += `border-bottom-left-radius: ${this.corners.bottomLeft.radius}${this.corners.bottomLeft.radiusUnit};`
			} else {
				style += `border-radius: ${this.radius}${this.radiusUnit};`
			}

			return style
		}
	}"
	{{ $attributes->merge( [ 'class' => 'flex flex-col gap-3' ] ) }}
	role="group"
	@if ( $label )
		aria-label="{{ $label }}"
	@endif
>
	<div class="flex items-center justify-between">
		@if ( $label )
			<label class="text-xs font-medium text-base-content/60">
				{{ $label }}
			</label>
		@else
			<span></span>
		@endif

		<button
			type="button"
			x-on:click="togglePerSide()"
			:class="perSide ? 'text-primary bg-primary/10' : 'text-base-content/30 hover:text-base-content/60'"
			class="p-1.5 rounded-md transition-colors"
			aria-label="{{ __( 'visual-editor::ve.configure_per_side' ) }}"
			:aria-pressed="perSide ? 'true' : 'false'"
			title="{{ __( 'visual-editor::ve.per_side' ) }}"
		>
			<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5z" />
			</svg>
		</button>
	</div>

	{{-- Uniform controls (shown when perSide is false) --}}
	<div x-show="!perSide" class="flex flex-col gap-3">
		{{-- Border Width --}}
		<x-ve-unit-control
			:label="__( 'visual-editor::ve.width' )"
			x-on:ve-unit-change.stop="width = $event.detail.value; widthUnit = $event.detail.unit; dispatch()"
			:value="$width"
			:unit="$widthUnit"
			:units="[ 'px', 'em', 'rem' ]"
			:min="0"
			:max="100"
		/>

		{{-- Border Style --}}
		<div class="flex flex-col gap-1">
			<label class="text-xs font-medium text-base-content/60">
				{{ __( 'visual-editor::ve.style' ) }}
			</label>
			<x-artisanpack-select
				:options="$styleOptions()"
				option-value="id"
				option-label="name"
				x-model="style"
				x-on:change="dispatch()"
				size="sm"
				:aria-label="__( 'visual-editor::ve.border_style' )"
			/>
		</div>

		{{-- Border Color --}}
		<x-ve-color-system
			:label="__( 'visual-editor::ve.color' )"
			:value="$color"
			:palette="$palette"
			x-on:ve-color-change.stop="color = $event.detail.color; dispatch()"
		/>
	</div>

	{{-- Per-side controls (shown when perSide is true) --}}
	<div x-show="perSide" x-collapse class="flex flex-col gap-3">
		{{-- Side selector tabs --}}
		<div class="flex rounded-lg border border-base-300 bg-base-200 p-1">
			@foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side )
				<button
					type="button"
					x-on:click="activeSide = '{{ $side }}'"
					:class="activeSide === '{{ $side }}' ? 'bg-primary text-primary-content shadow-sm' : 'text-base-content/50 hover:text-base-content/80 hover:bg-base-300'"
					class="flex-1 py-1.5 px-2 text-xs font-medium rounded-md transition-all text-center capitalize"
				>
					{{ __( 'visual-editor::ve.' . $side ) }}
				</button>
			@endforeach
		</div>

		{{-- Per-side width --}}
		<div class="flex flex-col gap-1">
			<label class="text-xs font-medium text-base-content/60">
				{{ __( 'visual-editor::ve.width' ) }}
			</label>
			<div class="flex items-stretch gap-1">
				<div class="flex-1">
					<x-artisanpack-input
						type="number"
						x-model="sides[activeSide].width"
						x-on:input="dispatch()"
						min="0"
						max="100"
						size="sm"
						:aria-label="__( 'visual-editor::ve.border_width' )"
					/>
				</div>
				<div class="w-20">
					<x-artisanpack-select
						:options="[ [ 'id' => 'px', 'name' => 'px' ], [ 'id' => 'em', 'name' => 'em' ], [ 'id' => 'rem', 'name' => 'rem' ] ]"
						option-value="id"
						option-label="name"
						x-model="sides[activeSide].widthUnit"
						x-on:change="dispatch()"
						size="sm"
						:aria-label="__( 'visual-editor::ve.unit' )"
					/>
				</div>
			</div>
		</div>

		{{-- Per-side style --}}
		<div class="flex flex-col gap-1">
			<label class="text-xs font-medium text-base-content/60">
				{{ __( 'visual-editor::ve.style' ) }}
			</label>
			<x-artisanpack-select
				:options="$styleOptions()"
				option-value="id"
				option-label="name"
				x-model="sides[activeSide].style"
				x-on:change="dispatch()"
				size="sm"
				:aria-label="__( 'visual-editor::ve.border_style' )"
			/>
		</div>

		{{-- Per-side color --}}
		<div class="flex flex-col gap-2">
			<label class="text-xs font-medium text-base-content/60">
				{{ __( 'visual-editor::ve.color' ) }}
			</label>
			<div class="flex items-center gap-2">
				<label class="relative cursor-pointer">
					<div
						class="h-7 w-7 rounded-full ring-1 ring-base-300"
						:style="`background-color: ${sides[activeSide].color || '#000000'}`"
					></div>
					<input
						type="color"
						x-model="sides[activeSide].color"
						x-on:input="dispatch()"
						class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
						aria-label="{{ __( 'visual-editor::ve.border_color' ) }}"
					/>
				</label>
				<x-artisanpack-input
					x-model="sides[activeSide].color"
					x-on:input="dispatch()"
					placeholder="#000000"
					size="sm"
					class="font-mono flex-1"
					:aria-label="__( 'visual-editor::ve.hex_color_value' )"
				/>
			</div>
		</div>
	</div>

	{{-- Border Radius --}}
	<div class="flex flex-col gap-3">
		<div class="flex items-center justify-between">
			<label class="text-xs font-medium text-base-content/60">
				{{ __( 'visual-editor::ve.radius' ) }}
			</label>

			<button
				type="button"
				x-on:click="togglePerCorner()"
				:class="perCorner ? 'text-primary bg-primary/10' : 'text-base-content/30 hover:text-base-content/60'"
				class="p-1.5 rounded-md transition-colors"
				aria-label="{{ __( 'visual-editor::ve.configure_per_corner' ) }}"
				:aria-pressed="perCorner ? 'true' : 'false'"
				title="{{ __( 'visual-editor::ve.per_corner' ) }}"
			>
				<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V6a2 2 0 012-2h2M16 4h2a2 2 0 012 2v2M20 16v2a2 2 0 01-2 2h-2M8 20H6a2 2 0 01-2-2v-2" />
				</svg>
			</button>
		</div>

		{{-- Uniform radius (shown when perCorner is false) --}}
		<div x-show="!perCorner">
			<x-ve-unit-control
				x-on:ve-unit-change.stop="radius = $event.detail.value; radiusUnit = $event.detail.unit; dispatch()"
				:value="$radius"
				:unit="$radiusUnit"
				:units="[ 'px', 'em', 'rem', '%' ]"
				:min="0"
				:max="999"
			/>
		</div>

		{{-- Per-corner radius (shown when perCorner is true) --}}
		<div x-show="perCorner" x-collapse class="flex flex-col gap-2">
			<div class="grid grid-cols-2 gap-2">
				@foreach ( [ 'topLeft' => __( 'visual-editor::ve.top_left' ), 'topRight' => __( 'visual-editor::ve.top_right' ), 'bottomLeft' => __( 'visual-editor::ve.bottom_left' ), 'bottomRight' => __( 'visual-editor::ve.bottom_right' ) ] as $corner => $cornerLabel )
					<div class="flex flex-col gap-0.5">
						<span class="text-[10px] font-medium uppercase tracking-wider text-base-content/40 text-center">
							{{ $cornerLabel }}
						</span>
						<div class="flex items-stretch gap-1">
							<div class="flex-1">
								<x-artisanpack-input
									type="number"
									x-model="corners.{{ $corner }}.radius"
									x-on:input="dispatch()"
									min="0"
									max="999"
									size="sm"
									:aria-label="$cornerLabel . ' ' . __( 'visual-editor::ve.radius_lowercase' )"
								/>
							</div>
							<div class="w-16">
								<x-artisanpack-select
									:options="[ [ 'id' => 'px', 'name' => 'px' ], [ 'id' => 'em', 'name' => 'em' ], [ 'id' => 'rem', 'name' => 'rem' ], [ 'id' => '%', 'name' => '%' ] ]"
									option-value="id"
									option-label="name"
									x-model="corners.{{ $corner }}.radiusUnit"
									x-on:change="dispatch()"
									size="sm"
									:aria-label="__( 'visual-editor::ve.unit' )"
								/>
							</div>
						</div>
					</div>
				@endforeach
			</div>
		</div>
	</div>

	{{-- Preview --}}
	<div class="flex flex-col gap-1">
		<span class="text-[10px] font-medium uppercase tracking-wider text-base-content/40">
			{{ __( 'visual-editor::ve.preview' ) }}
		</span>
		<div
			class="h-10 w-full bg-base-200"
			:style="previewStyle"
		></div>
	</div>

	@if ( $hint )
		<div class="{{ $hintClass }}">{{ $hint }}</div>
	@endif
</div>
