{{--
 * Popover Component
 *
 * A popover with smart positioning that anchors to elements
 * and automatically repositions to stay within the viewport.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{
		open: false,
		placement: {{ Js::from( $placement ) }},
		currentPlacement: {{ Js::from( $placement ) }},
		popoverWidth: {{ Js::from( $width ) }},
		popoverStyle: { position: 'fixed', top: '-9999px', left: '-9999px', zIndex: 50 },
		arrowStyle: {},
		_resizeObserver: null,
		_scrollHandler: null,

		toggle() {
			this.open ? this.close() : this.openPopover();
		},

		openPopover() {
			this.open = true;
			this.$nextTick( () => {
				this.position();
				this.startObserving();
			} );
			this.$dispatch( 've-popover-open', { id: '{{ $uuid }}' } );
		},

		close() {
			this.open = false;
			this.stopObserving();
			this.$dispatch( 've-popover-close', { id: '{{ $uuid }}' } );
		},

		handleBlur( event ) {
			const related = event.relatedTarget;
			if ( related && ( this.$refs.popover?.contains( related ) || this.$refs.trigger?.contains( related ) ) ) {
				return;
			}
			this.close();
		},

		position() {
			const trigger = this.$refs.trigger;
			const popover = this.$refs.popover;
			if ( ! trigger || ! popover ) return;

			const triggerRect = trigger.getBoundingClientRect();
			const popoverRect = popover.getBoundingClientRect();
			const offset = {{ Js::from( $offset ) }};
			const viewportW = window.innerWidth;
			const viewportH = window.innerHeight;

			let placement = this.placement;
			let top = 0;
			let left = 0;

			{{-- Calculate position based on placement --}}
			const calcPosition = ( pl ) => {
				const base = pl.split( '-' )[ 0 ];
				const align = pl.split( '-' )[ 1 ] || 'center';
				let t = 0;
				let l = 0;

				switch ( base ) {
					case 'top':
						t = triggerRect.top - popoverRect.height - offset;
						l = triggerRect.left + ( triggerRect.width / 2 ) - ( popoverRect.width / 2 );
						break;
					case 'bottom':
						t = triggerRect.bottom + offset;
						l = triggerRect.left + ( triggerRect.width / 2 ) - ( popoverRect.width / 2 );
						break;
					case 'left':
						t = triggerRect.top + ( triggerRect.height / 2 ) - ( popoverRect.height / 2 );
						l = triggerRect.left - popoverRect.width - offset;
						break;
					case 'right':
						t = triggerRect.top + ( triggerRect.height / 2 ) - ( popoverRect.height / 2 );
						l = triggerRect.right + offset;
						break;
				}

				if ( align === 'start' ) {
					if ( base === 'top' || base === 'bottom' ) {
						l = triggerRect.left;
					} else {
						t = triggerRect.top;
					}
				} else if ( align === 'end' ) {
					if ( base === 'top' || base === 'bottom' ) {
						l = triggerRect.right - popoverRect.width;
					} else {
						t = triggerRect.bottom - popoverRect.height;
					}
				}

				return { top: t, left: l };
			};

			let pos = calcPosition( placement );
			top = pos.top;
			left = pos.left;

			{{-- Flip if needed --}}
			@if ( $flip )
				const flipMap = {
					top: 'bottom', bottom: 'top', left: 'right', right: 'left'
				};
				const base = placement.split( '-' )[ 0 ];
				const suffix = placement.split( '-' )[ 1 ] ? ( '-' + placement.split( '-' )[ 1 ] ) : '';

				if (
					( base === 'top' && top < 0 ) ||
					( base === 'bottom' && top + popoverRect.height > viewportH ) ||
					( base === 'left' && left < 0 ) ||
					( base === 'right' && left + popoverRect.width > viewportW )
				) {
					const flipped = flipMap[ base ] + suffix;
					const flippedPos = calcPosition( flipped );
					top = flippedPos.top;
					left = flippedPos.left;
					this.currentPlacement = flipped;
				} else {
					this.currentPlacement = placement;
				}
			@endif

			{{-- Shift to stay in viewport --}}
			@if ( $shift )
				const pad = 8;
				if ( left < pad ) left = pad;
				if ( left + popoverRect.width > viewportW - pad ) left = viewportW - popoverRect.width - pad;
				if ( top < pad ) top = pad;
				if ( top + popoverRect.height > viewportH - pad ) top = viewportH - popoverRect.height - pad;
			@endif

			const style = {
				position: 'fixed',
				top: top + 'px',
				left: left + 'px',
				zIndex: 50,
			};
			if ( this.popoverWidth ) {
				style.width = this.popoverWidth;
			}
			this.popoverStyle = style;

			{{-- Arrow positioning --}}
			@if ( $arrow )
				const arrowSize = 6;
				const currentBase = this.currentPlacement.split( '-' )[ 0 ];
				let arrowTop = '';
				let arrowLeft = '';
				let arrowTransform = 'rotate(45deg)';

				switch ( currentBase ) {
					case 'bottom':
						arrowTop = '-' + arrowSize + 'px';
						arrowLeft = ( triggerRect.left + triggerRect.width / 2 - left - arrowSize ) + 'px';
						break;
					case 'top':
						arrowTop = ( popoverRect.height - arrowSize ) + 'px';
						arrowLeft = ( triggerRect.left + triggerRect.width / 2 - left - arrowSize ) + 'px';
						break;
					case 'left':
						arrowTop = ( triggerRect.top + triggerRect.height / 2 - top - arrowSize ) + 'px';
						arrowLeft = ( popoverRect.width - arrowSize ) + 'px';
						break;
					case 'right':
						arrowTop = ( triggerRect.top + triggerRect.height / 2 - top - arrowSize ) + 'px';
						arrowLeft = '-' + arrowSize + 'px';
						break;
				}

				this.arrowStyle = {
					position: 'absolute',
					top: arrowTop,
					left: arrowLeft,
					width: ( arrowSize * 2 ) + 'px',
					height: ( arrowSize * 2 ) + 'px',
					transform: arrowTransform,
				};
			@endif
		},

		startObserving() {
			let rafId = null;
			this._scrollHandler = () => {
				if ( rafId ) return;
				rafId = requestAnimationFrame( () => {
					this.position();
					rafId = null;
				} );
			};
			window.addEventListener( 'scroll', this._scrollHandler, true );
			window.addEventListener( 'resize', this._scrollHandler );

			if ( window.ResizeObserver && this.$refs.trigger ) {
				this._resizeObserver = new ResizeObserver( () => this.position() );
				this._resizeObserver.observe( this.$refs.trigger );
			}
		},

		stopObserving() {
			if ( this._scrollHandler ) {
				window.removeEventListener( 'scroll', this._scrollHandler, true );
				window.removeEventListener( 'resize', this._scrollHandler );
				this._scrollHandler = null;
			}
			if ( this._resizeObserver ) {
				this._resizeObserver.disconnect();
				this._resizeObserver = null;
			}
		},

		destroy() {
			this.stopObserving();
		}
	}"
	{{ $attributes->merge( [ 'class' => 'relative inline-block' ] ) }}
>
	{{-- Trigger --}}
	<div
		x-ref="trigger"
		@if ( 'click' === $triggerOn )
			x-on:click="toggle()"
		@elseif ( 'hover' === $triggerOn )
			x-on:mouseenter="openPopover()"
			x-on:mouseleave="close()"
			x-on:focusin="openPopover()"
			x-on:focusout="handleBlur( $event )"
		@endif
		:aria-expanded="open"
		aria-controls="{{ $uuid }}-content"
		aria-haspopup="dialog"
	>
		{{ $trigger ?? '' }}
	</div>

	{{-- Popover Content --}}
	<div
		id="{{ $uuid }}-content"
		x-ref="popover"
		x-show="open"
		x-transition:enter="ve-enter-{{ $animation }}"
		x-transition:enter-start="ve-enter-{{ $animation }}-from"
		x-transition:enter-end="ve-enter-{{ $animation }}-to"
		x-transition:leave="ve-leave-{{ $animation }}"
		x-transition:leave-start="ve-leave-{{ $animation }}-from"
		x-transition:leave-end="ve-leave-{{ $animation }}-to"
		@if ( $closeOnClickOutside )
			x-on:click.outside="close()"
		@endif
		@if ( $closeOnEscape )
			x-on:keydown.escape.window="if ( open ) close()"
		@endif
		@if ( $trapFocus )
			{{-- Requires @alpinejs/focus plugin to be installed and registered --}}
			x-trap="open"
		@endif
		:style="popoverStyle"
		class="rounded-lg border border-base-300 bg-base-100 shadow-lg"
		role="dialog"
		:aria-hidden="! open"
		@if ( $ariaLabel )
			aria-label="{{ $ariaLabel }}"
		@endif
	>
		@if ( $arrow )
			<div
				class="bg-base-100 border-l border-t border-base-300"
				:style="arrowStyle"
			></div>
		@endif

		<div class="relative">
			{{ $slot }}
		</div>
	</div>
</div>
