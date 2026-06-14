/**
 * Icon block — shared types.
 *
 * Phase 1 (#552). The picker (#555), custom-SVG paste (#556),
 * and admin upload (#557) phases all consume `IconAttributes` —
 * keeping the shape in one place avoids drift between edit/save
 * and the future controls modules.
 */

/**
 * Reference to an icon registered via the `ap.icons.register-icon-sets`
 * filter. `null` means the block has no selected icon and should
 * render a placeholder (or the `customSvg` if one is set).
 */
export interface IconRef {
    /** Set prefix as registered (e.g. `fas`, `far`, `fab`). */
    readonly set: string;
    /** Icon slug within the set (e.g. `github`, `arrow-left`). */
    readonly name: string;
}

export type SizeUnit = 'px' | 'em' | 'rem' | '%' | 'vw' | 'vh';
export type Rotation = 0 | 90 | 180 | 270;

/**
 * Subset of WordPress's `style` attribute that the icon block reads.
 *
 * `supports.color`, `supports.spacing`, and `supports.__experimentalBorder`
 * all write into the same `style` envelope. We declare just the slots we
 * consume — the wider WP shape is wider but irrelevant here.
 */
export interface WpStyleAttribute {
    readonly color?: {
        readonly text?: string | null;
        readonly background?: string | null;
    } | null;
    readonly border?: {
        readonly color?: string | null;
        readonly radius?: string | { readonly topLeft?: string | null; readonly topRight?: string | null; readonly bottomLeft?: string | null; readonly bottomRight?: string | null } | null;
        readonly style?: string | null;
        readonly width?: string | null;
        readonly top?: { readonly color?: string | null; readonly style?: string | null; readonly width?: string | null } | null;
        readonly right?: { readonly color?: string | null; readonly style?: string | null; readonly width?: string | null } | null;
        readonly bottom?: { readonly color?: string | null; readonly style?: string | null; readonly width?: string | null } | null;
        readonly left?: { readonly color?: string | null; readonly style?: string | null; readonly width?: string | null } | null;
    } | null;
    readonly spacing?: {
        readonly padding?: string | { readonly top?: string | null; readonly right?: string | null; readonly bottom?: string | null; readonly left?: string | null } | null;
        readonly margin?: string | { readonly top?: string | null; readonly right?: string | null; readonly bottom?: string | null; readonly left?: string | null } | null;
    } | null;
}

/**
 * Every string attribute is typed `string | null | undefined` because
 * blocks saved before this version's defaults shipped can deserialize
 * attributes as `null` — and React/RichText pipelines occasionally hand
 * back `undefined` mid-edit. The render helpers normalize each field
 * via {@link normalizeAttributes} before touching them.
 */
export interface IconAttributes {
    readonly iconRef: IconRef | null | undefined;
    readonly customSvg: string | null | undefined;
    readonly size: number | null | undefined;
    readonly sizeUnit: SizeUnit | null | undefined;
    readonly width?: number | null;
    readonly widthUnit?: SizeUnit | null;
    readonly height?: number | null;
    readonly heightUnit?: SizeUnit | null;
    readonly color?: string | null;
    readonly backgroundColor?: string | null;
    readonly iconColor?: string | null;
    readonly rotation: Rotation | null | undefined;
    readonly flipH: boolean | null | undefined;
    readonly flipV: boolean | null | undefined;
    readonly link: string | null | undefined;
    readonly linkTarget: string | null | undefined;
    readonly linkRel: string | null | undefined;
    readonly titleAttr: string | null | undefined;
    readonly ariaLabel: string | null | undefined;
    readonly isDecorative: boolean | null | undefined;
    readonly style?: WpStyleAttribute | null;
}

/**
 * The "always present, always the right type" form used internally
 * by the render helpers. Authors never construct this directly —
 * {@link normalizeAttributes} produces it from the raw `IconAttributes`.
 *
 * `width`/`height` are the resolved render-time dimensions: when the
 * author left them unset, {@link normalizeAttributes} fills them from
 * `size`/`sizeUnit` so downstream helpers only have to read one pair.
 * `widthExplicit` / `heightExplicit` flag whether the author actually
 * overrode `size` — useful for UI hints, never for rendering.
 */
export interface NormalizedIconAttributes {
    readonly iconRef: IconRef | null;
    readonly customSvg: string;
    readonly size: number;
    readonly sizeUnit: SizeUnit;
    readonly width: number;
    readonly widthUnit: SizeUnit;
    readonly widthExplicit: boolean;
    readonly height: number;
    readonly heightUnit: SizeUnit;
    readonly heightExplicit: boolean;
    readonly color: string;
    readonly backgroundColor: string;
    readonly iconColor: string;
    readonly rotation: Rotation;
    readonly flipH: boolean;
    readonly flipV: boolean;
    readonly link: string;
    readonly linkTarget: string;
    readonly linkRel: string;
    readonly titleAttr: string;
    readonly ariaLabel: string;
    readonly isDecorative: boolean;
    readonly style: WpStyleAttribute;
}
