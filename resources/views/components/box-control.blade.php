{{--
 * Box Control Component
 *
 * Four-sided input (top/right/bottom/left) with linked/unlinked toggle.
 * Displayed in a cross layout with clear side labels.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{
		top: {{ Js::from( $top ?? '' ) }},
		right: {{ Js::from( $right ?? '' ) }},
		bottom: {{ Js::from( $bottom ?? '' ) }},
		left: {{ Js::from( $left ?? '' ) }},
		linked: {{ Js::from( $linked ) }},
		onInput( side, value ) {
			this[side] = value
			if ( this.linked ) {
				this.top = value
				this.right = value
				this.bottom = value
				this.left = value
			}
			this.dispatch()
		},
		toggleLinked() {
			this.linked = !this.linked
			if ( this.linked && this.top ) {
				this.right = this.top
				this.bottom = this.top
				this.left = this.top
			}
			this.dispatch()
		},
		dispatch() {
			$dispatch( 've-box-change', {
				top: this.top,
				right: this.right,
				bottom: this.bottom,
				left: this.left,
				linked: this.linked
			} )
		}
	}"
	{{ $attributes->merge( [ 'class' => 'flex flex-col gap-2' ] ) }}
	role="group"
	@if ( $label )
		aria-label="{{ $label }}"
	@endif
>
	@if ( $label )
		<div class="flex items-center justify-between">
			<label class="text-xs font-medium text-base-content/60">
				{{ $label }}
			</label>

			{{-- Link/Unlink Toggle --}}
			<button
				type="button"
				x-on:click="toggleLinked()"
				:class="linked ? 'text-primary' : 'text-base-content/30 hover:text-base-content/60'"
				class="p-1 rounded transition-colors"
				aria-label="{{ __( 'visual-editor::ve.link_sides' ) }}"
				:aria-pressed="linked ? 'true' : 'false'"
			>
				<svg x-show="linked" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
				</svg>
				<svg x-show="!linked" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m3.212-8.383l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
					<path stroke-linecap="round" stroke-width="2" d="M4 20L20 4" />
				</svg>
			</button>
		</div>
	@endif

	{{-- Box inputs in a cross/visual layout --}}
	<div class="grid grid-cols-4 gap-2">
		{{-- Top --}}
		<div class="flex flex-col gap-0.5">
			<span class="text-[10px] font-medium uppercase tracking-wider text-base-content/40 text-center">
				{{ __( 'visual-editor::ve.top' ) }}
			</span>
			<x-artisanpack-input
				type="number"
				x-model="top"
				x-on:input="onInput( 'top', $event.target.value )"
				:min="$min"
				:max="$max"
				:step="$step"
				size="sm"
				:aria-label="__( 'visual-editor::ve.top' )"
			/>
		</div>

		{{-- Right --}}
		<div class="flex flex-col gap-0.5">
			<span class="text-[10px] font-medium uppercase tracking-wider text-base-content/40 text-center">
				{{ __( 'visual-editor::ve.right' ) }}
			</span>
			<x-artisanpack-input
				type="number"
				x-model="right"
				x-on:input="onInput( 'right', $event.target.value )"
				:min="$min"
				:max="$max"
				:step="$step"
				size="sm"
				:aria-label="__( 'visual-editor::ve.right' )"
			/>
		</div>

		{{-- Bottom --}}
		<div class="flex flex-col gap-0.5">
			<span class="text-[10px] font-medium uppercase tracking-wider text-base-content/40 text-center">
				{{ __( 'visual-editor::ve.bottom' ) }}
			</span>
			<x-artisanpack-input
				type="number"
				x-model="bottom"
				x-on:input="onInput( 'bottom', $event.target.value )"
				:min="$min"
				:max="$max"
				:step="$step"
				size="sm"
				:aria-label="__( 'visual-editor::ve.bottom' )"
			/>
		</div>

		{{-- Left --}}
		<div class="flex flex-col gap-0.5">
			<span class="text-[10px] font-medium uppercase tracking-wider text-base-content/40 text-center">
				{{ __( 'visual-editor::ve.left' ) }}
			</span>
			<x-artisanpack-input
				type="number"
				x-model="left"
				x-on:input="onInput( 'left', $event.target.value )"
				:min="$min"
				:max="$max"
				:step="$step"
				size="sm"
				:aria-label="__( 'visual-editor::ve.left' )"
			/>
		</div>
	</div>

	@if ( $hint )
		<div class="{{ $hintClass }}">{{ $hint }}</div>
	@endif
</div>
