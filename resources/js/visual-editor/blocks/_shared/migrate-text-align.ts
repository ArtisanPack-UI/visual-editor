// Vendored from @wordpress/block-library/src/utils/migrate-text-align.js (v9.43.0).
//
// Migrates a top-level `textAlign` attribute into the canonical
// `style.typography.textAlign` block-support shape. Used by the heading,
// quote, code, preformatted, pullquote, and verse deprecation chains —
// hence the promotion out of any single block directory.

interface AttributesWithStyle {
    textAlign?: string;
    style?: {
        typography?: {
            textAlign?: string;
        } & Record<string, unknown>;
    } & Record<string, unknown>;
    [key: string]: unknown;
}

export function migrateTextAlignAttributeToBlockSupport<
    T extends AttributesWithStyle,
>(attributes: T): T {
    const { textAlign, ...restAttributes } = attributes;
    if (!textAlign) {
        return attributes;
    }
    return {
        ...(restAttributes as T),
        style: {
            ...attributes.style,
            typography: {
                ...attributes.style?.typography,
                textAlign,
            },
        },
    };
}

export default migrateTextAlignAttributeToBlockSupport;
