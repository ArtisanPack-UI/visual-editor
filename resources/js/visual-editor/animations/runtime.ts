/**
 * Block-animations runtime (#489).
 *
 * The shared JS module loaded on published pages that have at least one
 * entrance animation. It:
 *
 *  - Subscribes a single IntersectionObserver to every element with
 *    `data-ap-anim-entrance`.
 *  - Respects `prefers-reduced-motion: reduce` by default. A block can
 *    opt-out with `data-ap-anim-reduced="allow"`.
 *  - Watches DOM mutations for blocks that were initially hidden (e.g.
 *    inside a collapsed accordion) so an entrance plays on first reveal.
 *  - Re-arms blocks whose `data-ap-anim-once="false"` opts into replays.
 *
 * The runtime is intentionally side-effecting at import time so the
 * renderer can just `<script type="module" src=".../animations.js">` it
 * and forget — there's no public `init()` to call. Tests import
 * `bootstrap()` directly to inject a fake document.
 *
 * Target gzipped size: <5 KB.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

const PRE_CLASS = 'ap-anim-pre';
const PLAY_CLASS = 'ap-anim-play';

export interface RuntimeOptions {
	root?: Document | ShadowRoot;
	prefersReducedMotion?: () => boolean;
}

interface ObservedEntry {
	element: HTMLElement;
	threshold: number;
	once: boolean;
	played: boolean;
}

function defaultReducedMotion(): boolean {
	if ( 'undefined' === typeof window || ! window.matchMedia ) {
		return false;
	}

	return window.matchMedia( '( prefers-reduced-motion: reduce )' ).matches;
}

function readThreshold( element: HTMLElement ): number {
	const raw = element.getAttribute( 'data-ap-anim-threshold' );
	if ( null === raw ) {
		return 0.2;
	}
	const parsed = parseFloat( raw );
	if ( Number.isNaN( parsed ) ) {
		return 0.2;
	}
	return Math.min( 1, Math.max( 0, parsed ) );
}

function readOnce( element: HTMLElement ): boolean {
	return 'false' !== element.getAttribute( 'data-ap-anim-once' );
}

function shouldSuppress( element: HTMLElement, reducedMotion: boolean ): boolean {
	if ( ! reducedMotion ) {
		return false;
	}

	return 'allow' !== element.getAttribute( 'data-ap-anim-reduced' );
}

function play( element: HTMLElement ): void {
	element.classList.remove( PRE_CLASS );
	element.classList.add( PLAY_CLASS );
}

function rearm( element: HTMLElement ): void {
	element.classList.remove( PLAY_CLASS );
	element.classList.add( PRE_CLASS );
}

export function bootstrap( options: RuntimeOptions = {} ): () => void {
	const root = options.root ?? ( 'undefined' === typeof document ? null : document );
	if ( null === root ) {
		return () => undefined;
	}

	// Per-bootstrap observer list so the returned disposer only tears
	// down THIS instance — concurrent mounts on different roots stay
	// isolated.
	const observers: IntersectionObserver[] = [];

	const reducedMotion = ( options.prefersReducedMotion ?? defaultReducedMotion )();

	if ( 'undefined' === typeof IntersectionObserver ) {
		// Without IO support, reveal everything immediately. The
		// noscript fallback CSS handles the no-JS case; this branch
		// handles JS-but-stone-age-browser.
		root
			.querySelectorAll<HTMLElement>( `.${ PRE_CLASS }` )
			.forEach( ( el ) => play( el ) );
		return () => undefined;
	}

	const entries = new Map<Element, ObservedEntry>();

	// One IntersectionObserver per threshold so each element gets the
	// exact threshold it requested instead of a coarse shared value.
	// Stored in a map so the MutationObserver path below can look up
	// (or lazily create) the right observer for a late-added element.
	const observersByThreshold = new Map<number, IntersectionObserver>();

	function observerFor( threshold: number ): IntersectionObserver {
		let observer = observersByThreshold.get( threshold );
		if ( observer ) {
			return observer;
		}
		observer = new IntersectionObserver(
			( ioEntries ) => {
				for ( const ioEntry of ioEntries ) {
					const meta = entries.get( ioEntry.target );
					if ( ! meta ) {
						continue;
					}

					if ( ioEntry.isIntersecting ) {
						if ( ! meta.played ) {
							play( meta.element );
							meta.played = true;
							if ( meta.once ) {
								observer!.unobserve( meta.element );
								entries.delete( meta.element );
							}
						}
					} else if ( ! meta.once && meta.played ) {
						rearm( meta.element );
						meta.played = false;
					}
				}
			},
			{ threshold },
		);
		observersByThreshold.set( threshold, observer );
		observers.push( observer );
		return observer;
	}

	const candidates = Array.from(
		root.querySelectorAll<HTMLElement>( '[data-ap-anim-entrance]' )
	);

	for ( const element of candidates ) {
		if ( shouldSuppress( element, reducedMotion ) ) {
			// Reveal immediately, skip observation.
			play( element );
			continue;
		}

		const entry: ObservedEntry = {
			element,
			threshold: readThreshold( element ),
			once:      readOnce( element ),
			played:    false,
		};
		entries.set( element, entry );

		observerFor( entry.threshold ).observe( element );
	}

	// Watch for entrance blocks that were initially `display: none`
	// (e.g. inside a closed accordion) and become visible later.
	let mutationObserver: MutationObserver | null = null;
	if ( 'undefined' !== typeof MutationObserver ) {
		mutationObserver = new MutationObserver( ( mutations ) => {
			for ( const mutation of mutations ) {
				for ( const node of Array.from( mutation.addedNodes ) ) {
					if ( ! ( node instanceof HTMLElement ) ) {
						continue;
					}
					const added = node.matches?.( '[data-ap-anim-entrance]' )
						? [ node ]
						: Array.from( node.querySelectorAll?.<HTMLElement>( '[data-ap-anim-entrance]' ) ?? [] );

					for ( const element of added ) {
						if ( entries.has( element ) ) {
							continue;
						}
						if ( shouldSuppress( element, reducedMotion ) ) {
							play( element );
							continue;
						}
						const entry: ObservedEntry = {
							element,
							threshold: readThreshold( element ),
							once:      readOnce( element ),
							played:    false,
						};
						entries.set( element, entry );
						observerFor( entry.threshold ).observe( element );
					}
				}
			}
		} );

		if ( root instanceof Document ) {
			mutationObserver.observe( root.body, { childList: true, subtree: true } );
		} else {
			mutationObserver.observe( root as ShadowRoot, { childList: true, subtree: true } );
		}
	}

	return () => {
		for ( const observer of observers ) {
			observer.disconnect();
		}
		observers.length = 0;
		entries.clear();
		mutationObserver?.disconnect();
	};
}

// Side-effecting auto-boot when imported in a browser.
if ( 'undefined' !== typeof document ) {
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', () => bootstrap(), { once: true } );
	} else {
		bootstrap();
	}
}
