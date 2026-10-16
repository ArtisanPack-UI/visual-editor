/**
 * Lexical scanner for `@wordpress/i18n` calls (M-12).
 *
 * Shared by the text-domain guard test and the one-off codemod that
 * added the domain to the Gutenberg-ported strings. Comments, string
 * bodies and regex literals are masked out before the scan, so calls
 * inside them are ignored and commas or parentheses inside string
 * arguments don't split them.
 *
 * @since 1.15.0
 */

import { readdirSync } from 'node:fs';
import { join } from 'node:path';

/** Placeholder domain reported for a call that passes none. */
export const MISSING_DOMAIN = '(none)';

export const IGNORED_DIRS = new Set( [ '__tests__', '_legacy', 'node_modules', 'dist' ] );

/** Zero-based index of the domain argument for each i18n function. */
export const DOMAIN_ARG_INDEX: Record<string, number> = { __: 1, _x: 2, _n: 3, _nx: 4 };

export const ALLOWED_DOMAINS = new Set( [ 'TEXT_DOMAIN', "'artisanpack-visual-editor'", '"artisanpack-visual-editor"' ] );

// A `/` after one of these (or a keyword) starts a regex literal, not a division.
const REGEX_PRECEDERS = new Set( [ '', '(', ',', '=', ':', '[', '!', '&', '|', '?', '{', '}', ';', '+', '-', '*', '%', '<', '>', '~', '^' ] );

/**
 * One i18n call found in a source file.
 *
 * @since 1.15.0
 */
export interface I18nCall {
	fn: string;
	line: number;
	args: string[];
	/** Offset of the call's `(`. */
	open: number;
	/** Offset of the call's `)`. */
	close: number;
}

export function collectSourceFiles( dir: string ): string[] {
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
export function maskNonCode( source: string ): string {
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
export function findCalls( source: string ): I18nCall[] {
	const masked = maskNonCode( source );
	const calls: I18nCall[] = [];
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
			open,
			close: i,
		} );
	}

	return calls;
}

export function offendersIn( source: string ): Array<{ line: number; fn: string; domain: string }> {
	return findCalls( source )
		.map( ( call ) => ( { ...call, domain: call.args[ DOMAIN_ARG_INDEX[ call.fn ] ] ?? MISSING_DOMAIN } ) )
		.filter( ( call ) => ! ALLOWED_DOMAINS.has( call.domain ) )
		.map( ( { line, fn, domain } ) => ( { line, fn, domain } ) );
}
