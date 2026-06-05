// Vendored from @wordpress/block-library/src/utils/migrate-font-family.js (v9.43.0).
//
// Migrates the legacy `style.typography.fontFamily` shape
// ("var:preset|font-family|helvetica-arial") to the top-level
// `fontFamily` attribute. The upstream helper reaches into
// `@wordpress/block-editor` privateApis to call `cleanEmptyObject`; we
// inline a self-contained equivalent so we don't depend on that
// unlock-gated API.

interface AttributesWithStyle {
    style?: {
        typography?: {
            fontFamily?: string;
        } & Record<string, unknown>;
    } & Record<string, unknown>;
    fontFamily?: string;
    [key: string]: unknown;
}

function isEmptyObject(value: unknown): boolean {
    return (
        typeof value === 'object' &&
        value !== null &&
        Object.keys(value as Record<string, unknown>).length === 0
    );
}

function cleanEmptyObject<T extends Record<string, unknown>>(
    object: T | undefined
): T | undefined {
    if (object === undefined || object === null) {
        return undefined;
    }
    const cleaned: Record<string, unknown> = {};
    for (const [key, value] of Object.entries(object)) {
        if (value && typeof value === 'object' && !Array.isArray(value)) {
            const recursed = cleanEmptyObject(value as Record<string, unknown>);
            if (recursed !== undefined) {
                cleaned[key] = recursed;
            }
        } else if (value !== undefined) {
            cleaned[key] = value;
        }
    }
    return isEmptyObject(cleaned) ? undefined : (cleaned as T);
}

export function migrateFontFamily<T extends AttributesWithStyle>(attributes: T): T {
    if (!attributes?.style?.typography?.fontFamily) {
        return attributes;
    }
    const { fontFamily, ...typography } = attributes.style.typography;

    return {
        ...attributes,
        style: cleanEmptyObject({
            ...attributes.style,
            typography,
        }),
        fontFamily: fontFamily.split('|').pop(),
    } as T;
}

export default migrateFontFamily;
