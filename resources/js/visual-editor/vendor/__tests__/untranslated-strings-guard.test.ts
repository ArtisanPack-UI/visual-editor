/**
 * @vitest-environment node
 *
 * Untranslated-string guard (M-12).
 *
 * Every user-facing editor string must pass through `@wordpress/i18n`
 * with the package text domain (see `text-domain-guard.test.ts`). This
 * walks the JSX of every editor `.tsx` file and fails on English JSX
 * text, or a plain string literal handed to a user-facing prop such as
 * `label`, `title`, `placeholder` or `aria-label`.
 *
 * Block `save` and `deprecated` files are exempt: their markup is
 * serialized and must not vary by locale. The `sandbox/` dev harness
 * never ships.
 *
 * @since 1.15.0
 */

import { readFileSync } from 'node:fs';
import { relative, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

import { collectSourceFiles } from './i18n-call-scanner';
import { findUntranslatedJsxStrings } from './untranslated-jsx-scanner';

const SOURCE_DIR = fileURLToPath( new URL( '../..', import.meta.url ) );

/**
 * Strings that are deliberately literal. Keyed `path|kind|text`; each
 * entry needs a reason.
 */
const ALLOWED: Record<string, string> = {
	'blocks/howto/edit.tsx|placeholder|https://…': 'URL format hint, not prose.',
	'blocks/image/width-control.tsx|label|px': 'CSS unit suffix, the same in every locale.',
	'site-editor/navigation/link-picker.tsx|placeholder|https://example.com': 'Example URL, not prose.',
	'site-editor/styles/panels/font-family-picker.tsx|placeholder|system-ui, sans-serif': 'Example CSS font-family value.',
};

describe( 'untranslated JSX scanner', () => {
	it( 'flags English JSX text and literal user-facing props', () => {
		const source = [
			'const a = <p>No results found.</p>;',
			'const b = <Button label="Close" />;',
			"const c = <Button aria-label={ open ? 'Collapse' : 'Expand' } />;",
			"const d = <Input placeholder={ hint ?? 'Search…' } />;",
			'const e = <span title={ `Width ${ w }px` } />;',
		].join( '\n' );

		expect( findUntranslatedJsxStrings( source ) ).toEqual( [
			{ line: 1, kind: 'text', text: 'No results found.' },
			{ line: 2, kind: 'label', text: 'Close' },
			{ line: 3, kind: 'aria-label', text: 'Collapse' },
			{ line: 3, kind: 'aria-label', text: 'Expand' },
			{ line: 4, kind: 'placeholder', text: 'Search…' },
			{ line: 5, kind: 'title', text: 'Width ${…}px' },
		] );
	} );

	it( 'ignores translated strings, entities, punctuation and non-UI props', () => {
		const source = [
			"const a = <p>{ __( 'Hello', TEXT_DOMAIN ) }</p>;",
			"const b = <Button label={ __( 'Close', TEXT_DOMAIN ) } />;",
			'const c = <span>&rarr; — { count } · ( %)</span>;',
			'const d = <div className="is-large" data-type="core/paragraph" id="main" />;',
			"const e = <span title={ `${ a } — ${ b }` } label={ name } placeholder={ '…' } />;",
			"const f = <Button aria-label={ open ? __( 'Collapse', TEXT_DOMAIN ) : label } />;",
		].join( '\n' );

		expect( findUntranslatedJsxStrings( source ) ).toEqual( [] );
	} );
} );

describe( 'editor UI strings', () => {
	it( 'are all translated', () => {
		const offenders: string[] = [];
		const allowedSeen         = new Set<string>();

		for ( const file of collectSourceFiles( SOURCE_DIR ).sort() ) {
			const path = relative( SOURCE_DIR, file ).split( sep ).join( '/' );

			if ( ! path.endsWith( '.tsx' ) || path.startsWith( 'sandbox/' ) || /(^|\/)(save|deprecated)\.tsx$/.test( path ) ) {
				continue;
			}

			for ( const { line, kind, text } of findUntranslatedJsxStrings( readFileSync( file, 'utf8' ), file ) ) {
				const key = `${ path }|${ kind }|${ text }`;

				if ( key in ALLOWED ) {
					allowedSeen.add( key );
					continue;
				}

				offenders.push( `${ path }:${ line } ${ kind }: ${ JSON.stringify( text ) }` );
			}
		}

		expect( offenders ).toEqual( [] );
		// Keep the allowlist honest: drop entries once the string is gone.
		expect( Object.keys( ALLOWED ).filter( ( key ) => ! allowedSeen.has( key ) ) ).toEqual( [] );
	} );
} );
