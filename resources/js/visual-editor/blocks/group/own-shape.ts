/**
 * Whether an empty Group draws something of its own (#1039).
 *
 * Authors use empty Groups as decorative shapes, such as timeline dots
 * and rails. Those need to keep their styled size in the canvas, while
 * a bare empty Group has no size of its own and relies on its appender
 * to stay visible.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.20.0
 */

import type { ArtisanpackFlexAttribute } from '../_shared/flex-controls';

export interface GroupShapeAttributes {
    readonly backgroundColor?: string;
    readonly gradient?: string;
    readonly borderColor?: string;
    readonly style?: unknown;
    readonly artisanpackFlex?: ArtisanpackFlexAttribute | null;
}

interface GroupShapeStyle {
    readonly color?: { readonly background?: unknown; readonly gradient?: unknown };
    readonly background?: unknown;
    readonly border?: unknown;
    readonly dimensions?: unknown;
    readonly spacing?: { readonly padding?: unknown };
}

/**
 * Whether the attributes give a Group a visible shape or a size without
 * any inner blocks.
 *
 * @since 1.20.0
 *
 * @param attributes The Group's attributes.
 */
export function hasOwnShape(attributes: GroupShapeAttributes): boolean {
    const style = (attributes.style ?? {}) as GroupShapeStyle;

    return Boolean(
        attributes.backgroundColor ||
            attributes.gradient ||
            attributes.borderColor ||
            style.color?.background ||
            style.color?.gradient ||
            style.background ||
            style.border ||
            style.dimensions ||
            style.spacing?.padding ||
            attributes.artisanpackFlex?.item?.basis
    );
}
