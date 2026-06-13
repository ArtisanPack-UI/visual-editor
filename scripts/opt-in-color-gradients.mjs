#!/usr/bin/env node
// One-shot — add `gradients: true` to `supports.color` on every block.json
// that opts into any color slot (`background`, `text`, or `link`) but
// doesn't yet declare gradient support. Unlocks Gutenberg's auto-
// injected Color | Gradient tabbed picker for those blocks' backgrounds.
//
// Idempotent; re-running leaves blocks that already have `gradients: true`
// untouched.
//
// Run from the package root: `node scripts/opt-in-color-gradients.mjs`.
// Part of #490 (border gradients PR scope expansion).

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
				// no block.json in this dir — skip.
			}
		}
	}

	return out
}

function colorHasAnySlot( color ) {
	if ( ! color || 'object' !== typeof color ) {
		return false
	}

	return true === color.background || true === color.text || true === color.link
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

	const color = json.supports?.color

	if ( ! colorHasAnySlot( color ) ) {
		return false
	}

	if ( true === color.gradients ) {
		return false
	}

	json.supports.color = { ...color, gradients: true }

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
