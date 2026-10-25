/**
 * Social Icons — shared class and style helpers (#1023).
 *
 * Every icon's colours, border, padding and hover styles are set once on the
 * parent: the `<ul>` carries them as `--ap-social-links-*` custom
 * properties plus a `has-icon-*` class per setting, and `social-links.css`
 * applies them to each `.wp-social-link` (hover included, which an inline
 * style can't express). The Blade, React and Vue renderers emit the same
 * classes and properties, in the same order.
 */

import clsx from 'clsx';

export const ICON_SIZES: ReadonlyArray<string> = [
    'has-small-icon-size',
    'has-normal-icon-size',
    'has-large-icon-size',
    'has-huge-icon-size',
];

export const ICON_BORDER_STYLES: ReadonlyArray<string> = ['none', 'solid', 'dashed', 'dotted', 'double'];

export interface SocialLinksStyleAttributes {
    readonly size?: string;
    readonly showLabels?: boolean;
    readonly iconColorValue?: string;
    readonly iconBackgroundColorValue?: string;
    readonly iconBorderColorValue?: string;
    readonly iconBorderWidth?: string;
    readonly iconBorderStyle?: string;
    readonly iconBorderRadius?: string;
    readonly iconPadding?: string;
    readonly iconHoverColorValue?: string;
    readonly iconHoverBackgroundColorValue?: string;
    readonly iconHoverBorderColorValue?: string;
}

/**
 * Overrides for the colours `withColors` resolves from a named palette
 * colour, which can differ from the stored `*Value` mid-edit.
 */
export interface ResolvedIconColors {
    readonly iconColor?: string;
    readonly iconBackgroundColor?: string;
}

function filled(value: string | undefined): string | undefined {
    return typeof value === 'string' && value.trim() !== '' ? value : undefined;
}

/**
 * The `--ap-social-links-*` custom properties for the set icon styles, in
 * the order every renderer emits them.
 *
 * @since 1.18.0
 */
export function socialLinksStyle(
    attributes: SocialLinksStyleAttributes,
    resolved: ResolvedIconColors = {}
): Record<string, string> {
    const borderStyle = filled(attributes.iconBorderStyle);
    const entries: Array<[string, string | undefined]> = [
        ['--ap-social-links-color', filled(resolved.iconColor) ?? filled(attributes.iconColorValue)],
        ['--ap-social-links-bg', filled(resolved.iconBackgroundColor) ?? filled(attributes.iconBackgroundColorValue)],
        ['--ap-social-links-border-color', filled(attributes.iconBorderColorValue)],
        ['--ap-social-links-border-width', filled(attributes.iconBorderWidth)],
        ['--ap-social-links-border-style', borderStyle && ICON_BORDER_STYLES.includes(borderStyle) ? borderStyle : undefined],
        ['--ap-social-links-radius', filled(attributes.iconBorderRadius)],
        ['--ap-social-links-padding', filled(attributes.iconPadding)],
        ['--ap-social-links-hover-color', filled(attributes.iconHoverColorValue)],
        ['--ap-social-links-hover-bg', filled(attributes.iconHoverBackgroundColorValue)],
        ['--ap-social-links-hover-border-color', filled(attributes.iconHoverBorderColorValue)],
    ];

    return Object.fromEntries(entries.filter((entry): entry is [string, string] => entry[1] !== undefined));
}

/**
 * The wrapper classes shared by `edit` and `save`. `wp-block-social-links`
 * is added alongside the block's own class so the bundled WordPress
 * block-library CSS (sizes, styles, service colours) applies unchanged.
 *
 * @since 1.18.0
 */
export function socialLinksClassName(
    attributes: SocialLinksStyleAttributes,
    resolved: ResolvedIconColors = {}
): string {
    const style = socialLinksStyle(attributes, resolved);
    const has = (property: string): boolean => property in style;
    const { size } = attributes;

    return clsx('wp-block-social-links', size && ICON_SIZES.includes(size) ? size : undefined, {
        'has-visible-labels': attributes.showLabels,
        'has-icon-color': has('--ap-social-links-color'),
        'has-icon-background-color': has('--ap-social-links-bg'),
        'has-icon-border':
            has('--ap-social-links-border-color') ||
            has('--ap-social-links-border-width') ||
            has('--ap-social-links-border-style'),
        'has-icon-border-radius': has('--ap-social-links-radius'),
        'has-icon-padding': has('--ap-social-links-padding'),
        'has-icon-hover-color': has('--ap-social-links-hover-color'),
        'has-icon-hover-background-color': has('--ap-social-links-hover-bg'),
        'has-icon-hover-border-color': has('--ap-social-links-hover-border-color'),
    });
}
