{{--
 * Toolbar Component
 *
 * A composable toolbar container with roving tabindex keyboard navigation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{
		activeIndex: 0,
		getButtons() {
			return Array.from( this.$el.querySelectorAll( '[data-ve-toolbar-item]' ) );
		},
		focusItem( index ) {
			const buttons = this.getButtons();
			if ( buttons.length === 0 ) return;
			this.activeIndex = Math.max( 0, Math.min( index, buttons.length - 1 ) );
			buttons.forEach( ( btn, i ) => {
				btn.setAttribute( 'tabindex', i === this.activeIndex ? '0' : '-1' );
			} );
			buttons[ this.activeIndex ].focus();
		},
		handleKeydown( event ) {
			const buttons = this.getButtons();
			if ( buttons.length === 0 ) return;

			const isHorizontal = '{{ $orientation }}' === 'horizontal';
			const nextKey = isHorizontal ? 'ArrowRight' : 'ArrowDown';
			const prevKey = isHorizontal ? 'ArrowLeft' : 'ArrowUp';

			switch ( event.key ) {
				case nextKey:
					event.preventDefault();
					this.focusItem( ( this.activeIndex + 1 ) % buttons.length );
					break;
				case prevKey:
					event.preventDefault();
					this.focusItem( ( this.activeIndex - 1 + buttons.length ) % buttons.length );
					break;
				case 'Home':
					event.preventDefault();
					this.focusItem( 0 );
					break;
				case 'End':
					event.preventDefault();
					this.focusItem( buttons.length - 1 );
					break;
			}
		},
		init() {
			this.$nextTick( () => {
				const buttons = this.getButtons();
				buttons.forEach( ( btn, i ) => {
					btn.setAttribute( 'tabindex', i === 0 ? '0' : '-1' );
				} );
			} );
		}
	}"
	x-on:keydown="handleKeydown( $event )"
	{{ $attributes->merge( [
		'class' => 'flex items-center gap-0.5 rounded-lg border border-base-300 bg-base-100 p-1.5 shadow-sm ' .
			( 'vertical' === $orientation ? 'flex-col' : 'flex-row' ),
	] ) }}
	role="toolbar"
	aria-orientation="{{ $orientation }}"
	@if ( $label )
		aria-label="{{ $label }}"
	@endif
>
	{{ $slot }}
</div>
