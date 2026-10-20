/**
 * Block-supports compiler — Vue renderer (#920).
 *
 * Twin of the Blade renderer's `BlockSupports::compile()`: walks a block's
 * colour, background, spacing, border, typography and dimensions supports
 * and returns the wrapper classes plus the inline style declarations, in
 * the order Blade emits them. Each block renderer merges the classes after
 * its own block-specific ones (and before the author `className`) and the
 * style ahead of any block-specific declarations, as `wrapperAttrs()` does.
 *
 * Alignment, the author `className` and the anchor `id` are left to the
 * block renderers, which already place them.
 *
 * The mirror lives at the matching path in the React renderer.
 *
 * @package @artisanpack-ui/visual-editor-renderer-vue
 * @since 1.15.2
 */

import { attrRecord, phpTrim } from './attributes';
import { applyBackground } from './background';
import { applyBlockGap } from './blockGap';
import { applyDimensions, dimensionsClassName } from './dimensions';
import { presetPickClass } from './layoutBaselineCss';
import { expandPresetReference } from './presetReference';

/** The compiled wrapper output: classes in Blade order and a style object (camelCased keys). */
export interface CompiledBlockSupports {
    classNames: string[];
    style: Record<string, string>;
}

const BOX_SIDES = ['top', 'right', 'bottom', 'left'] as const;
const BORDER_CORNERS = ['topLeft', 'topRight', 'bottomLeft', 'bottomRight'] as const;
const BORDER_PROPERTIES = ['color', 'style', 'width'] as const;

/** `style.typography.*` keys and the CSS properties they map to, in Blade order. */
const TYPOGRAPHY_PROPERTIES: ReadonlyArray<[string, string]> = [
    ['fontSize', 'font-size'],
    ['fontFamily', 'font-family'],
    ['fontWeight', 'font-weight'],
    ['fontStyle', 'font-style'],
    ['lineHeight', 'line-height'],
    ['letterSpacing', 'letter-spacing'],
    ['textTransform', 'text-transform'],
    ['textDecoration', 'text-decoration'],
];

/**
 * Compile a block's colour, background, spacing, border, typography and
 * dimensions supports, in `BlockSupports::compile()` order. Classes are
 * deduped like Blade's `array_unique()`.
 */
export function compileBlockSupports(attributes: Record<string, unknown>): CompiledBlockSupports {
    // A legacy string `style` (separator's `"default"`) is a variant id,
    // not a supports bag; Blade drops it from the compile pass.
    const input =
        attributes.style !== undefined && attrRecord(attributes.style) !== attributes.style
            ? { ...attributes, style: undefined }
            : attributes;
    const classNames: string[] = [];
    const style: Record<string, string> = {};

    applyColor(input, classNames, style);
    Object.assign(style, applyBackground(input));
    applySpacing(input, style);
    applyBorder(input, classNames, style);
    applyTypography(input, classNames, style);
    Object.assign(style, applyDimensions(input));

    const aspectRatioClass = dimensionsClassName(input);

    if (aspectRatioClass !== null) {
        classNames.push(aspectRatioClass);
    }

    return { classNames: [...new Set(classNames)], style };
}

/**
 * Split compiled supports into the background output and the rest, as
 * `BlockSupports::splitBackgroundOutput()` does for the cover block (#583),
 * whose background paints on the overlay span rather than the wrapper.
 */
export function splitBackgroundSupports(compiled: CompiledBlockSupports): {
    background: CompiledBlockSupports;
    wrapper: CompiledBlockSupports;
} {
    const background: CompiledBlockSupports = { classNames: [], style: {} };
    const wrapper: CompiledBlockSupports = { classNames: [], style: {} };

    for (const className of compiled.classNames) {
        (isBackgroundClass(className) ? background : wrapper).classNames.push(className);
    }

    for (const [property, value] of Object.entries(compiled.style)) {
        const isBackground = property === 'background' || property === 'backgroundColor' || property === 'backgroundImage';

        (isBackground ? background : wrapper).style[property] = value;
    }

    return { background, wrapper };
}

/**
 * The cover's overlay colour / gradient (`overlayColor`,
 * `customOverlayColor`, `customGradient`), as
 * `BlockSupports::compileCoverOverlay()` emits it onto the overlay span.
 */
export function compileCoverOverlay(attributes: Record<string, unknown>): CompiledBlockSupports {
    const classNames: string[] = [];
    const style: Record<string, string> = {};
    const overlaySlug = stringAttr(attributes.overlayColor);
    const customOverlay = stringAttr(attributes.customOverlayColor);
    const customGradient = stringAttr(attributes.customGradient);

    if (overlaySlug !== '') {
        classNames.push(`has-${slugify(overlaySlug)}-background-color`, 'has-background');
    } else if (customOverlay !== '') {
        style.backgroundColor = expandPresetReference(customOverlay);
        classNames.push('has-background');
    }

    if (customGradient !== '') {
        style.background = expandPresetReference(customGradient);
        classNames.push('has-background');
    }

    return { classNames: [...new Set(classNames)], style };
}

/** Mirrors `BlockSupports::applyColor()`: a slug wins over a custom value. */
function applyColor(attributes: Record<string, unknown>, classNames: string[], style: Record<string, string>): void {
    const color = attrRecord(attrRecord(attributes.style).color);
    const backgroundSlug = stringAttr(attributes.backgroundColor);
    const textSlug = stringAttr(attributes.textColor);
    const gradientSlug = stringAttr(attributes.gradient);
    const customBackground = stringAttr(color.background);
    const customText = stringAttr(color.text);
    const customGradient = stringAttr(color.gradient);

    if (backgroundSlug !== '') {
        classNames.push(`has-${slugify(backgroundSlug)}-background-color`, 'has-background');
    } else if (customBackground !== '') {
        style.backgroundColor = expandPresetReference(customBackground);
        classNames.push('has-background');
    }

    if (gradientSlug !== '') {
        classNames.push(`has-${slugify(gradientSlug)}-gradient-background`, 'has-background');
    } else if (customGradient !== '') {
        style.background = expandPresetReference(customGradient);
        classNames.push('has-background');
    }

    if (textSlug !== '') {
        classNames.push(`has-${slugify(textSlug)}-color`, 'has-text-color');

        const pickClass = presetPickClass(slugify(textSlug));

        if (pickClass !== null) {
            classNames.push(pickClass);
        }
    } else if (customText !== '') {
        style.color = expandPresetReference(customText);
        classNames.push('has-text-color');
    }
}

/** Mirrors `BlockSupports::applySpacing()`: padding, margin, then Block spacing. */
function applySpacing(attributes: Record<string, unknown>, style: Record<string, string>): void {
    const spacing = attrRecord(attrRecord(attributes.style).spacing);

    for (const box of ['padding', 'margin'] as const) {
        const value = spacing[box];

        if (typeof value === 'string') {
            if (value !== '') {
                style[box] = expandPresetReference(value);
            }

            continue;
        }

        const sides = attrRecord(value);

        for (const side of BOX_SIDES) {
            const sideValue = stringAttr(sides[side]);

            if (sideValue !== '') {
                style[camelCase(`${box}-${side}`)] = expandPresetReference(sideValue);
            }
        }
    }

    Object.assign(style, applyBlockGap(attributes));
}

/**
 * Mirrors `BlockSupports::applyBorder()`: the `borderColor` slug, radius,
 * root colour / style / width, per-side overrides, and the `solid` style
 * fallback for a width with no style.
 */
function applyBorder(attributes: Record<string, unknown>, classNames: string[], style: Record<string, string>): void {
    const borderColorSlug = stringAttr(attributes.borderColor);

    if (borderColorSlug !== '') {
        classNames.push('has-border-color', `has-${slugify(borderColorSlug)}-border-color`);
    }

    const rawBorder = attrRecord(attributes.style).border;

    if (rawBorder === null || typeof rawBorder !== 'object') {
        return;
    }

    const border = attrRecord(rawBorder);
    const radius = border.radius;

    if (typeof radius === 'string') {
        if (radius !== '') {
            style.borderRadius = expandPresetReference(radius);
        }
    } else {
        const corners = attrRecord(radius);

        for (const corner of BORDER_CORNERS) {
            const value = stringAttr(corners[corner]);

            if (value !== '') {
                style[`border${corner[0].toUpperCase()}${corner.slice(1)}Radius`] = expandPresetReference(value);
            }
        }
    }

    let hasRootWidthOrStyle = false;

    for (const property of BORDER_PROPERTIES) {
        const value = stringAttr(border[property]);

        if (value === '') {
            continue;
        }

        style[camelCase(`border-${property}`)] = expandPresetReference(value);

        if (property !== 'color') {
            hasRootWidthOrStyle = true;
        }
    }

    // A side-only width gets a style fallback on that side alone, so the
    // untouched edges don't turn on with browser-default widths.
    const sideNeedsStyleFallback: string[] = [];

    for (const side of BOX_SIDES) {
        const sideValues = border[side];

        if (sideValues === null || typeof sideValues !== 'object') {
            continue;
        }

        let sideHasWidth = false;
        let sideHasExplicitStyle = false;

        for (const property of BORDER_PROPERTIES) {
            const value = stringAttr(attrRecord(sideValues)[property]);

            if (value === '') {
                continue;
            }

            style[camelCase(`border-${side}-${property}`)] = expandPresetReference(value);
            sideHasWidth ||= property === 'width';
            sideHasExplicitStyle ||= property === 'style';
        }

        if (sideHasWidth && !sideHasExplicitStyle) {
            sideNeedsStyleFallback.push(side);
        }
    }

    if (hasRootWidthOrStyle && style.borderStyle === undefined) {
        style.borderStyle = 'solid';
    }

    for (const side of sideNeedsStyleFallback) {
        const key = camelCase(`border-${side}-style`);

        if (style[key] === undefined) {
            style[key] = 'solid';
        }
    }
}

/**
 * Mirrors `BlockSupports::applyTypography()`: `fontSize` / `fontFamily`
 * slugs become classes (and win over the matching custom value), the
 * other `style.typography.*` values inline declarations.
 */
function applyTypography(attributes: Record<string, unknown>, classNames: string[], style: Record<string, string>): void {
    const fontSizeSlug = stringAttr(attributes.fontSize);
    const fontFamilySlug = stringAttr(attributes.fontFamily);

    if (fontSizeSlug !== '') {
        classNames.push(`has-${slugify(fontSizeSlug)}-font-size`, 'has-defined-font-size');
    }

    if (fontFamilySlug !== '') {
        classNames.push(`has-${slugify(fontFamilySlug)}-font-family`);
    }

    const rawTypography = attrRecord(attributes.style).typography;

    if (rawTypography === null || typeof rawTypography !== 'object') {
        return;
    }

    const typography = attrRecord(rawTypography);

    for (const [key, property] of TYPOGRAPHY_PROPERTIES) {
        if ((key === 'fontSize' && fontSizeSlug !== '') || (key === 'fontFamily' && fontFamilySlug !== '')) {
            continue;
        }

        const value = stringAttr(typography[key]);

        if (value !== '') {
            style[camelCase(property)] = expandPresetReference(value);
        }
    }
}

/**
 * Mirrors `BlockSupports::isBackgroundClass()`: the `has-background` marker
 * and the palette background / gradient classes.
 */
function isBackgroundClass(className: string): boolean {
    return (
        className === 'has-background' ||
        /^has-[a-z0-9](?:[a-z0-9-]*[a-z0-9])?-(?:background-color|gradient-background)$/.test(className)
    );
}

/** `border-top-width` → `borderTopWidth`, the key Vue and React style objects take. */
function camelCase(property: string): string {
    return property.replace(/-([a-z])/g, (_, char: string) => char.toUpperCase());
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
 * `strtolower()`.
 */
function slugify(value: string): string {
    return phpTrim(value)
        .replace(/[A-Z]/g, (char) => char.toLowerCase())
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}
