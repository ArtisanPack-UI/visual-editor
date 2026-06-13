{{-- Block animations runtime (#489).

The minimal inline counterpart of `resources/js/visual-editor/animations/runtime.ts`.
Emitted on published pages that include at least one entrance animation. ~1 KB
gzipped. The full TypeScript runtime is the canonical implementation; this
inline copy exists so the Blade renderer doesn't depend on a host bundling
the editor's exported `bootstrapAnimationsRuntime`. --}}
<script>
(function () {
	if ( ! document.querySelector( '[data-ap-anim-entrance]' ) ) {
		return;
	}

	var PRE = 'ap-anim-pre';
	var PLAY = 'ap-anim-play';

	function reduced() {
		return window.matchMedia
			&& window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	}

	function thresholdFor( el ) {
		var raw = el.getAttribute( 'data-ap-anim-threshold' );
		if ( null === raw ) return 0.2;
		var v = parseFloat( raw );
		if ( isNaN( v ) ) return 0.2;
		if ( v < 0 ) return 0;
		if ( v > 1 ) return 1;
		return v;
	}

	function play( el ) {
		el.classList.remove( PRE );
		el.classList.add( PLAY );
	}

	function rearm( el ) {
		el.classList.remove( PLAY );
		el.classList.add( PRE );
	}

	function init() {
		var reducedMotion = reduced();
		// No-IO browsers reveal everything synchronously — but the
		// MutationObserver path below still wires up so late-inserted
		// nodes get revealed too (they'd otherwise stay frozen in the
		// pre-state forever).
		var hasIO = 'undefined' !== typeof IntersectionObserver;

		var entries = new Map();
		var observersByThreshold = {};

		function observerFor( t ) {
			var key = String( t );
			if ( observersByThreshold[ key ] ) {
				return observersByThreshold[ key ];
			}
			var observer = new IntersectionObserver(
				function ( ioEntries ) {
					ioEntries.forEach( function ( ioEntry ) {
						var meta = entries.get( ioEntry.target );
						if ( ! meta ) return;
						if ( ioEntry.isIntersecting ) {
							if ( ! meta.played ) {
								play( ioEntry.target );
								meta.played = true;
								if ( meta.once ) {
									observer.unobserve( ioEntry.target );
									entries.delete( ioEntry.target );
								}
							}
						} else if ( ! meta.once && meta.played ) {
							rearm( ioEntry.target );
							meta.played = false;
						}
					} );
				},
				{ threshold: t }
			);
			observersByThreshold[ key ] = observer;
			return observer;
		}

		function observeEntry( el ) {
			if ( entries.has( el ) ) return;
			// Reduced-motion must be checked BEFORE the hasIO gate —
			// otherwise no-IntersectionObserver browsers play the
			// entrance even when the user prefers reduced motion. The
			// per-block `data-ap-anim-reduced="allow"` override still
			// lets a designer keep motion on essential animations.
			if ( reducedMotion && 'allow' !== el.getAttribute( 'data-ap-anim-reduced' ) ) {
				play( el );
				// Record a played entry so a later MutationObserver
				// callback for the same node (e.g. it was re-parented)
				// short-circuits at `entries.has( el )` above.
				entries.set( el, { threshold: 0, once: true, played: true } );
				return;
			}
			// No IntersectionObserver → reveal immediately. The noscript
			// fallback handles the no-JS case; this branch handles
			// JS-but-stone-age-browser.
			if ( ! hasIO ) {
				play( el );
				entries.set( el, { threshold: 0, once: true, played: true } );
				return;
			}
			var t = thresholdFor( el );
			var once = 'false' !== el.getAttribute( 'data-ap-anim-once' );
			entries.set( el, { threshold: t, once: once, played: false } );
			observerFor( t ).observe( el );
		}

		Array.prototype.forEach.call(
			document.querySelectorAll( '[data-ap-anim-entrance]' ),
			observeEntry
		);

		// Mirror the TS runtime: pick up entrance elements that arrive
		// after init() — e.g. inserted by a client-side accordion expand
		// or a query-loop pagination swap — so they animate on first
		// reveal instead of staying in the pre-state.
		if ( 'undefined' !== typeof MutationObserver ) {
			new MutationObserver( function ( mutations ) {
				mutations.forEach( function ( mutation ) {
					Array.prototype.forEach.call( mutation.addedNodes, function ( node ) {
						if ( ! node || 1 !== node.nodeType ) return;
						if ( node.matches && node.matches( '[data-ap-anim-entrance]' ) ) {
							observeEntry( node );
						}
						if ( node.querySelectorAll ) {
							Array.prototype.forEach.call(
								node.querySelectorAll( '[data-ap-anim-entrance]' ),
								observeEntry
							);
						}
					} );
				} );
			} ).observe( document.body, { childList: true, subtree: true } );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init, { once: true } );
	} else {
		init();
	}
})();
</script>
