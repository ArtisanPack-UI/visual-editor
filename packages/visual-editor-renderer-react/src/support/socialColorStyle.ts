/**
 * Builds the CSS-style object applied to a social chip (share / author
 * flavours) so saved icon-color attributes flow through identically to
 * the editor preview and the Blade / Vue renderers.
 *
 * Hover colors are emitted as CSS custom properties (`--ap-social-…`)
 * so host CSS can hook them via `.chip:hover { background: var(...); }`
 * without the renderer injecting `<style>` blocks at runtime.
 *
 * Mirrors `resources/js/visual-editor/blocks/_shared/social-color-style.ts`
 * in the editor source tree.
 *
 * @since 1.0.0
 */

import { safeCssValue } from './cssValue';

export interface SocialColorAttributes {
    readonly iconColor?: string;
    readonly iconHoverColor?: string;
    readonly iconBackgroundColor?: string;
    readonly iconHoverBackgroundColor?: string;
}

/**
 * Custom property each color attribute is written to, in output order.
 */
const SOCIAL_COLOR_PROPERTIES: ReadonlyArray<
    readonly [keyof SocialColorAttributes, string]
> = [
    ['iconColor', '--ap-social-color'],
    ['iconBackgroundColor', '--ap-social-bg'],
    ['iconHoverColor', '--ap-social-hover-color'],
    ['iconHoverBackgroundColor', '--ap-social-hover-bg'],
];

export function buildSocialColorStyle(
    attributes: SocialColorAttributes
): Record<string, string> {
    const style: Record<string, string> = {};

    // Emit ONLY CSS custom properties so the `.chip:hover` rule can
    // swap them on hover — inline `color: X` would have higher
    // specificity than `:hover { color: var(...) }` and break the
    // swap silently. The base color reads from `var(--ap-social-color)`
    // in social-icons.css.
    //
    // A value that fails `safeCssValue()` is dropped, as the Blade
    // partials drop it, so a `;` can't add declarations to the chip
    // (#1079).
    for (const [attribute, property] of SOCIAL_COLOR_PROPERTIES) {
        const value = attributes[attribute];

        if (typeof value !== 'string') {
            continue;
        }

        const safe = safeCssValue(value);

        if (safe !== null) {
            style[property] = safe;
        }
    }

    return style;
}
