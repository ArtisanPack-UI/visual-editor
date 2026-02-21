{{--
 * ARIA Live Region Component
 *
 * Provides a global announcement system for screen readers.
 * Initializes an Alpine.js store for dispatching announcements
 * from anywhere in the application.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data
	x-init="
		if ( ! Alpine.store( 'announcer' ) ) {
			Alpine.store( 'announcer', {
				politeMessage: '',
				assertiveMessage: '',
				_clearTimer: null,
				_debounceTimer: null,

				announce( message, priority ) {
					const prio = priority || '{{ $priority }}';
					const debounceMs = {{ $debounce }};
					const clearMs = {{ $clearAfter }};

					clearTimeout( this._debounceTimer );
					this._debounceTimer = setTimeout( () => {
						if ( prio === 'assertive' ) {
							this.assertiveMessage = '';
							Alpine.nextTick( () => { this.assertiveMessage = message } );
						} else {
							this.politeMessage = '';
							Alpine.nextTick( () => { this.politeMessage = message } );
						}

						if ( clearMs > 0 ) {
							clearTimeout( this._clearTimer );
							this._clearTimer = setTimeout( () => {
								this.politeMessage = '';
								this.assertiveMessage = '';
							}, clearMs );
						}
					}, debounceMs );
				},

				clear() {
					this.politeMessage = '';
					this.assertiveMessage = '';
					clearTimeout( this._clearTimer );
					clearTimeout( this._debounceTimer );
				}
			} );
		}
	"
	{{ $attributes->merge( [ 'class' => '' ] ) }}
>
	{{-- Polite region: queued announcements --}}
	<div
		class="sr-only"
		aria-live="polite"
		aria-atomic="true"
		role="status"
		x-text="$store.announcer?.politeMessage || ''"
	></div>

	{{-- Assertive region: immediate/interrupting announcements --}}
	<div
		class="sr-only"
		aria-live="assertive"
		aria-atomic="true"
		role="alert"
		x-text="$store.announcer?.assertiveMessage || ''"
	></div>
</div>
