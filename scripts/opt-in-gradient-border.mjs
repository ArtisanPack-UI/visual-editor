#!/usr/bin/env node
// One-shot — flip `supports.__experimentalBorder.gradient` (or
// `supports.border.gradient` for non-experimental opt-ins) to `true` on
// every block.json that already opts into border support. Idempotent;
// re-running leaves blocks already updated alone.
//
// Run from the package root: `node scripts/opt-in-gradient-border.mjs`.
// Part of #490 (border gradients).

import { promises as fs } from 'node:fs'
import path from 'node:path'

const SCAN_DIRS = [
	path.resolve( 'resources/js/visual-editor/blocks' ),
	path.resolve( 'resources/js/visual-editor/core-blocks' ),
]

async function listBlockJsonFiles( dir ) {
	try {
		await fs.access( dir )
	} catch {
		return []
	}

	const entries = await fs.readdir( dir, { withFileTypes: true } )
	const out     = []

	for ( const entry of entries ) {
		const full = path.join( dir, entry.name )

		if ( entry.isDirectory() ) {
			const nested = path.join( full, 'block.json' )
			try {
				await fs.access( nested )
				out.push( nested )
			} catch {
				// no block.json in this dir — fine, skip.
			}
		}
	}

	return out
}

function flipBorderSupport( supportObj ) {
	if ( ! supportObj || 'object' !== typeof supportObj ) {
		return { changed: false, value: supportObj }
	}

	if ( true === supportObj.gradient ) {
		return { changed: false, value: supportObj }
	}

	return {
		changed: true,
		value:   { ...supportObj, gradient: true },
	}
}

async function processOne( file ) {
	const raw = await fs.readFile( file, 'utf8' )
	let json

	try {
		json = JSON.parse( raw )
	} catch ( err ) {
		console.warn( `[skip] ${ file }: invalid JSON (${ err.message })` )
		return false
	}

	const supports = json.supports

	if ( ! supports || 'object' !== typeof supports ) {
		return false
	}

	let changed = false

	if ( supports.__experimentalBorder && 'object' === typeof supports.__experimentalBorder ) {
		const flipped = flipBorderSupport( supports.__experimentalBorder )

		if ( flipped.changed ) {
			supports.__experimentalBorder = flipped.value
			changed                       = true
		}
	}

	if ( supports.border && 'object' === typeof supports.border ) {
		const flipped = flipBorderSupport( supports.border )

		if ( flipped.changed ) {
			supports.border = flipped.value
			changed         = true
		}
	}

	if ( ! changed ) {
		return false
	}

	// Match the project's indentation: 4-space pretty-print.
	const next = JSON.stringify( json, null, 4 ) + '\n'
	await fs.writeFile( file, next, 'utf8' )

	return true
}

async function main() {
	let touched = 0
	let total   = 0

	for ( const dir of SCAN_DIRS ) {
		const files = await listBlockJsonFiles( dir )

		for ( const file of files ) {
			total++
			if ( await processOne( file ) ) {
				touched++
				console.log( `[updated] ${ path.relative( process.cwd(), file ) }` )
			}
		}
	}

	console.log( `\n${ touched } / ${ total } block.json files updated.` )
}

main().catch( ( err ) => {
	console.error( err )
	process.exit( 1 )
} )
