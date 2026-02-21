{{--
 * Focus Trap Component
 *
 * Traps keyboard focus within a container using Alpine.js x-trap.
 * Supports focus restoration and auto-focus on activation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{
		active: {{ Js::from( $active ) }},
		previousFocus: null,
		init() {
			if ( {{ Js::from( $restoreFocus ) }} ) {
				this.previousFocus = document.activeElement;
			}

			@if ( $initialFocus )
				this.$nextTick( () => {
					if ( this.active ) {
						const target = this.$el.querySelector( {{ Js::from( $initialFocus ) }} );
						if ( target ) {
							target.focus();
						}
					}
				} );
			@endif

			this.$watch( 'active', ( value ) => {
				if ( value ) {
					$dispatch( 've-focus-trap-activate', { id: '{{ $uuid }}' } );
				} else {
					$dispatch( 've-focus-trap-deactivate', { id: '{{ $uuid }}' } );
					if ( this.previousFocus && {{ Js::from( $restoreFocus ) }} ) {
						this.$nextTick( () => {
							if ( this.previousFocus && typeof this.previousFocus.focus === 'function' ) {
								this.previousFocus.focus();
							}
						} );
					}
				}
			} );
		},
		activate() {
			this.previousFocus = document.activeElement;
			this.active = true;
		},
		deactivate() {
			this.active = false;
		}
	}"
	x-trap{{ $inert ? '.inert' : '' }}{{ ! $autoFocus ? '.noautofocus' : '' }}="active"
	{{ $attributes->merge( [ 'class' => '' ] ) }}
	role="region"
	:aria-label="active ? '{{ __( 'visual-editor::ve.focus_trapped_region' ) }}' : null"
>
	{{ $slot }}
</div>
