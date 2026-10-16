/**
 * @vitest-environment node
 *
 * Text-domain guard (M-12).
 *
 * `scripts/extract-pot.mjs` only extracts calls bound to
 * `TEXT_DOMAIN` / `'artisanpack-visual-editor'`, so a string with any
 * other domain (or none) never reaches the `.pot` and can't be
 * translated. This scans every non-test source file under
 * `resources/js/visual-editor` and fails on any `__`, `_x`, `_n` or
 * `_nx` call whose domain argument is anything else or missing.
 *
 * @since 1.15.0
 */

import { readFileSync } from 'node:fs';
import { relative, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

import { collectSourceFiles, findCalls, offendersIn } from './i18n-call-scanner';

const SOURCE_DIR = fileURLToPath( new URL( '../..', import.meta.url ) );

describe( 'text-domain guard scanner', () => {
	it( 'accepts TEXT_DOMAIN and the literal package domain', () => {
		expect( offendersIn( "__( 'A', TEXT_DOMAIN ); _x( 'B', 'ctx', 'artisanpack-visual-editor' );" ) ).toEqual( [] );
	} );

	it( 'flags other domains, missing domains and every i18n function', () => {
		const source = [
			"__( 'A', 'visual-editor' );",
			"_x( 'B', 'ctx' );",
			"_n( 'one', 'many', count, 'other' );",
			"sprintf( _nx( '%d x', '%d xs', n, 'ctx', 'visual-editor' ), n );",
		].join( '\n' );

		expect( offendersIn( source ) ).toEqual( [
			{ line: 1, fn: '__', domain: "'visual-editor'" },
			{ line: 2, fn: '_x', domain: '(none)' },
			{ line: 3, fn: '_n', domain: "'other'" },
			{ line: 4, fn: '_nx', domain: "'visual-editor'" },
		] );
	} );

	it( 'reads multi-line calls with commas and parentheses inside strings', () => {
		const source = "__(\n\t'Hello, (world)',\n\t'visual-editor'\n)";

		expect( offendersIn( source ) ).toEqual( [ { line: 1, fn: '__', domain: "'visual-editor'" } ] );
	} );

	it( 'ignores comments, strings, regex literals, member calls and declarations', () => {
		const source = [
			"// __( 'x', 'visual-editor' )",
			"/* _x( 'y', 'c', 'nope' ) */",
			"const s = \"__( 'z', 'nope' )\";",
			"const r = /__\\(['\"]/;",
			"obj.__( 'a', 'nope' );",
			'function __( text: string, domain?: string ) {}',
			"__( `tpl ${ a( 1, 2 ) }`, TEXT_DOMAIN );",
		].join( '\n' );

		expect( offendersIn( source ) ).toEqual( [] );
	} );
} );

describe( 'editor i18n calls', () => {
	it( 'all pass the artisanpack-visual-editor text domain', () => {
		const offenders: string[] = [];

		for ( const file of collectSourceFiles( SOURCE_DIR ).sort() ) {
			const path = relative( SOURCE_DIR, file ).split( sep ).join( '/' );

			for ( const { line, fn, domain } of offendersIn( readFileSync( file, 'utf8' ) ) ) {
				offenders.push( `${ path }:${ line } ${ fn }() domain ${ domain }` );
			}
		}

		expect( offenders ).toEqual( [] );
	} );
} );

/*
 * Serialized markup must not depend on the editor's locale: once
 * translations load, a translated string in `save()` would make every
 * block saved in another language invalid. Block `save` and
 * `deprecated` files therefore use plain literals.
 */
describe( 'block save code', () => {
	it( 'calls no i18n function in save or deprecated files', () => {
		const offenders: string[] = [];

		for ( const file of collectSourceFiles( SOURCE_DIR ).sort() ) {
			if ( ! /(^|[\\/])(save|deprecated)\.tsx?$/.test( file ) ) {
				continue;
			}

			const path = relative( SOURCE_DIR, file ).split( sep ).join( '/' );

			for ( const { line, fn } of findCalls( readFileSync( file, 'utf8' ) ) ) {
				offenders.push( `${ path }:${ line } ${ fn }()` );
			}
		}

		expect( offenders ).toEqual( [] );
	} );
} );
