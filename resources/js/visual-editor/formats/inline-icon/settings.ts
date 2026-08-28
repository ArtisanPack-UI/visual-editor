/**
 * Inline-icon RichText format — shared constants and pure builders (#717).
 *
 * These helpers are deliberately framework-free so the format's insert /
 * replace / save contract can be unit-tested without mounting the React
 * toolbar. The `edit` component and `registerInlineIconFormat()` glue
 * live alongside in `edit.tsx` / `register.ts`.
 */

import type { IconRef } from '../../blocks/icon/types';

/** Registered format-type name. */
export const FORMAT_NAME = 'artisanpack/inline-icon';

/** Shared class on every inline icon, editor and frontend alike. */
export const INLINE_ICON_CLASS = 'ap-inline-icon';

/**
 * Fallback body for a registered-set icon whose preview SVG couldn't be
 * fetched (offline / transient error). A `contentEditable: false` object
 * with an *empty* body is dropped by `@wordpress/rich-text` on save, so a
 * non-empty placeholder keeps the reference span alive — the server
 * hydrator replaces it with the resolved SVG at render regardless.
 */
export const SET_ICON_PLACEHOLDER_SVG = '<svg aria-hidden="true" viewBox="0 0 24 24"></svg>';

/**
 * Format-type attribute schema: maps each rich-text attribute key to the
 * HTML attribute it serializes to. `data-icon-set` / `data-icon-name`
 * are the render-time reference the server hydrator resolves; the a11y
 * attributes flip the icon between decorative and meaningful.
 */
export const INLINE_ICON_ATTRIBUTES = {
    iconSet: 'data-icon-set',
    iconName: 'data-icon-name',
    style: 'style',
    role: 'role',
    label: 'aria-label',
    hidden: 'aria-hidden',
} as const;

/**
 * Per-icon overrides surfaced in the inline popover. Every field is
 * inherit-first: omit it and the icon takes the surrounding text's
 * colour and scales with its `font-size` (the glyph is sized at `1em`).
 */
export interface InlineIconOptions {
    /**
     * Accessible label. When set, the icon is exposed to assistive tech
     * (`role="img"` + `aria-label`); when omitted it stays decorative
     * (`aria-hidden="true"`), matching the Icon block's default.
     */
    readonly label?: string;
    /** Size override in `em` (scales the `1em` glyph). Omit to inherit. */
    readonly sizeEm?: number;
    /** Colour override. Omit to inherit the surrounding text colour. */
    readonly color?: string;
}

/**
 * A rich-text object replacement for an inline icon. `attributes` are
 * keyed by the schema keys in {@link INLINE_ICON_ATTRIBUTES}; `innerHTML`
 * is the SVG shown in the editor (and, for custom SVG, persisted inline).
 */
export interface InlineIconObject {
    readonly type: typeof FORMAT_NAME;
    readonly attributes: Record< string, string >;
    readonly innerHTML: string;
}

/**
 * Colour-shaped value whitelist for the per-icon `color` override. Mirrors
 * the shape of `canvas-color-tokens.ts`'s `ALLOWED_VALUE` (hex, `rgb()` /
 * `hsl()`, `var(--…)`, named colours) but excludes `/`, `;`, and `:` so a
 * free-text ColorPalette custom value can't smuggle extra declarations into
 * the serialized `style` — e.g. `red;position:fixed;inset:0`, which is
 * attribute-escaped (no markup breakout) but would otherwise persist the
 * extra rules. The server's `InlineIconContentHydrator` re-sanitizes `style`
 * at render as a second line of defence.
 */
const COLOR_VALUE_PATTERN = /^[#\w(),.%\s-]+$/;

/**
 * Build the inline `style` string from the size / colour overrides.
 * Returns undefined when nothing is overridden so the icon inherits.
 */
export function buildInlineIconStyle( options: InlineIconOptions ): string | undefined {
    const declarations: string[] = [];

    if ( 'number' === typeof options.sizeEm && options.sizeEm > 0 ) {
        declarations.push( `font-size:${ options.sizeEm }em` );
    }

    const color = options.color?.trim();
    if ( color && COLOR_VALUE_PATTERN.test( color ) ) {
        declarations.push( `color:${ color }` );
    }

    return declarations.length > 0 ? declarations.join( ';' ) : undefined;
}

/**
 * Merge the reference attributes with the size / colour / a11y overrides
 * into the schema-keyed attribute bag the rich-text object carries.
 */
export function buildInlineIconAttributes(
    base: Record< string, string >,
    options: InlineIconOptions,
): Record< string, string > {
    const attributes: Record< string, string > = { ...base };

    const style = buildInlineIconStyle( options );
    if ( style ) {
        attributes.style = style;
    }

    const label = options.label?.trim();
    if ( label ) {
        attributes.role  = 'img';
        attributes.label = label;
    } else {
        attributes.hidden = 'true';
    }

    return attributes;
}

/**
 * Inline style forced onto every inline-icon `<svg>` so it renders
 * correctly with zero reliance on an external stylesheet. This matters
 * because the editor canvas is a same-origin iframe that a bundle-level
 * `import './inline-icon.css'` never reaches — without inline styles the
 * glyph renders at its intrinsic (huge) size. `display:inline-block`
 * overrides CSS resets that set `svg { display: block }` (e.g. Tailwind's
 * Preflight), which would otherwise push the icon onto its own line;
 * sizing at `1em` lets the icon scale with the span's `font-size`;
 * `fill:currentColor` lets it inherit the surrounding text colour; and
 * `vertical-align` seats it on the text baseline. Kept in sync with the
 * PHP hydrator's normalizer.
 */
export const INLINE_ICON_SVG_STYLE =
    'display:inline-block;width:1em;height:1em;fill:currentColor;vertical-align:-0.125em';

/**
 * Force the inherit-first sizing / colour styles onto an SVG's root
 * element: strip any intrinsic width/height attributes (so the inline
 * `1em` wins) and merge {@link INLINE_ICON_SVG_STYLE} into its `style`.
 * A non-SVG string is returned untouched.
 */
export function normalizeInlineIconSvg( svg: string ): string {
    const trimmed = svg.trim();
    if ( ! /^<svg\b/i.test( trimmed ) ) {
        return trimmed;
    }

    // Matches a `style="…"` or `style='…'` attribute, capturing the
    // declarations under group 2 (double) or 3 (single).
    const styleAttr = /\sstyle\s*=\s*(?:"([^"]*)"|'([^']*)')/i;

    return trimmed.replace(
        /<svg\b([^>]*?)(\/?)>/i,
        ( _match, rawAttrs: string, selfClose: string ): string => {
            const withoutDimensions = rawAttrs
                .replace( /\s(?:width|height)\s*=\s*"[^"]*"/gi, '' )
                .replace( /\s(?:width|height)\s*=\s*'[^']*'/gi, '' );

            const withStyle = styleAttr.test( withoutDimensions )
                ? withoutDimensions.replace(
                    styleAttr,
                    ( _m, dq: string | undefined, sq: string | undefined ): string => {
                        const existing = ( dq ?? sq ?? '' ).trim().replace( /;+$/, '' );
                        // Enforced declarations go LAST so the icon's own
                        // `1em` sizing / `currentColor` win over any source
                        // width/height/fill in the existing style.
                        const merged = '' === existing
                            ? INLINE_ICON_SVG_STYLE
                            : `${ existing };${ INLINE_ICON_SVG_STYLE }`;
                        return ` style="${ merged }"`;
                    },
                )
                : `${ withoutDimensions } style="${ INLINE_ICON_SVG_STYLE }"`;

            return `<svg${ withStyle }${ selfClose }>`;
        },
    );
}

/**
 * Parse the size / colour overrides back out of a serialized `style`
 * string, so the inline popover can pre-fill its controls from an
 * existing icon. Unrecognized declarations are ignored.
 */
export function parseInlineIconStyle( style: string | undefined ): InlineIconOptions {
    const options: { sizeEm?: number; color?: string } = {};

    if ( ! style ) {
        return options;
    }

    const size = style.match( /font-size\s*:\s*([\d.]+)em/i );
    if ( size ) {
        const value = Number.parseFloat( size[ 1 ] );
        if ( Number.isFinite( value ) && value > 0 ) {
            options.sizeEm = value;
        }
    }

    const color = style.match( /(?:^|;)\s*color\s*:\s*([^;]+)/i );
    if ( color ) {
        const value = color[ 1 ].trim();
        if ( value ) {
            options.color = value;
        }
    }

    return options;
}

/**
 * Build the object for a registered-set icon. The `iconSet` / `iconName`
 * reference is authoritative — the server hydrator re-resolves it at
 * render — while `previewSvg` is what the author sees in the canvas.
 */
export function buildSetIconObject(
    ref: IconRef,
    previewSvg: string,
    options: InlineIconOptions = {},
): InlineIconObject {
    return {
        type: FORMAT_NAME,
        attributes: buildInlineIconAttributes(
            { iconSet: ref.set, iconName: ref.name },
            options,
        ),
        // Never persist an empty body — see SET_ICON_PLACEHOLDER_SVG.
        innerHTML: normalizeInlineIconSvg(
            '' === previewSvg.trim() ? SET_ICON_PLACEHOLDER_SVG : previewSvg,
        ),
    };
}

/**
 * Build the object for a custom SVG icon. It carries no set/name
 * reference — the (already server-sanitized) SVG is embedded directly
 * and passes through the render-time hydrator untouched.
 */
export function buildCustomSvgObject(
    svg: string,
    options: InlineIconOptions = {},
): InlineIconObject {
    return {
        type: FORMAT_NAME,
        attributes: buildInlineIconAttributes( {}, options ),
        innerHTML: normalizeInlineIconSvg( svg ),
    };
}
