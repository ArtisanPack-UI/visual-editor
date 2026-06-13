import { afterEach, beforeEach, describe, expect, it } from 'vitest'

import { bootstrap } from '../runtime'

interface FakeObserver {
	callback: IntersectionObserverCallback
	options:  IntersectionObserverInit
	observed: Set<Element>
	trigger:  ( target: Element, isIntersecting: boolean ) => void
}

const observers: FakeObserver[] = []

class TestIntersectionObserver implements IntersectionObserver {
	root         = null
	rootMargin   = ''
	thresholds:  ReadonlyArray<number> = []
	private cb:  IntersectionObserverCallback

	observed = new Set<Element>()

	constructor( callback: IntersectionObserverCallback, options: IntersectionObserverInit = {} ) {
		this.cb = callback
		this.thresholds = Array.isArray( options.threshold )
			? options.threshold
			: [ ( options.threshold as number ) ?? 0 ]

		observers.push( {
			callback: callback,
			options:  options,
			observed: this.observed,
			trigger:  ( target: Element, isIntersecting: boolean ) => {
				this.cb(
					[ { target, isIntersecting, intersectionRatio: isIntersecting ? 1 : 0 } as IntersectionObserverEntry ],
					this,
				)
			},
		} )
	}

	observe( target: Element ): void {
		this.observed.add( target )
	}

	unobserve( target: Element ): void {
		this.observed.delete( target )
	}

	disconnect(): void {
		this.observed.clear()
	}

	takeRecords(): IntersectionObserverEntry[] {
		return []
	}
}

beforeEach( () => {
	observers.length = 0
	;( globalThis as unknown as { IntersectionObserver: typeof IntersectionObserver } ).IntersectionObserver =
		TestIntersectionObserver as unknown as typeof IntersectionObserver
} )

afterEach( () => {
	document.body.innerHTML = ''
} )

function placeBlock( html: string ): HTMLElement {
	const wrapper = document.createElement( 'div' )
	wrapper.innerHTML = html.trim()
	const node = wrapper.firstElementChild as HTMLElement
	document.body.appendChild( node )
	return node
}

describe( 'block-animations runtime', () => {
	it( 'swaps ap-anim-pre for ap-anim-play when a block enters the viewport', () => {
		const block = placeBlock( '<div class="ap-anim ap-anim-pre" data-ap-anim-entrance="fade-in"></div>' )

		const dispose = bootstrap( { root: document, prefersReducedMotion: () => false } )

		observers[ 0 ].trigger( block, true )

		expect( block.classList.contains( 'ap-anim-pre' ) ).toBe( false )
		expect( block.classList.contains( 'ap-anim-play' ) ).toBe( true )

		dispose()
	} )

	it( 'suppresses the entrance when reduced motion is on (block opts in)', () => {
		const block = placeBlock(
			'<div class="ap-anim ap-anim-pre" data-ap-anim-entrance="fade-in"></div>'
		)

		bootstrap( { root: document, prefersReducedMotion: () => true } )

		// Reveal happened immediately with no observer; no observer was
		// registered because the block was preemptively played.
		expect( block.classList.contains( 'ap-anim-play' ) ).toBe( true )
	} )

	it( 'plays the entrance even with reduced motion when the block opts out', () => {
		const block = placeBlock(
			'<div class="ap-anim ap-anim-pre" data-ap-anim-entrance="fade-in" data-ap-anim-reduced="allow"></div>'
		)

		bootstrap( { root: document, prefersReducedMotion: () => true } )

		// Reduced motion was overridden, so the observer takes over.
		expect( block.classList.contains( 'ap-anim-pre' ) ).toBe( true )
		observers[ 0 ].trigger( block, true )
		expect( block.classList.contains( 'ap-anim-play' ) ).toBe( true )
	} )

	it( 'unobserves the block after a single play by default', () => {
		const block = placeBlock( '<div class="ap-anim ap-anim-pre" data-ap-anim-entrance="fade-in"></div>' )

		bootstrap( { root: document, prefersReducedMotion: () => false } )
		observers[ 0 ].trigger( block, true )

		expect( observers[ 0 ].observed.has( block ) ).toBe( false )
	} )

	it( 'rearms the block when once=false and it leaves the viewport', () => {
		const block = placeBlock(
			'<div class="ap-anim ap-anim-pre" data-ap-anim-entrance="fade-in" data-ap-anim-once="false"></div>'
		)

		bootstrap( { root: document, prefersReducedMotion: () => false } )

		observers[ 0 ].trigger( block, true )
		expect( block.classList.contains( 'ap-anim-play' ) ).toBe( true )

		observers[ 0 ].trigger( block, false )
		expect( block.classList.contains( 'ap-anim-pre' ) ).toBe( true )
	} )

	it( 'reads the per-block threshold attribute', () => {
		placeBlock(
			'<div class="ap-anim ap-anim-pre" data-ap-anim-entrance="fade-in" data-ap-anim-threshold="0.5"></div>'
		)

		bootstrap( { root: document, prefersReducedMotion: () => false } )

		expect( observers[ 0 ].options.threshold ).toBe( 0.5 )
	} )

	it( 'uses one observer per distinct threshold', () => {
		placeBlock(
			'<div class="ap-anim ap-anim-pre" data-ap-anim-entrance="fade-in" data-ap-anim-threshold="0.1"></div>'
		)
		placeBlock(
			'<div class="ap-anim ap-anim-pre" data-ap-anim-entrance="fade-in-up" data-ap-anim-threshold="0.5"></div>'
		)

		bootstrap( { root: document, prefersReducedMotion: () => false } )

		const thresholds = observers.map( ( o ) => o.options.threshold ).sort()
		expect( thresholds ).toEqual( [ 0.1, 0.5 ] )
	} )

	it( 'shares observers for blocks at the same threshold', () => {
		placeBlock(
			'<div class="ap-anim ap-anim-pre" data-ap-anim-entrance="fade-in" data-ap-anim-threshold="0.2"></div>'
		)
		placeBlock(
			'<div class="ap-anim ap-anim-pre" data-ap-anim-entrance="fade-in-up" data-ap-anim-threshold="0.2"></div>'
		)

		bootstrap( { root: document, prefersReducedMotion: () => false } )

		expect( observers.length ).toBe( 1 )
		expect( observers[ 0 ].observed.size ).toBe( 2 )
	} )
} )
