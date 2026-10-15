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
 * `_nx` call whose domain argument is anything else, and on any new call
 * with no domain at all (see `text-domain-baseline.json`).
 *
 * @since 1.15.0
 */

import { readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join, relative, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const SOURCE_DIR = fileURLToPath( new URL( '../..', import.meta.url ) );

const BASELINE_FILE = fileURLToPath( new URL( './text-domain-baseline.json', import.meta.url ) );

const MISSING_DOMAIN = '(none)';

const IGNORED_DIRS = new Set( [ '__tests__', '_legacy', 'node_modules', 'dist' ] );

/** Zero-based index of the domain argument for each i18n function. */
const DOMAIN_ARG_INDEX: Record<string, number> = { __: 1, _x: 2, _n: 3, _nx: 4 };

const ALLOWED_DOMAINS = new Set( [ 'TEXT_DOMAIN', "'artisanpack-visual-editor'", '"artisanpack-visual-editor"' ] );

// A `/` after one of these (or a keyword) starts a regex literal, not a division.
const REGEX_PRECEDERS = new Set( [ '', '(', ',', '=', ':', '[', '!', '&', '|', '?', '{', '}', ';', '+', '-', '*', '%', '<', '>', '~', '^' ] );

interface Call {
	fn: string;
	line: number;
	args: string[];
}

function collectSourceFiles( dir: string ): string[] {
	const found: string[] = [];

	for ( const entry of readdirSync( dir, { withFileTypes: true } ) ) {
		if ( entry.isDirectory() ) {
			if ( ! IGNORED_DIRS.has( entry.name ) ) {
				found.push( ...collectSourceFiles( join( dir, entry.name ) ) );
			}
			continue;
		}

		if ( /\.(ts|tsx)$/.test( entry.name ) && ! /\.(test|spec)\.tsx?$/.test( entry.name ) && ! entry.name.endsWith( '.d.ts' ) ) {
			found.push( join( dir, entry.name ) );
		}
	}

	return found;
}

/**
 * Replace comments, string bodies and regex literals with spaces (keeping
 * the quotes and newlines), so a structural scan sees only code.
 */
function maskNonCode( source: string ): string {
	const out = source.split( '' );
	let i = 0;
	let lastSignificant = '';
	let lastWord = '';

	const blank = ( from: number, to: number ): void => {
		for ( let k = from; k < to; k++ ) {
			if ( '\n' !== out[ k ] ) {
				out[ k ] = ' ';
			}
		}
	};

	const skipString = ( quote: string ): void => {
		i++;
		let chunkStart = i;

		while ( i < source.length && source[ i ] !== quote ) {
			if ( '\\' === source[ i ] ) {
				i += 2;
				continue;
			}

			// Template expression: blank the text so far, keep the
			// expression as code, and resume the text after its `}`.
			if ( '`' === quote && '$' === source[ i ] && '{' === source[ i + 1 ] ) {
				blank( chunkStart, i );
				i += 2;
				let depth = 1;
				while ( i < source.length && 0 < depth ) {
					const c = source[ i ];
					if ( "'" === c || '"' === c || '`' === c ) {
						skipString( c );
						continue;
					}
					if ( '{' === c ) {
						depth++;
					} else if ( '}' === c ) {
						depth--;
					}
					i++;
				}
				chunkStart = i;
				continue;
			}

			i++;
		}

		blank( chunkStart, i );
		i++;
	};

	while ( i < source.length ) {
		const c = source[ i ];
		const n = source[ i + 1 ];

		if ( '/' === c && '/' === n ) {
			const end = source.indexOf( '\n', i );
			const stop = -1 === end ? source.length : end;
			blank( i, stop );
			i = stop;
			continue;
		}

		if ( '/' === c && '*' === n ) {
			const end = source.indexOf( '*/', i + 2 );
			const stop = -1 === end ? source.length : end + 2;
			blank( i, stop );
			i = stop;
			continue;
		}

		if ( "'" === c || '"' === c || '`' === c ) {
			skipString( c );
			lastSignificant = c;
			lastWord = '';
			continue;
		}

		if ( '/' === c && ( REGEX_PRECEDERS.has( lastSignificant ) || 'return' === lastWord || 'typeof' === lastWord ) ) {
			const start = i;
			i++;
			let inClass = false;
			while ( i < source.length && '\n' !== source[ i ] ) {
				const r = source[ i ];
				if ( '\\' === r ) {
					i += 2;
					continue;
				}
				if ( '[' === r ) {
					inClass = true;
				} else if ( ']' === r ) {
					inClass = false;
				} else if ( '/' === r && ! inClass ) {
					break;
				}
				i++;
			}
			blank( start + 1, i );
			i++;
			lastSignificant = '/';
			lastWord = '';
			continue;
		}

		if ( ! /\s/.test( c ) ) {
			lastSignificant = c;
			lastWord = /[A-Za-z_$]/.test( c ) ? ( /[A-Za-z0-9_$]/.test( source[ i - 1 ] ?? '' ) ? lastWord + c : c ) : '';
		}

		i++;
	}

	return out.join( '' );
}

/**
 * Find every i18n call and split its top-level arguments (taken from the
 * original source, so literal domains stay readable).
 */
function findCalls( source: string ): Call[] {
	const masked = maskNonCode( source );
	const calls: Call[] = [];
	const pattern = /(?<![\w$.])(__|_x|_nx|_n)\s*\(/g;

	for ( const match of masked.matchAll( pattern ) ) {
		const open = ( match.index ?? 0 ) + match[ 0 ].length - 1;
		const args: string[] = [];
		let depth = 0;
		let argStart = open + 1;
		let i = open;

		for ( ; i < masked.length; i++ ) {
			const c = masked[ i ];
			if ( '(' === c || '[' === c || '{' === c ) {
				depth++;
			} else if ( ')' === c || ']' === c || '}' === c ) {
				depth--;
				if ( 0 === depth ) {
					break;
				}
			} else if ( ',' === c && 1 === depth ) {
				args.push( source.slice( argStart, i ).trim() );
				argStart = i + 1;
			}
		}

		const last = source.slice( argStart, i ).trim();
		if ( '' !== last ) {
			args.push( last );
		}

		// A declaration (`function __( text: string, … )`) is not a call.
		const before = masked.slice( Math.max( 0, ( match.index ?? 0 ) - 20 ), match.index ).trimEnd();
		if ( /\bfunction$/.test( before ) ) {
			continue;
		}

		calls.push( {
			fn: match[ 1 ],
			line: source.slice( 0, match.index ).split( '\n' ).length,
			args,
		} );
	}

	return calls;
}

function offendersIn( source: string ): Array<{ line: number; fn: string; domain: string }> {
	return findCalls( source )
		.map( ( call ) => ( { ...call, domain: call.args[ DOMAIN_ARG_INDEX[ call.fn ] ] ?? MISSING_DOMAIN } ) )
		.filter( ( call ) => ! ALLOWED_DOMAINS.has( call.domain ) )
		.map( ( { line, fn, domain } ) => ( { line, fn, domain } ) );
}

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

function scanSources(): { wrongDomain: string[]; missingDomainByFile: Record<string, number> } {
	const wrongDomain: string[] = [];
	const missingDomainByFile: Record<string, number> = {};

	for ( const file of collectSourceFiles( SOURCE_DIR ).sort() ) {
		const path = relative( SOURCE_DIR, file ).split( sep ).join( '/' );

		for ( const { line, fn, domain } of offendersIn( readFileSync( file, 'utf8' ) ) ) {
			if ( MISSING_DOMAIN === domain ) {
				missingDomainByFile[ path ] = ( missingDomainByFile[ path ] ?? 0 ) + 1;
				continue;
			}

			wrongDomain.push( `${ path }:${ line } ${ fn }() domain ${ domain }` );
		}
	}

	return { wrongDomain, missingDomainByFile };
}

describe( 'editor i18n calls', () => {
	const { wrongDomain, missingDomainByFile } = scanSources();

	if ( process.env.UPDATE_TEXT_DOMAIN_BASELINE ) {
		writeFileSync( BASELINE_FILE, JSON.stringify( missingDomainByFile, null, '\t' ) + '\n' );
	}

	it( 'never pass a text domain other than artisanpack-visual-editor', () => {
		expect( wrongDomain ).toEqual( [] );
	} );

	/*
	 * Code ported from Gutenberg calls `__()` with no domain. Those
	 * strings are not extracted either; until they are migrated, the
	 * baseline only lets their count go down. Regenerate it after a
	 * migration with `UPDATE_TEXT_DOMAIN_BASELINE=1 npx vitest run` on
	 * this file.
	 */
	it( 'add no new calls without a text domain', () => {
		const baseline = JSON.parse( readFileSync( BASELINE_FILE, 'utf8' ) ) as Record<string, number>;
		const grown: string[] = [];

		for ( const [ path, count ] of Object.entries( missingDomainByFile ) ) {
			if ( count > ( baseline[ path ] ?? 0 ) ) {
				grown.push( `${ path }: ${ count } call(s) without a domain (baseline ${ baseline[ path ] ?? 0 })` );
			}
		}

		expect( grown ).toEqual( [] );
	} );
} );
