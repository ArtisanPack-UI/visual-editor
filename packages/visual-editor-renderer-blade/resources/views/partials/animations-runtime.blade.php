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

		if ( 'undefined' === typeof IntersectionObserver ) {
			Array.prototype.forEach.call(
				document.querySelectorAll( '.' + PRE ),
				play
			);
			return;
		}

		var byThreshold = {};
		var entries = new Map();

		Array.prototype.forEach.call(
			document.querySelectorAll( '[data-ap-anim-entrance]' ),
			function ( el ) {
				if ( reducedMotion && 'allow' !== el.getAttribute( 'data-ap-anim-reduced' ) ) {
					play( el );
					return;
				}
				var t = thresholdFor( el );
				var key = String( t );
				var once = 'false' !== el.getAttribute( 'data-ap-anim-once' );
				entries.set( el, { threshold: t, once: once, played: false } );
				( byThreshold[ key ] = byThreshold[ key ] || [] ).push( el );
			}
		);

		Object.keys( byThreshold ).forEach( function ( key ) {
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
				{ threshold: parseFloat( key ) }
			);
			byThreshold[ key ].forEach( function ( el ) { observer.observe( el ); } );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init, { once: true } );
	} else {
		init();
	}
})();
</script>
