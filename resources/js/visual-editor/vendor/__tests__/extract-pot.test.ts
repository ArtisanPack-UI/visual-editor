/**
 * @vitest-environment node
 *
 * `.pot` extractor coverage (M-12).
 *
 * Runs `scripts/extract-pot.mjs` in memory over the real source tree and
 * checks it against the lexical text-domain guard scanner: every
 * package-domain `__` / `_x` / `_n` / `_nx` call with a static msgid the
 * scanner finds must reach the `.pot`. Small fixtures pin the call shapes
 * the old regex extractor silently skipped.
 *
 * @since 1.15.0
 */

import { readFileSync } from 'node:fs';
import { relative, sep } from 'node:path';
import ts from 'typescript';
import { beforeAll, describe, expect, it } from 'vitest';

import {
	escapePotString,
	extract,
	extractFromSource,
	keyFor,
	renderPot,
	ROOT,
	SOURCE_DIR,
	type PotEntry,
} from '../../../../../scripts/extract-pot.mjs';
import { ALLOWED_DOMAINS, collectSourceFiles, DOMAIN_ARG_INDEX, findCalls } from './i18n-call-scanner';

/** Decode a source-text argument when it is a static string literal. */
function literalValue( argument: string | undefined ): string | undefined {
	if ( undefined === argument ) {
		return undefined;
	}

	const sourceFile = ts.createSourceFile( 'arg.ts', `(${ argument });`, ts.ScriptTarget.Latest, false, ts.ScriptKind.TS );
	const statement  = sourceFile.statements[ 0 ];
	if ( ! statement || ! ts.isExpressionStatement( statement ) || ! ts.isParenthesizedExpression( statement.expression ) ) {
		return undefined;
	}

	const expression = statement.expression.expression;

	return ts.isStringLiteral( expression ) || ts.isNoSubstitutionTemplateLiteral( expression ) ? expression.text : undefined;
}

describe( 'extract-pot over the source tree', () => {
	let entries: Map<string, PotEntry>;
	const warnings: string[] = [];

	beforeAll( async () => {
		entries = await extract( { warn: ( message ) => warnings.push( message ) } );
	} );

	it( 'extracts every static package-domain call the guard scanner finds', () => {
		const missing: string[] = [];
		let checked             = 0;

		for ( const file of collectSourceFiles( SOURCE_DIR ) ) {
			const rel = relative( ROOT, file ).split( sep ).join( '/' );

			for ( const call of findCalls( readFileSync( file, 'utf8' ) ) ) {
				if ( ! ALLOWED_DOMAINS.has( call.args[ DOMAIN_ARG_INDEX[ call.fn ] ] ?? '' ) ) {
					continue;
				}

				const msgid   = literalValue( call.args[ 0 ] );
				const msgctxt = '_x' === call.fn ? literalValue( call.args[ 1 ] ) : '_nx' === call.fn ? literalValue( call.args[ 3 ] ) : undefined;
				if ( undefined === msgid || ( ( '_x' === call.fn || '_nx' === call.fn ) && undefined === msgctxt ) ) {
					continue;
				}

				checked++;
				const entry = entries.get( keyFor( { msgid, msgctxt } ) );
				if ( ! entry?.references.has( `${ rel }:${ call.line }` ) ) {
					missing.push( `${ rel }:${ call.line } ${ call.fn }( ${ JSON.stringify( msgid ) } )` );
				}
			}
		}

		expect( checked ).toBeGreaterThan( 1500 );
		expect( missing ).toEqual( [] );
	} );

	it.each( [
		[ 'This form is unavailable — it may have been deleted or deactivated. Pick a different form in the block sidebar.', 'resources/js/visual-editor/blocks/form/edit.tsx' ],
		[ 'This navigation block uses a legacy format and could not be updated automatically. Update it to the current Navigation block to edit it.', 'resources/js/visual-editor/blocks/navigation/edit.tsx' ],
		[ 'Force every image inside this container onto a uniform aspect ratio.', 'resources/js/visual-editor/blocks/_shared/photo-grid/controls.tsx' ],
	] )( 'extracts the trailing-comma call %s', ( msgid, file ) => {
		const references = [ ...( entries.get( keyFor( { msgid } ) )?.references ?? [] ) ];

		expect( references.some( ( reference ) => reference.startsWith( `${ file }:` ) ) ).toBe( true );
	} );

	it( 'decodes escapes instead of copying them from the source', () => {
		expect( entries.has( keyFor( { msgid: "Shown in the parent query block's \"Post Variants\" panel." } ) ) ).toBe( true );
		expect( [ ...entries.values() ].some( ( entry ) => entry.msgid.includes( "\\'" ) ) ).toBe( false );
	} );

	it( 'attaches translators comments from the source', () => {
		const entry = entries.get( keyFor( { msgid: 'Failed to load form preview (status %d).' } ) );

		expect( [ ...( entry?.comments ?? [] ) ] ).toContain( 'translators: %d: HTTP status code.' );
	} );
} );

describe( 'extractFromSource', () => {
	const extractOne = ( source: string ) => extractFromSource( source, 'fixture.tsx' );

	it( 'reads a trailing comma after the domain', () => {
		const source = "__(\n\t'Long message',\n\tTEXT_DOMAIN,\n);";

		expect( extractOne( source ) ).toEqual( [ { msgid: 'Long message', line: 1, msgctxt: undefined, msgid_plural: undefined, comment: undefined } ] );
	} );

	it( 'reads no-substitution template literals for msgid, plural and context', () => {
		const source = [
			'__( `Template text`, TEXT_DOMAIN );',
			'_x( `Post`, `noun`, TEXT_DOMAIN );',
			"_nx( `%d item`, `%d items`, count, `cart`, 'artisanpack-visual-editor' );",
		].join( '\n' );

		expect( extractOne( source ).map( ( { msgid, msgctxt, msgid_plural } ) => ( { msgid, msgctxt, msgid_plural } ) ) ).toEqual( [
			{ msgid: 'Template text', msgctxt: undefined, msgid_plural: undefined },
			{ msgid: 'Post', msgctxt: 'noun', msgid_plural: undefined },
			{ msgid: '%d item', msgctxt: 'cart', msgid_plural: '%d items' },
		] );
	} );

	it( 'reads comments between arguments', () => {
		const source = "_x( /* msgid */ 'Open', // verb\n\t'menu action', /* domain */ TEXT_DOMAIN );";

		expect( extractOne( source ) ).toMatchObject( [ { msgid: 'Open', msgctxt: 'menu action' } ] );
	} );

	it( 'reads a nested call with commas in the count argument', () => {
		const source = "_n( '%d block', '%d blocks', Math.max( a, b ), TEXT_DOMAIN );";

		expect( extractOne( source ) ).toMatchObject( [ { msgid: '%d block', msgid_plural: '%d blocks' } ] );
	} );

	it( 'reads namespace calls and decodes escapes', () => {
		const source = "i18n.__( 'It\\'s \"quoted\"\\n\\tnext', TEXT_DOMAIN );";

		expect( extractOne( source ) ).toMatchObject( [ { msgid: 'It\'s "quoted"\n\tnext' } ] );
		expect( escapePotString( 'It\'s "quoted"\n\tnext\\' ) ).toBe( 'It\'s \\"quoted\\"\\n\\tnext\\\\' );
	} );

	it( 'skips other domains, comments, strings and declarations', () => {
		const source = [
			"__( 'Other', 'visual-editor' );",
			"_x( 'No domain', 'ctx' );",
			"// __( 'Commented', TEXT_DOMAIN );",
			"const s = \"__( 'In a string', TEXT_DOMAIN )\";",
			'function __( text: string, domain?: string ) { return text; }',
		].join( '\n' );

		expect( extractOne( source ) ).toEqual( [] );
	} );

	it( 'attaches translators comments on the same or previous line only', () => {
		const source = [
			'/* translators: %s: name. */',
			"sprintf( __( 'Hello %s', TEXT_DOMAIN ), name );",
			"const a = /* translators: %d: count */ _n( '%d x', '%d xs', n, TEXT_DOMAIN );",
			'// translators: line comment',
			"const b = __( 'Line', TEXT_DOMAIN );",
			'/*',
			' * translators: 1: first,',
			' *              2: second.',
			' */',
			"const c = __( '%1$s %2$s', TEXT_DOMAIN );",
			'// translators: too far away',
			'',
			"const d = __( 'Far', TEXT_DOMAIN );",
			'// not a translators comment',
			"const e = __( 'Plain', TEXT_DOMAIN );",
			"/* translators: first only */ f( __( 'First', TEXT_DOMAIN ), __( 'Second', TEXT_DOMAIN ) );",
		].join( '\n' );

		expect( extractOne( source ).map( ( { msgid, comment } ) => [ msgid, comment ] ) ).toEqual( [
			[ 'Hello %s', 'translators: %s: name.' ],
			[ '%d x', 'translators: %d: count' ],
			[ 'Line', 'translators: line comment' ],
			[ '%1$s %2$s', 'translators: 1: first, 2: second.' ],
			[ 'Far', undefined ],
			[ 'Plain', undefined ],
			[ 'First', 'translators: first only' ],
			[ 'Second', undefined ],
		] );
	} );

	it( 'reads translators comments in JSX expression containers but not JSX text', () => {
		const source = [
			'const el = (',
			'\t<p>',
			'\t\t{/* translators: shown in a JSX child. */}',
			"\t\t{__( 'Child', TEXT_DOMAIN )}",
			'\t\t// translators: this is JSX text',
			"\t\t{__( 'After text', TEXT_DOMAIN )}",
			'\t</p>',
			');',
		].join( '\n' );

		expect( extractOne( source ).map( ( { msgid, comment } ) => [ msgid, comment ] ) ).toEqual( [
			[ 'Child', 'translators: shown in a JSX child.' ],
			[ 'After text', undefined ],
		] );
	} );

	it( 'warns about package-domain calls whose strings are not static', () => {
		const warnings: string[] = [];
		const source             = [
			'__( message, TEXT_DOMAIN );',
			"__( 'a' + b, TEXT_DOMAIN );",
			'__( `Hi ${ name }`, TEXT_DOMAIN );',
			"_x( 'Post', context, TEXT_DOMAIN );",
			'__( message, OTHER_DOMAIN );',
		].join( '\n' );

		expect( extractFromSource( source, 'fixture.ts', ( message ) => warnings.push( message ) ) ).toEqual( [] );
		expect( warnings ).toEqual( [
			'fixture.ts:1: __() msgid is not a static string; skipped.',
			'fixture.ts:2: __() msgid is not a static string; skipped.',
			'fixture.ts:3: __() msgid is not a static string; skipped.',
			'fixture.ts:4: _x() context is not a static string; skipped.',
		] );
	} );
} );

describe( 'renderPot', () => {
	it( 'writes translators comments before sorted references', () => {
		const entries = new Map<string, PotEntry>( [
			[ 'k', { msgid: 'Say "hi"', comments: new Set( [ 'translators: greeting.' ] ), references: new Set( [ 'b.ts:2', 'a.ts:9' ] ) } ],
		] );

		expect( renderPot( entries ).split( '\n' ).slice( -6 ) ).toEqual( [
			'#. translators: greeting.',
			'#: a.ts:9',
			'#: b.ts:2',
			'msgid "Say \\"hi\\""',
			'msgstr ""',
			'',
		] );
	} );
} );
