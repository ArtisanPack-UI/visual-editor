import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const css = readFileSync( resolve( __dirname, '../a11y.css' ), 'utf8' );

/**
 * See #765 — every classless `<button>` the package renders must still
 * pick up a pointer cursor, a disabled `not-allowed` cursor, and a
 * visible focus ring. These are shipped by low-specificity `:where()`
 * base rules in a11y.css so component CSS overrides them without
 * `!important`.
 */
describe( 'a11y.css button base rules', () => {
	it( 'sets cursor: pointer on enabled buttons via zero-specificity selector', () => {
		expect( css ).toMatch( /:where\(\s*button:not\(:disabled\)\s*\)\s*\{[^}]*cursor:\s*pointer/ );
	} );

	it( 'sets cursor: not-allowed on disabled buttons', () => {
		expect( css ).toMatch( /:where\(\s*button:disabled\s*\)\s*\{[^}]*cursor:\s*not-allowed/ );
	} );

	it( 'renders a visible focus-visible outline (WCAG 2.4.7)', () => {
		expect( css ).toMatch(
			/:where\(\s*button:focus-visible\s*\)\s*\{[^}]*outline:\s*2px\s+solid[^}]*outline-offset:\s*2px/s,
		);
	} );
} );
