/**
 * Icon block — shared rendering helpers.
 *
 * The same inline-style + transform computation is used by both `edit.tsx`
 * and `save.tsx`. Extracting it here keeps the two views in lockstep — a
 * common source of subtle "looks right in the editor, wrong on the front
 * end" bugs in icon-style blocks.
 */

import type { CSSProperties } from 'react';

import type {
    IconAttributes,
    IconRef,
    NormalizedIconAttributes,
    Rotation,
    SizeUnit,
} from './types';

const ALLOWED_TARGETS = new Set([ '_blank', '_self', '_parent', '_top' ]);

/**
 * Anchor-href safety check.
 *
 * Author-controlled URLs reach `<a href>` directly, so we accept only
 * known-safe schemes (and relative / anchor forms) — anything else
 * (`javascript:`, `data:`, `vbscript:`) is treated as an empty link
 * and the wrapper is suppressed. Mirrors the server-side allowlist
 * in `IconBlock::normalizeLink()`.
 */
function isSafeLinkUrl( raw: string ): boolean {
    const trimmed = raw.trim();
    if ( trimmed.length === 0 ) {
        return false;
    }

    const first = trimmed.charAt( 0 );
    if ( first === '/' || first === '#' || first === '?' ) {
        return true;
    }

    const colon = trimmed.indexOf( ':' );
    if ( colon === -1 ) {
        return true;
    }

    const scheme = trimmed.slice( 0, colon ).toLowerCase();
    return scheme === 'http' || scheme === 'https' || scheme === 'mailto' || scheme === 'tel';
}
const ALLOWED_SIZE_UNITS: ReadonlySet< SizeUnit > = new Set( [ 'px', 'em', 'rem' ] );
const ALLOWED_ROTATIONS: ReadonlySet< Rotation > = new Set( [ 0, 90, 180, 270 ] );

function asString( value: unknown ): string {
    return typeof value === 'string' ? value : '';
}

function asBoolean( value: unknown ): boolean {
    return value === true;
}

function asNumber( value: unknown, fallback: number ): number {
    return typeof value === 'number' && Number.isFinite( value ) ? value : fallback;
}

const MIN_SIZE = 1;
const MAX_SIZE = 1024;

function clampSize( raw: unknown ): number {
    const n = asNumber( raw, 32 );
    return Math.max( MIN_SIZE, Math.min( MAX_SIZE, n ) );
}

function asSizeUnit( value: unknown ): SizeUnit {
    return typeof value === 'string' && ALLOWED_SIZE_UNITS.has( value as SizeUnit )
        ? ( value as SizeUnit )
        : 'px';
}

function asRotation( value: unknown ): Rotation {
    return typeof value === 'number' && ALLOWED_ROTATIONS.has( value as Rotation )
        ? ( value as Rotation )
        : 0;
}

function asIconRef( value: unknown ): IconRef | null {
    if ( value === null || value === undefined || typeof value !== 'object' ) {
        return null;
    }
    const candidate = value as { set?: unknown; name?: unknown };
    const set = asString( candidate.set ).trim();
    const name = asString( candidate.name ).trim();
    return set.length > 0 && name.length > 0 ? { set, name } : null;
}

/**
 * Coerce the raw attribute payload into the always-valid shape used by
 * the render helpers. Blocks saved before this version's defaults shipped
 * can deserialize attributes as `null` — calling `.trim()` on those
 * crashes the edit component. Normalizing once at the top of the render
 * loop keeps the rest of the helpers blissfully unaware.
 */
export function normalizeAttributes( attributes: IconAttributes ): NormalizedIconAttributes {
    return {
        iconRef: asIconRef( attributes.iconRef ),
        customSvg: asString( attributes.customSvg ),
        // Mirror IconBlock::validateAttrs's 1..1024 clamp so a server
        // re-render produces the same size the editor previewed.
        size: clampSize( attributes.size ),
        sizeUnit: asSizeUnit( attributes.sizeUnit ),
        color: asString( attributes.color ),
        backgroundColor: asString( attributes.backgroundColor ),
        rotation: asRotation( attributes.rotation ),
        flipH: asBoolean( attributes.flipH ),
        flipV: asBoolean( attributes.flipV ),
        link: asString( attributes.link ),
        linkTarget: asString( attributes.linkTarget ),
        linkRel: asString( attributes.linkRel ),
        titleAttr: asString( attributes.titleAttr ),
        ariaLabel: asString( attributes.ariaLabel ),
        isDecorative: asBoolean( attributes.isDecorative ),
    };
}

/**
 * Compose the inline-sized inner-wrapper style.
 *
 * The OUTER `<div>` stays a plain block element so the editor's layout
 * wrapper can position it inside the content column. This inner style
 * is applied to the inline `<span>` that actually carries the icon —
 * letting the icon stay 32px wide without dragging the whole block
 * out of the document flow.
 */
export function computeIconStyle( attributes: NormalizedIconAttributes ): CSSProperties {
    const dimension = `${ attributes.size }${ attributes.sizeUnit }`;

    const style: CSSProperties = {
        width: dimension,
        height: dimension,
        display: 'inline-flex',
        alignItems: 'center',
        justifyContent: 'center',
        lineHeight: 0,
    };

    if ( attributes.color.length > 0 ) {
        style.color = attributes.color;
    }

    if ( attributes.backgroundColor.length > 0 ) {
        style.backgroundColor = attributes.backgroundColor;
    }

    return style;
}

/**
 * Build a CSS `transform` value from rotation + flip attributes.
 *
 * Returns `undefined` (not an empty string) when no transform applies, so
 * React omits the attribute rather than emitting `transform=""`.
 */
export function computeTransform( attributes: NormalizedIconAttributes ): string | undefined {
    const parts: string[] = [];

    if ( attributes.rotation ) {
        parts.push( `rotate(${ attributes.rotation }deg)` );
    }

    if ( attributes.flipH ) {
        parts.push( 'scaleX(-1)' );
    }

    if ( attributes.flipV ) {
        parts.push( 'scaleY(-1)' );
    }

    return parts.length > 0 ? parts.join( ' ' ) : undefined;
}

/**
 * Whether the block should wrap its icon in an `<a>` on the front end.
 *
 * We require a non-empty link AND an icon to render (either a registered
 * `iconRef` or a `customSvg`) — wrapping a placeholder in a link only
 * confuses screen readers and search crawlers.
 */
export function shouldRenderLink( attributes: NormalizedIconAttributes ): boolean {
    return (
        isSafeLinkUrl( attributes.link ) &&
        ( attributes.iconRef !== null || attributes.customSvg.trim().length > 0 )
    );
}

/**
 * Whether the current decorative + link combination is an a11y conflict.
 *
 * Decorative icons (`aria-hidden="true"`) cannot be the only content
 * inside a link — screen readers would announce an unlabeled link. The
 * edit-side surfaces this as an inline warning; the save side still
 * emits the markup the author chose so the warning isn't masked.
 */
export function hasDecorativeLinkConflict( attributes: NormalizedIconAttributes ): boolean {
    return (
        attributes.isDecorative &&
        shouldRenderLink( attributes ) &&
        attributes.ariaLabel.trim().length === 0
    );
}

/**
 * Sanitize the link target to a known-safe value.
 *
 * Authors can type anything, but anything other than the four standard
 * targets is meaningless and a footgun (custom targets are sometimes used
 * to coordinate with `window.open` shims). We pass through allowed values
 * and drop everything else.
 */
export function normalizeLinkTarget( target: string ): string {
    return ALLOWED_TARGETS.has( target ) ? target : '';
}

/**
 * Compose the final `rel` attribute for a linked icon.
 *
 * `target="_blank"` MUST be paired with `noopener noreferrer` to avoid
 * reverse-tabnabbing — we inject those tokens regardless of what the
 * author typed, then dedupe and re-serialize.
 */
export function composeRel( linkTarget: string, rel: string ): string {
    const tokens = new Set(
        rel
            .split( /\s+/ )
            .map( ( token ) => token.trim() )
            .filter( ( token ) => token.length > 0 )
    );

    if ( '_blank' === normalizeLinkTarget( linkTarget ) ) {
        tokens.add( 'noopener' );
        tokens.add( 'noreferrer' );
    }

    return Array.from( tokens ).join( ' ' );
}
