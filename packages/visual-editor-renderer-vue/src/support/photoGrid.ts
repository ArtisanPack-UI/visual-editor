/**
 * Photo Grid wrapper scope — Vue renderer (#714).
 *
 * Mirrors `PhotoGridSupport::wrapperForBlock()` on the Blade renderer
 * (#594). A `core/group`, `core/columns` or `artisanpack/grid` block that
 * stores `photoGrid.enabled === true` gets a `has-photo-grid` class plus a
 * hashed `photo-grid-<12-char-sha1>` scope class spliced into its wrapper,
 * and the matching custom-property declarations pushed into a per-tree
 * accumulator that `BlockTree` flushes as one `<style data-ve-photo-grid>`
 * block.
 *
 * The scope class is hashed from the rendered declaration string with
 * {@link sha1Hex} exactly as Blade hashes it, so the token matches
 * byte-for-byte (kept honest by the #704 markup-parity check). Identical
 * configs collide on one scope, so the rule emits once.
 *
 * The mirror lives at the matching path in the React renderer.
 *
 * @package @artisanpack-ui/visual-editor-renderer-vue
 * @since 1.7.0
 */

import { phpTrim } from './attributes';
import type { Block } from '../types';
import { sha1Hex } from './sha1';

/**
 * Block names that expose the Photo Grid panel and therefore emit the
 * wrapper. Matches the three Blade partials that call
 * `PhotoGridSupport::wrapperForBlock()`.
 */
const PHOTO_GRID_BLOCK_NAMES = new Set<string>( [
	'core/group',
	'core/columns',
	'artisanpack/grid',
] );

export interface PhotoGridScope {
	/** The `has-photo-grid photo-grid-<hash>` class list for the wrapper. */
	className: string;
	/** The scoped custom-property rule for the accumulator. */
	css: string;
}

/**
 * Resolve the wrapper scope for a block's Photo Grid attribute, or `null`
 * when the feature is off. Returns the `has-photo-grid` + hashed scope
 * class list plus the `.photo-grid-<hash>{…}` rule, mirroring
 * `PhotoGridSupport::wrapperForBlock()`.
 */
export function photoGridScope( attributes: Record<string, unknown> ): PhotoGridScope | null {
	const photoGrid = attributes.photoGrid;

	if ( photoGrid === null || typeof photoGrid !== 'object' || Array.isArray( photoGrid ) ) {
		return null;
	}

	const bag = photoGrid as Record<string, unknown>;

	if ( bag.enabled !== true ) {
		return null;
	}

	const aspect = normaliseAspectRatio( bag.aspectRatio );
	const fit = normaliseObjectFit( bag.objectFit );
	const position = normaliseObjectPosition( bag.objectPosition );

	// Declaration order (fit, position, then optional aspect) mirrors
	// `PhotoGridSupport::wrapper()` so the hashed scope token matches Blade.
	const declarations = [
		`--ap-photo-grid-fit:${ fit }`,
		`--ap-photo-grid-position:${ position }`,
	];

	if ( aspect !== null ) {
		declarations.push( `--ap-photo-grid-aspect:${ aspect }` );
	}

	const declaration = `${ declarations.join( ';' ) };`;
	const scopeClass = `photo-grid-${ sha1Hex( declaration ).slice( 0, 12 ) }`;

	return {
		className: `has-photo-grid ${ scopeClass }`,
		css: `.${ scopeClass }{${ declaration }}`,
	};
}

/**
 * Walk a tree and stamp a `_vePhotoGridScope` class list onto every
 * Photo-Grid-enabled group / columns / grid block. The class list is
 * joined onto the wrapper by the block renderer; the accumulated CSS is
 * emitted once as a `<style data-ve-photo-grid>` block. Rules for identical
 * declarations share a scope and are emitted only once.
 */
export function stampPhotoGridScopes( tree: Block[] ): { tree: Block[]; css: string } {
	const seen = new Set<string>();
	const css: string[] = [];

	function walk( nodes: Block[] ): Block[] {
		return nodes.map( ( block ) => {
			if ( block === null || typeof block !== 'object' ) {
				return block;
			}

			const innerBlocks = Array.isArray( block.innerBlocks )
				? walk( block.innerBlocks as Block[] )
				: block.innerBlocks;

			const name = typeof block.name === 'string' ? block.name.trim() : '';
			// `_vePhotoGridScope` is a renderer-owned side channel that the
			// group / columns / grid renderers fold into the wrapper's class
			// list. Strip any value that arrived on the input tree so an
			// author-crafted block can't inject class tokens through it; it
			// is re-added below only when we compute a scope ourselves.
			const attrs = stripPhotoGridScope( attributesOf( block ) );

			if ( ! PHOTO_GRID_BLOCK_NAMES.has( name ) || attrs === null ) {
				return withAttributes( block, attrs, innerBlocks );
			}

			const scope = photoGridScope( attrs );

			if ( scope === null ) {
				return withAttributes( block, attrs, innerBlocks );
			}

			if ( ! seen.has( scope.className ) ) {
				seen.add( scope.className );
				css.push( scope.css );
			}

			return {
				...block,
				attributes: {
					...attrs,
					_vePhotoGridScope: scope.className,
				},
				innerBlocks,
			} as Block;
		} );
	}

	return { tree: walk( tree ), css: css.join( '' ) };
}

function attributesOf( block: Block ): Record<string, unknown> | null {
	const attrs = ( block as { attributes?: unknown } ).attributes;

	if ( attrs === null || typeof attrs !== 'object' || Array.isArray( attrs ) ) {
		return null;
	}

	return attrs as Record<string, unknown>;
}

/**
 * Return `attrs` without the renderer-owned `_vePhotoGridScope` key.
 * Allocates a copy only when the key is actually present, so the common
 * (clean) path keeps the original reference.
 */
function stripPhotoGridScope( attrs: Record<string, unknown> | null ): Record<string, unknown> | null {
	if ( attrs === null || ! ( '_vePhotoGridScope' in attrs ) ) {
		return attrs;
	}

	const rest: Record<string, unknown> = { ...attrs };
	delete rest._vePhotoGridScope;

	return rest;
}

/**
 * Rebuild a block, replacing its attributes only when `attrs` is a usable
 * object (a null means the block carried no plain-object attributes, so the
 * original is left untouched).
 */
function withAttributes(
	block: Block,
	attrs: Record<string, unknown> | null,
	innerBlocks: Block['innerBlocks']
): Block {
	if ( attrs === null ) {
		return { ...block, innerBlocks } as Block;
	}

	return { ...block, attributes: attrs, innerBlocks } as Block;
}

/**
 * Validate an aspect ratio token. Accepts `null` / `''` / `'auto'` /
 * `'inherit'` as "no aspect ratio", a positive `W/H` numeric pair as a
 * valid ratio, and rejects everything else (returns `null`). Mirrors
 * `PhotoGridSupport::normaliseAspectRatio()`.
 */
function normaliseAspectRatio( value: unknown ): string | null {
	if ( value === null || value === undefined || value === '' ) {
		return null;
	}

	if ( typeof value !== 'string' ) {
		return null;
	}

	const trimmed = phpTrim( value );

	if ( trimmed === '' || trimmed === 'auto' || trimmed === 'inherit' ) {
		return null;
	}

	if ( ! /^\d+(\.\d+)?\/\d+(\.\d+)?$/.test( trimmed ) ) {
		return null;
	}

	const [ w, h ] = trimmed.split( '/' ).map( Number );

	if ( ! Number.isFinite( w ) || ! Number.isFinite( h ) || w <= 0 || h <= 0 ) {
		return null;
	}

	return trimmed;
}

function normaliseObjectFit( value: unknown ): 'cover' | 'contain' {
	return value === 'contain' ? 'contain' : 'cover';
}

/**
 * Sanitise the object-position token with the same allowlist as
 * `PhotoGridSupport::normaliseObjectPosition()`: digits, percent, decimal,
 * sign, whitespace and the CSS keywords (top/right/bottom/left/center).
 * Anything else falls back to the default so a tampered `objectPosition`
 * cannot break out of the declaration and inject sibling rules.
 *
 * The whitespace set is spelled out (`[ \t\n\r\f\v]`) rather than `\s`
 * because the PHP allowlist runs under PCRE, whose `\s` is ASCII-only; a
 * bare JS `\s` also matches Unicode spaces (U+00A0, …), so a value carrying
 * one would pass here but be rejected by Blade — minting a different scope
 * hash and breaking the #704 parity contract.
 */
function normaliseObjectPosition( value: unknown ): string {
	if ( typeof value !== 'string' ) {
		return '50% 50%';
	}

	const trimmed = phpTrim( value );

	if ( trimmed === '' ) {
		return '50% 50%';
	}

	if ( ! /^[0-9%.+\- \t\n\r\f\va-z]+$/i.test( trimmed ) ) {
		return '50% 50%';
	}

	return trimmed;
}
