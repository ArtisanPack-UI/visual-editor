#!/usr/bin/env node
/**
 * sync-fa-icons — copy Font Awesome Free SVGs + build the search index.
 *
 * Phase 3 of the Icon Block feature (#494, issue #554). Mirrors the SVGs
 * from `@fortawesome/fontawesome-free` (a devDependency) into the package's
 * tracked icon directory layout so the PHP icons registry can resolve
 * `iconRef` markers without reaching into node_modules at runtime.
 *
 * Output layout:
 *   resources/icons/font-awesome/
 *     fas/<name>.svg     (solid)
 *     far/<name>.svg     (regular)
 *     fab/<name>.svg     (brands)
 *     index.json         { version, generatedAt, sets, icons[] }
 *
 * The output directory is .gitignored — `npm run build` (which runs the
 * `prebuild` hook) keeps it in sync. The icons-registry filter in
 * VisualEditorServiceProvider is_dir-gates each set so a fresh checkout
 * boots even before the first `npm run build`.
 */

import { copyFileSync, mkdirSync, readFileSync, readdirSync, rmSync, writeFileSync, existsSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const __filename = fileURLToPath( import.meta.url )
const __dirname = dirname( __filename )
const PACKAGE_ROOT = resolve( __dirname, '..' )

const SOURCE_ROOT = resolve( PACKAGE_ROOT, 'node_modules/@fortawesome/fontawesome-free' )
const DEST_ROOT = resolve( PACKAGE_ROOT, 'resources/icons/font-awesome' )

// FA ships SVGs under `svgs/{solid,regular,brands}/<name>.svg`. We expose
// them under prefix directories that match the FA CSS class shorthand
// (`fas`/`far`/`fab`) so the iconRef in saved block content stays compact.
const SETS = [
	{ prefix: 'fas', source: 'solid', label: 'Solid' },
	{ prefix: 'far', source: 'regular', label: 'Regular' },
	{ prefix: 'fab', source: 'brands', label: 'Brands' },
]

function fail( message ) {
	console.error( `[sync-fa-icons] ${ message }` )
	process.exit( 1 )
}

function readJson( filePath ) {
	try {
		return JSON.parse( readFileSync( filePath, 'utf8' ) )
	} catch ( err ) {
		fail( `Failed to parse ${ filePath }: ${ err.message }` )
	}
}

function readSourceVersion() {
	const pkgPath = join( SOURCE_ROOT, 'package.json' )
	if ( ! existsSync( pkgPath ) ) {
		fail(
			`Font Awesome Free not installed. Run \`npm install\` to pull \`@fortawesome/fontawesome-free\` ` +
			`(declared as a devDependency in package.json).`,
		)
	}
	return readJson( pkgPath ).version
}

function readMetadata() {
	// `icon-families.json` is the canonical index — it carries the search
	// terms and human labels that Phase 4's server search needs. Falling
	// back to bare filenames is acceptable if the metadata vanishes in a
	// future FA release, but we want to know if that happens.
	const metaPath = join( SOURCE_ROOT, 'metadata/icon-families.json' )
	if ( ! existsSync( metaPath ) ) {
		console.warn( '[sync-fa-icons] icon-families.json missing — search terms will be empty.' )
		return {}
	}
	return readJson( metaPath )
}

function resetDir( path ) {
	rmSync( path, { recursive: true, force: true } )
	mkdirSync( path, { recursive: true } )
}

function copySet( set ) {
	const sourceDir = join( SOURCE_ROOT, 'svgs', set.source )
	const destDir = join( DEST_ROOT, set.prefix )

	if ( ! existsSync( sourceDir ) ) {
		fail( `Source directory missing: ${ sourceDir }` )
	}

	resetDir( destDir )

	const files = readdirSync( sourceDir ).filter( f => f.endsWith( '.svg' ) )
	for ( const file of files ) {
		copyFileSync( join( sourceDir, file ), join( destDir, file ) )
	}

	return files.map( f => f.slice( 0, -4 ) ).sort()
}

function buildIndex( setResults, metadata, version ) {
	// One row per (icon, set) pair so Phase 4's search can filter by family
	// without re-walking the directory tree. `terms` is the searchable
	// haystack; `label` is what the picker displays.
	const icons = []
	for ( const { set, names } of setResults ) {
		for ( const name of names ) {
			const meta = metadata[ name ] ?? {}
			const terms = Array.isArray( meta?.search?.terms ) ? meta.search.terms.map( String ) : []
			icons.push( {
				name,
				set: set.prefix,
				label: typeof meta.label === 'string' ? meta.label : name,
				terms,
			} )
		}
	}

	return {
		version,
		generatedAt: new Date().toISOString(),
		sets: SETS.map( s => ( { prefix: s.prefix, label: s.label, source: s.source } ) ),
		icons,
	}
}

function main() {
	const version = readSourceVersion()
	const metadata = readMetadata()

	mkdirSync( DEST_ROOT, { recursive: true } )

	const results = SETS.map( set => ( { set, names: copySet( set ) } ) )

	const index = buildIndex( results, metadata, version )
	writeFileSync( join( DEST_ROOT, 'index.json' ), JSON.stringify( index, null, 2 ) + '\n' )

	const total = results.reduce( ( n, r ) => n + r.names.length, 0 )
	console.log( `[sync-fa-icons] mirrored ${ total } icons from FA Free ${ version }` )
	for ( const { set, names } of results ) {
		console.log( `[sync-fa-icons]   ${ set.prefix } (${ set.label }): ${ names.length }` )
	}
}

main()
