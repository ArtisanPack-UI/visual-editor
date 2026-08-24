/**
 * Column responsive-width scope — Vue renderer (#712).
 *
 * Mirrors the `ve-w-<hash>` mechanism the Blade partial
 * `blocks/core/column.blade.php` introduced in #487. A `core/column` with an
 * explicit `width` (or per-breakpoint `responsive.width`) needs a class-based
 * `flex-basis`/`flex-grow` rule with `!important` to beat WP core's mobile
 * stacking rule (`… > .wp-block-column { flex-basis: 100% !important }`) below
 * 782px — an inline style alone loses on specificity. This module reproduces
 * the Blade partial's merge, hash and rule emission so the React renderer
 * mints the *same* scope token and applies the same width at every viewport.
 *
 * The scope class is hashed from the merged `{base, sm, md, …}` width map with
 * {@link xxh3_64_hex} exactly as Blade hashes it, so the token matches
 * byte-for-byte (kept honest by the #704 markup-parity check).
 *
 * @package @artisanpack-ui/visual-editor-renderer-vue
 * @since 1.7.0
 */

import { formatPercent } from './attributes';
import { getBreakpoints } from '../visibility';
import type { Block } from '../types';
import { xxh3_64_hex } from './xxh3';

const BASE_KEY = 'base';

const COLUMN_BLOCK_NAME = 'core/column';

export interface ColumnWidthScope {
	/** The `ve-w-<hash>` class stamped onto the column wrapper. */
	className: string;
	/** The accumulated CSS rules for every surviving breakpoint. */
	css: string;
}

interface NormalizedBasis {
	basis: string;
	percent: number | null;
}

/**
 * Resolve the scope class + CSS for a column's width attributes, or `null`
 * when the column carries no width (or only orphan-breakpoint overrides that
 * emit no rule) — matching the Blade partial, which attaches the class only
 * once at least one rule survives.
 */
export function columnWidthScope( attributes: Record<string, unknown> ): ColumnWidthScope | null {
	const merged = mergeResponsiveWidths( attributes );
	const keys = Object.keys( merged );

	if ( keys.length === 0 ) {
		return null;
	}

	let json: string;
	try {
		json = phpJsonEncode( merged );
	} catch {
		return null;
	}

	const className = `ve-w-${ xxh3_64_hex( json ).slice( 0, 10 ) }`;

	// Triple-class selector matches the (0,0,3,0) specificity of WP's
	// stacking rule; `!important` on both declarations plus source order
	// (this style block renders after the external stylesheets) wins the
	// cascade. See the matching comment in column.blade.php.
	const selector = `.${ className }.${ className }.${ className }`;

	const breakpoints = new Map( getBreakpoints().map( ( bp ) => [ bp.key, bp.minWidthPx ] ) );
	const rules: string[] = [];

	for ( const key of keys ) {
		const value = merged[ key ];

		if ( value === null || value === undefined || value === '' ) {
			continue;
		}

		// Reject anything that isn't a plain number, percentage, or CSS
		// length before it reaches the emitted stylesheet. A stored value
		// such as `10px}body{display:none` would otherwise close the rule
		// and inject attacker-chosen CSS into `<style data-ve-column-width>`.
		const normalized = normalizeBasis( value );
		if ( normalized === null ) {
			continue;
		}

		const declaration = `flex-basis:${ basisExpr( normalized ) }!important;flex-grow:0!important`;

		if ( key === BASE_KEY ) {
			rules.push( `${ selector }{${ declaration }}` );
			continue;
		}

		const minWidth = breakpoints.get( key );
		if ( minWidth === undefined ) {
			continue;
		}

		rules.push( `@media (min-width:${ minWidth }px){${ selector }{${ declaration }}}` );
	}

	if ( rules.length === 0 ) {
		return null;
	}

	return { className, css: rules.join( '' ) };
}

/**
 * Walk a tree and stamp a `_veColumnWidthScope` class onto every
 * `core/column` whose width resolves to a rule. The class is joined onto the
 * column wrapper by the renderer; the accumulated CSS is emitted once as a
 * `<style data-ve-column-width>` block. Rules for identical width maps share
 * a scope and are emitted only once.
 */
export function stampColumnWidthScopes( tree: Block[] ): { tree: Block[]; css: string } {
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
			// `_veColumnWidthScope` is a renderer-owned side channel that
			// `ColumnBlock` folds into the wrapper's class list. Strip any
			// value that arrived on the input tree so an author-crafted
			// block can't inject class tokens through it; it is re-added
			// below only when we compute a scope ourselves.
			const attrs = stripWidthScope( attributesOf( block ) );

			if ( name !== COLUMN_BLOCK_NAME || attrs === null ) {
				return withAttributes( block, attrs, innerBlocks );
			}

			const scope = columnWidthScope( attrs );

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
					_veColumnWidthScope: scope.className,
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
 * Return `attrs` without the renderer-owned `_veColumnWidthScope` key.
 * Allocates a copy only when the key is actually present, so the common
 * (clean) path keeps the original reference.
 */
function stripWidthScope( attrs: Record<string, unknown> | null ): Record<string, unknown> | null {
	if ( attrs === null || ! ( '_veColumnWidthScope' in attrs ) ) {
		return attrs;
	}

	const rest: Record<string, unknown> = { ...attrs };
	delete rest._veColumnWidthScope;

	return rest;
}

/**
 * Rebuild a block, replacing its attributes only when `attrs` is a
 * usable object (a null means the block carried no plain-object
 * attributes, so the original is left untouched).
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
 * Build the merged, order-preserving `{base, sm, …}` width map the scope is
 * hashed from. The legacy scalar `width` is promoted into the `base` slot
 * only when the editor has not already written a base override — exactly the
 * branch the Blade partial takes.
 */
function mergeResponsiveWidths( attributes: Record<string, unknown> ): Record<string, unknown> {
	const responsive = attributes.responsive;
	const responsiveWidthsRaw =
		responsive !== null && typeof responsive === 'object' && ! Array.isArray( responsive )
			? ( responsive as Record<string, unknown> ).width
			: undefined;

	const responsiveWidths =
		responsiveWidthsRaw !== null &&
		typeof responsiveWidthsRaw === 'object' &&
		! Array.isArray( responsiveWidthsRaw )
			? ( responsiveWidthsRaw as Record<string, unknown> )
			: {};

	const width = attributes.width;
	const baseSet = responsiveWidths[ BASE_KEY ] !== undefined && responsiveWidths[ BASE_KEY ] !== null;

	if ( isNonEmptyWidth( width ) && ! baseSet ) {
		// Promote `width` into the base slot, keeping every other override
		// in its stored order — the exact shape (and iteration order) the
		// Blade partial's `[ 'base' => … ] + $responsiveWidths` produces,
		// so both sides hash identical JSON.
		const merged: Record<string, unknown> = { [ BASE_KEY ]: width };

		for ( const [ key, value ] of Object.entries( responsiveWidths ) ) {
			if ( key !== BASE_KEY ) {
				merged[ key ] = value;
			}
		}

		return merged;
	}

	return { ...responsiveWidths };
}

/**
 * PHP `empty()` semantics for the scalar width, so a stored `0` / `'0'` /
 * `''` is treated as "no width" the same way the Blade partial's
 * `! empty( $attributes['width'] )` guard is.
 */
function isNonEmptyWidth( value: unknown ): boolean {
	if ( value === undefined || value === null || value === false ) {
		return false;
	}

	return value !== 0 && value !== '0' && value !== '';
}

// A single CSS length: an optional-sign number with a known absolute or
// relative unit. Percentages are handled separately (they carry a numeric
// `percent` for the block-gap calc); this list is the units a column width
// realistically stores. Anything else is rejected so it can never reach the
// emitted stylesheet.
const CSS_LENGTH = /^-?(?:\d+\.?\d*|\.\d+)(?:px|em|rem|ex|ch|cap|ic|lh|rlh|vw|vh|vi|vb|vmin|vmax|svw|svh|lvw|lvh|dvw|dvh|cm|mm|q|in|pt|pc|fr)$/i;

/**
 * Normalize a width value into a `flex-basis` expression plus its numeric
 * percent (or `null` for absolute units). Mirrors `$normalizeBasis` in the
 * Blade partial, with one addition: a non-numeric, non-percentage string is
 * accepted only when it is a bare CSS length, and otherwise returns `null`
 * (rejected) so the caller skips the rule. This keeps hostile values out of
 * the `<style>` block the Blade partial emits raw. The scope hash is taken
 * over the full width map upstream, so rejecting a value here does not change
 * the `ve-w-<hash>` token.
 */
function normalizeBasis( value: unknown ): NormalizedBasis | null {
	if ( isNumeric( value ) ) {
		const percent = typeof value === 'number' ? value : Number( String( value ).trim() );

		return { basis: formatPercent( percent ), percent };
	}

	const str = String( value );
	const match = /^(\d+(?:\.\d+)?)\s*%$/.exec( str );

	if ( match !== null ) {
		return { basis: str, percent: Number( match[ 1 ] ) };
	}

	if ( CSS_LENGTH.test( str.trim() ) ) {
		return { basis: str, percent: null };
	}

	return null;
}

/**
 * Subtract the parent block-gap from percentage bases with the same
 * `calc(X% - var(--wp--style--block-gap, 0.5em) * factor)` pattern WP uses,
 * so a themed `gap` on `.wp-block-columns` cannot push the row past 100% and
 * wrap. Mirrors `$basisExpr` in the Blade partial.
 */
function basisExpr( normalized: NormalizedBasis ): string {
	if ( normalized.percent === null ) {
		return normalized.basis;
	}

	const factor = ( 100 - normalized.percent ) / 100;
	const formattedFactor = trimFloat( factor );

	if ( formattedFactor === '' || formattedFactor === '0' ) {
		return normalized.basis;
	}

	return `calc(${ normalized.basis } - var(--wp--style--block-gap, 0.5em) * ${ formattedFactor })`;
}

/**
 * PHP `is_numeric()` for the values this module sees (numbers and numeric
 * strings). Trailing-whitespace numerics diverge from PHP but never reach a
 * width attribute.
 */
function isNumeric( value: unknown ): value is number | string {
	if ( typeof value === 'number' ) {
		return Number.isFinite( value );
	}

	if ( typeof value === 'string' ) {
		const trimmed = value.trim();

		return trimmed !== '' && ! Number.isNaN( Number( trimmed ) );
	}

	return false;
}

/**
 * Format a float the way PHP's `rtrim( rtrim( sprintf( '%F', $n ), '0' ), '.' )`
 * does — six fixed decimals with trailing zeros (and any bare dot) trimmed.
 */
function trimFloat( value: number ): string {
	return value.toFixed( 6 ).replace( /0+$/, '' ).replace( /\.$/, '' );
}

/**
 * Serialize the width map the way PHP's `json_encode()` does with default
 * flags: `/` and every non-ASCII code unit are `\u`/`\/` escaped, so the
 * bytes fed to the hash match the Blade side exactly. Keys are the fixed
 * breakpoint identifiers, so only values need escaping.
 */
function phpJsonEncode( map: Record<string, unknown> ): string {
	const parts: string[] = [];

	for ( const [ key, value ] of Object.entries( map ) ) {
		parts.push( `${ phpJsonString( key ) }:${ phpJsonValue( value ) }` );
	}

	return `{${ parts.join( ',' ) }}`;
}

function phpJsonValue( value: unknown ): string {
	if ( value === null || value === undefined ) {
		return 'null';
	}

	if ( typeof value === 'boolean' ) {
		return value ? 'true' : 'false';
	}

	if ( typeof value === 'number' ) {
		if ( ! Number.isFinite( value ) ) {
			throw new RangeError( 'phpJsonEncode: non-finite number' );
		}

		return String( value );
	}

	return phpJsonString( String( value ) );
}

function phpJsonString( value: string ): string {
	let out = '"';

	for ( let i = 0; i < value.length; i++ ) {
		const code = value.charCodeAt( i );
		const char = value[ i ];

		switch ( char ) {
			case '"':
				out += '\\"';
				break;
			case '\\':
				out += '\\\\';
				break;
			case '/':
				out += '\\/';
				break;
			case '\b':
				out += '\\b';
				break;
			case '\f':
				out += '\\f';
				break;
			case '\n':
				out += '\\n';
				break;
			case '\r':
				out += '\\r';
				break;
			case '\t':
				out += '\\t';
				break;
			default:
				if ( code < 0x20 || code >= 0x80 ) {
					out += `\\u${ code.toString( 16 ).padStart( 4, '0' ) }`;
				} else {
					out += char;
				}
		}
	}

	return `${ out }"`;
}
