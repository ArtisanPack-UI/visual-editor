import { describe, expect, it } from 'vitest'

import { emitStateCss } from '../css-emitter'
import { DEFAULT_STATES, StateRegistry } from '../registry'

const registry = new StateRegistry( DEFAULT_STATES )

describe( 'emitStateCss', () => {
	it( 'returns empty when scope is blank or states are null', () => {
		expect( emitStateCss( '', { 'style.color.background': { idle: 'red' } }, registry ) ).toBe( '' )
		expect( emitStateCss( '.scope', null, registry ) ).toBe( '' )
		expect( emitStateCss( '.scope', {}, registry ) ).toBe( '' )
	} )

	it( 'emits idle rule against the scope alone (no descendant mirror)', () => {
		const css = emitStateCss(
			'.ap-state-abc',
			{ 'style.color.background': { idle: 'red' } },
			registry,
		)

		expect( css ).toBe( '.ap-state-abc { background-color: red !important; }' )
		// Idle must NOT cascade into nested links — that would override
		// inner button/link colors set by their own block-supports.
		expect( css ).not.toContain( ':is(a, button)' )
	} )

	it( 'mirrors pseudo-state selectors onto descendant interactive elements', () => {
		const css = emitStateCss(
			'.ap-state-abc',
			{ 'style.color.background': { idle: 'red', hover: 'blue' } },
			registry,
		)

		// Hover rule must reach the inner link/button so e.g.
		// `wp-block-button__link` actually flips color on hover.
		expect( css ).toContain( '.ap-state-abc:hover, .ap-state-abc:hover :is(a, button)' )
	} )

	it( 'wraps hover rules in @media (hover: hover) and adds default transition', () => {
		const css = emitStateCss(
			'.ap-state-abc',
			{ 'style.color.background': { idle: 'red', hover: 'blue' } },
			registry,
		)

		expect( css ).toContain( 'background-color: red !important; transition: all 150ms ease;' )
		expect( css ).toContain( '@media (hover: hover) {' )
		expect( css ).toContain( '.ap-state-abc:hover' )
		expect( css ).toContain( 'background-color: blue !important;' )
	} )

	it( 'respects an editor-authored transition', () => {
		const css = emitStateCss(
			'.ap-state-abc',
			{
				'style.color.background': { idle: 'red', hover: 'blue' },
				'transition':             { idle: 'transform 200ms ease-out' },
			},
			registry,
		)

		// `transition` is in the NEVER_IMPORTANT set — host code can
		// still cancel the editor's transition with a plain rule.
		expect( css ).toContain( 'transition: transform 200ms ease-out;' )
		expect( css ).not.toContain( 'transition: transform 200ms ease-out !important;' )
		expect( css ).not.toContain( 'transition: all 150ms ease;' )
	} )

	it( 'skips redundant state rules when the value equals the inheritance parent', () => {
		const css = emitStateCss(
			'.ap-state-abc',
			{ 'style.color.background': { idle: 'red', hover: 'red' } },
			registry,
		)

		expect( css ).toContain( '{ background-color: red !important; }' )
		expect( css ).not.toContain( ':hover' )
	} )

	it( 'emits non-hover state rules outside the hover-media wrap', () => {
		const css = emitStateCss(
			'.ap-state-abc',
			{ 'style.color.text': { idle: 'red', 'focus-visible': 'navy' } },
			registry,
		)

		expect( css ).toContain( '.ap-state-abc:focus-visible' )
		expect( css ).toContain( 'color: navy !important;' )
		expect( css ).not.toContain( '@media (hover: hover) { .ap-state-abc:focus-visible' )
	} )

	it( 'handles comma-separated selector lists for disabled', () => {
		const css = emitStateCss(
			'.ap-state-abc',
			{ 'style.color.background': { idle: 'red', disabled: '#888' } },
			registry,
		)

		expect( css ).toContain( '.ap-state-abc:disabled' )
		expect( css ).toContain( '.ap-state-abc[aria-disabled="true"]' )
	} )

	it( 'accepts paths with or without the leading `style.` prefix', () => {
		const withPrefix = emitStateCss(
			'.s',
			{ 'style.color.background': { idle: 'red' } },
			registry,
		)
		const withoutPrefix = emitStateCss(
			'.s',
			{ 'color.background': { idle: 'red' } },
			registry,
		)

		expect( withPrefix ).toBe( withoutPrefix )
	} )

	it( 'silently drops unknown paths', () => {
		const css = emitStateCss(
			'.s',
			{ 'made.up.path': { idle: 'red' } },
			registry,
		)

		expect( css ).toBe( '' )
	} )

	it( 'translates palette slugs to wp preset vars on top-level color attrs', () => {
		const css = emitStateCss(
			'.scope',
			{
				backgroundColor: { idle: 'vivid-purple', hover: 'pale-pink' },
			},
			registry,
		)

		expect( css ).toContain( 'background-color: var(--wp--preset--color--vivid-purple) !important;' )
		expect( css ).toContain( '.scope:hover' )
		expect( css ).toContain( 'background-color: var(--wp--preset--color--pale-pink) !important;' )
	} )

	it( 'passes raw CSS values through unchanged for preset paths', () => {
		const css = emitStateCss(
			'.scope',
			{ backgroundColor: { idle: '#abc123', hover: 'var(--brand-accent)' } },
			registry,
		)

		expect( css ).toContain( 'background-color: #abc123 !important;' )
		expect( css ).toContain( 'background-color: var(--brand-accent) !important;' )
		expect( css ).not.toContain( 'var(--wp--preset--color--#abc123)' )
	} )

	it( 'maps gradient slugs to gradient presets', () => {
		const css = emitStateCss(
			'.scope',
			{ gradient: { idle: 'blush-bordeaux' } },
			registry,
		)

		expect( css ).toContain( 'background: var(--wp--preset--gradient--blush-bordeaux) !important;' )
	} )
	it( 'expands var:preset references on every path (#875)', () => {
		const css = emitStateCss(
			'.scope',
			{
				'style.color.background': { hover: 'var:preset|color|primary' },
				'border.color':           { hover: 'var:preset|color|accentDark' },
				'shadow':                 { focus: 'var:preset|shadow|natural' },
				'gradient':               { hover: 'var:preset|gradient|sunset_glow' },
			},
			registry,
		)

		expect( css ).toContain( 'background-color: var(--wp--preset--color--primary) !important;' )
		expect( css ).toContain( 'border-color: var(--wp--preset--color--accent-dark) !important;' )
		expect( css ).toContain( 'box-shadow: var(--wp--preset--shadow--natural) !important;' )
		expect( css ).toContain( 'background: var(--wp--preset--gradient--sunset-glow) !important;' )
		expect( css ).not.toContain( 'var:preset' )
	} )

	it( 'leaves var:preset references raw when expandPresets is off (1.14 shape)', () => {
		const css = emitStateCss(
			'.scope',
			{ 'border.color': { hover: 'var:preset|color|accentDark' } },
			registry,
			{ expandPresets: false },
		)

		expect( css ).toContain( 'border-color: var:preset|color|accentDark !important;' )
	} )
} )

describe( 'emitStateCss reduced-motion guard (F-9)', () => {
	it( 'turns the idle transition off and holds hover transforms still', () => {
		const css = emitStateCss(
			'.s',
			{ 'dimensions.transform': { hover: 'scale(1.05)' } },
			registry,
		)

		expect( css ).toBe(
			'.s { transition: all 150ms ease; } '
			+ '@media (hover: hover) { .s:hover, .s:hover :is(a, button) { transform: scale(1.05) !important; } } '
			+ '@media (prefers-reduced-motion: reduce) { .s { transition: none !important; } '
			+ '.s:hover, .s:hover :is(a, button) { transform: none !important; } }',
		)
	} )

	it( 'holds a state transform at the authored idle transform', () => {
		const css = emitStateCss(
			'.s',
			{ 'dimensions.transform': { idle: 'rotate(2deg)', focus: 'rotate(2deg) scale(1.1)' } },
			registry,
		)

		expect( css ).toContain(
			'@media (prefers-reduced-motion: reduce) { .s { transition: none !important; } '
			+ '.s:focus, .s:focus :is(a, button) { transform: rotate(2deg) !important; } }',
		)
	} )

	it( 'turns off a transition authored on a non-idle state', () => {
		const css = emitStateCss(
			'.s',
			{
				'color.background': { idle: 'red', hover: 'blue' },
				'transition':       { idle: 'color 1s', hover: 'background-color 2s' },
			},
			registry,
		)

		expect( css ).toContain(
			'@media (prefers-reduced-motion: reduce) { .s { transition: none !important; } '
			+ '.s:hover, .s:hover :is(a, button) { transition: none !important; } }',
		)
	} )

	it( 'emits no guard when nothing can animate or move', () => {
		const css = emitStateCss( '.s', { 'color.background': { idle: 'red' } }, registry )

		expect( css ).toBe( '.s { background-color: red !important; }' )
	} )

	it( 'can be switched off for the pre-guard saved shape', () => {
		const css = emitStateCss(
			'.s',
			{ 'dimensions.transform': { hover: 'scale(1.05)' } },
			registry,
			{ reducedMotionGuard: false },
		)

		expect( css ).not.toContain( 'prefers-reduced-motion' )
	} )
} )
