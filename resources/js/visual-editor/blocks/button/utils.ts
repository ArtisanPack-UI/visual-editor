/**
 * Button — width utilities.
 *
 * Ported from `@wordpress/block-library/src/button/utils.js` (v9.43.0).
 */

export function isPercentageWidth(width: unknown): boolean {
    return typeof width === 'string' && width.endsWith('%');
}

export function getWidthClasses(width: unknown): Record<string, boolean> {
    if (!width) {
        return {};
    }

    if (isPercentageWidth(width)) {
        const legacyWidthClasses: Record<string, string> = {
            '25%': 'wp-block-button__width-25',
            '50%': 'wp-block-button__width-50',
            '75%': 'wp-block-button__width-75',
            '100%': 'wp-block-button__width-100',
        };
        const widthKey = width as string;
        return {
            'has-custom-width': true,
            'wp-block-button__width': true,
            ...(legacyWidthClasses[widthKey] && {
                [legacyWidthClasses[widthKey]]: true,
            }),
        };
    }

    return {
        'has-custom-width': true,
    };
}
