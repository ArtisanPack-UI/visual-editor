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

const LEGACY_WIDTHS = ['25%', '50%', '75%', '100%'];

/**
 * Inline style for a width that has no legacy class.
 *
 * block-library's `[class*=wp-block-button__width]` rule sizes the button
 * from `--wp--block-button--width`, so an arbitrary percentage (e.g. 60%)
 * sets that variable. A non-percentage width (e.g. 240px) is set directly;
 * `.has-custom-width .wp-block-button__link` then fills it (#921).
 */
export function getWidthStyle(width: unknown): Record<string, string> | undefined {
    if ('string' !== typeof width || '' === width || LEGACY_WIDTHS.includes(width)) {
        return undefined;
    }

    if (isPercentageWidth(width)) {
        return { '--wp--block-button--width': String(parseFloat(width)) };
    }

    return { width };
}
