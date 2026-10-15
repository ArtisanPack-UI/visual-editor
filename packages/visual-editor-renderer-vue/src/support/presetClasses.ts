/**
 * Preset colour / typography classes — Vue renderer (#888).
 *
 * Mirrors the palette-slug halves of `BlockSupports::applyColor()` and
 * `applyTypography()` in the Blade renderer: a `backgroundColor`,
 * `gradient`, `textColor`, `fontSize` or `fontFamily` slug becomes its
 * `has-{slug}-*` class plus the marker class, in Blade's order.
 *
 * The mirror lives at the matching path in the React renderer.
 *
 * @package @artisanpack-ui/visual-editor-renderer-vue
 * @since 1.15.0
 */

import { phpTrim } from './attributes';
import { presetPickClass } from './layoutBaselineCss';

/** The preset classes for a block's colour and typography slugs. */
export function presetClasses(attributes: Record<string, unknown>): string[] {
    const classes: string[] = [];
    const backgroundSlug = stringAttr(attributes.backgroundColor);
    const gradientSlug = stringAttr(attributes.gradient);
    const textSlug = stringAttr(attributes.textColor);
    const fontSizeSlug = stringAttr(attributes.fontSize);
    const fontFamilySlug = stringAttr(attributes.fontFamily);

    if (backgroundSlug !== '') {
        classes.push(`has-${slugify(backgroundSlug)}-background-color`, 'has-background');
    }

    if (gradientSlug !== '') {
        classes.push(`has-${slugify(gradientSlug)}-gradient-background`, 'has-background');
    }

    if (textSlug !== '') {
        classes.push(`has-${slugify(textSlug)}-color`, 'has-text-color');

        const pickClass = presetPickClass(slugify(textSlug));

        if (pickClass !== null) {
            classes.push(pickClass);
        }
    }

    if (fontSizeSlug !== '') {
        classes.push(`has-${slugify(fontSizeSlug)}-font-size`, 'has-defined-font-size');
    }

    if (fontFamilySlug !== '') {
        classes.push(`has-${slugify(fontFamilySlug)}-font-family`);
    }

    // Deduped like Blade's `array_unique()`: a `text` slug's class is
    // `has-text-color`, the same token as the marker.
    return [...new Set(classes)];
}

/** Mirrors `BlockSupports::stringAttr()`. */
function stringAttr(value: unknown): string {
    if (typeof value === 'string') {
        return phpTrim(value);
    }

    return typeof value === 'number' && Number.isFinite(value) ? String(value) : '';
}

/**
 * Mirrors `BlockSupports::slugify()`. Lowercases ASCII only, like PHP's
 * `strtolower()`, so `İnk` slugs to `nk` here too rather than `i-nk`.
 */
function slugify(value: string): string {
    return phpTrim(value)
        .replace(/[A-Z]/g, (char) => char.toLowerCase())
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}
