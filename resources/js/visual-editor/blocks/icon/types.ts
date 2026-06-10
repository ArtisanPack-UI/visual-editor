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

export type SizeUnit = 'px' | 'em' | 'rem';
export type Rotation = 0 | 90 | 180 | 270;

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
    readonly color?: string | null;
    readonly backgroundColor?: string | null;
    readonly rotation: Rotation | null | undefined;
    readonly flipH: boolean | null | undefined;
    readonly flipV: boolean | null | undefined;
    readonly link: string | null | undefined;
    readonly linkTarget: string | null | undefined;
    readonly linkRel: string | null | undefined;
    readonly titleAttr: string | null | undefined;
    readonly ariaLabel: string | null | undefined;
    readonly isDecorative: boolean | null | undefined;
}

/**
 * The "always present, always the right type" form used internally
 * by the render helpers. Authors never construct this directly —
 * {@link normalizeAttributes} produces it from the raw `IconAttributes`.
 */
export interface NormalizedIconAttributes {
    readonly iconRef: IconRef | null;
    readonly customSvg: string;
    readonly size: number;
    readonly sizeUnit: SizeUnit;
    readonly color: string;
    readonly backgroundColor: string;
    readonly rotation: Rotation;
    readonly flipH: boolean;
    readonly flipV: boolean;
    readonly link: string;
    readonly linkTarget: string;
    readonly linkRel: string;
    readonly titleAttr: string;
    readonly ariaLabel: string;
    readonly isDecorative: boolean;
}
