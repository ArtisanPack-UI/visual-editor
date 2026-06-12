/**
 * Builds the inline-style record applied to a social chip (share /
 * author flavours) so saved icon-color attributes flow through
 * identically to the editor preview and the Blade / React renderers.
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

export interface SocialColorAttributes {
    readonly iconColor?: string;
    readonly iconHoverColor?: string;
    readonly iconBackgroundColor?: string;
    readonly iconHoverBackgroundColor?: string;
}

export function buildSocialColorStyle(
    attributes: SocialColorAttributes
): Record<string, string> {
    const style: Record<string, string> = {};

    // Emit ONLY CSS custom properties so the `.chip:hover` rule can
    // swap them on hover — inline `color: X` would have higher
    // specificity than `:hover { color: var(...) }` and break the
    // swap silently. The base color reads from `var(--ap-social-color)`
    // in social-icons.css.
    if (typeof attributes.iconColor === 'string' && attributes.iconColor !== '') {
        style['--ap-social-color'] = attributes.iconColor;
    }

    if (
        typeof attributes.iconBackgroundColor === 'string' &&
        attributes.iconBackgroundColor !== ''
    ) {
        style['--ap-social-bg'] = attributes.iconBackgroundColor;
    }

    if (
        typeof attributes.iconHoverColor === 'string' &&
        attributes.iconHoverColor !== ''
    ) {
        style['--ap-social-hover-color'] = attributes.iconHoverColor;
    }

    if (
        typeof attributes.iconHoverBackgroundColor === 'string' &&
        attributes.iconHoverBackgroundColor !== ''
    ) {
        style['--ap-social-hover-bg'] = attributes.iconHoverBackgroundColor;
    }

    return style;
}
