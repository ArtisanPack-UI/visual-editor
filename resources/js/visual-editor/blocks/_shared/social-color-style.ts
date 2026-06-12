/**
 * Helpers that build the inline-style object applied to each social
 * chip (share + author flavours) so the icon/background colors picked
 * in the inspector flow through the editor preview and the renderers
 * identically.
 *
 * Hover colors are persisted as CSS custom properties (`--ap-social-…`)
 * rather than directly assigned, so host CSS can hook them via
 * `.chip:hover { background: var(--ap-social-hover-bg, …); }` without
 * the renderer having to inject `<style>` blocks at runtime.
 *
 * @since 1.0.0
 */

export interface SocialColorAttributes {
    readonly iconColor?: string;
    readonly iconHoverColor?: string;
    readonly iconBackgroundColor?: string;
    readonly iconHoverBackgroundColor?: string;
}

/**
 * Returns a CSS-style record (compatible with React's `CSSProperties`
 * and Vue's style binding) that applies the static colors and the
 * hover CSS custom properties to a chip. Returns an empty object when
 * none of the inputs are set so callers can spread it conditionally.
 *
 * @since 1.0.0
 */
export function buildSocialColorStyle(
    attributes: SocialColorAttributes
): Record<string, string> {
    // Emit ONLY CSS custom properties — the base color / background
    // come from `var(--ap-social-color)` / `var(--ap-social-bg)` rules
    // in the social-icons stylesheet. Inline `color: X` would have
    // higher specificity than the `.chip:hover { color: var(...) }`
    // rule and silently break hover swaps; the variable indirection
    // keeps base + hover on equal footing.
    const style: Record<string, string> = {};

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

/**
 * Same shape as {@see buildSocialColorStyle} but encodes the inline
 * style as a `style="…"` attribute string for the Blade renderer
 * (which builds HTML by string concatenation). Returns an empty string
 * when no styles apply, so the caller can concat unconditionally.
 *
 * Kept here for parity with the React/Vue renderers — see the PHP
 * equivalent in the Blade partials, which mirrors the same precedence.
 *
 * @since 1.0.0
 */
export function buildSocialColorStyleString(
    attributes: SocialColorAttributes
): string {
    const style = buildSocialColorStyle(attributes);
    const entries = Object.entries(style);
    if (entries.length === 0) {
        return '';
    }

    return entries
        .map(([key, value]) => {
            const cssKey = key.startsWith('--')
                ? key
                : key.replace(/[A-Z]/g, (m) => `-${m.toLowerCase()}`);
            return `${cssKey}:${value}`;
        })
        .join(';');
}
