/**
 * WCAG 2.0 contrast helpers.
 *
 * TypeScript port of the helpers exposed by the
 * `artisanpack-ui/accessibility` PHP package (`a11yCheckContrastColor`,
 * `a11yGetContrastColor`). The package itself is PHP-only — bundling its
 * Composer build into the editor isn't viable — so the math lives here
 * and the API names mirror the PHP side intentionally so anyone reading
 * across both stacks recognises the contract. Algorithm is straight from
 * https://www.w3.org/TR/WCAG20-TECHS/G18.html (relative luminance) and
 * §1.4.3 (4.5:1 AA contrast ratio for normal text).
 *
 * Restoring this for #348 after #343/A1 disabled Gutenberg's built-in
 * `BlockColorContrastChecker`. The upstream checker reads computed
 * styles via a deps-less `useLayoutEffect` and triggered the
 * color-picker drag crash; this implementation is attribute-driven so
 * it cannot reintroduce that loop.
 */
export const WCAG_AA_NORMAL_TEXT_RATIO = 4.5;

interface RGB {
    readonly r: number;
    readonly g: number;
    readonly b: number;
}

const HEX_PATTERN = /^#([a-f0-9]{3}|[a-f0-9]{6})$/i;

function expandShorthandHex(hex: string): string {
    if (hex.length !== 3) {
        return hex;
    }

    return hex
        .split('')
        .map((character) => character + character)
        .join('');
}

function hexToRgb(hex: string): RGB | null {
    if (typeof hex !== 'string') {
        return null;
    }

    const trimmed = hex.trim();

    if (!HEX_PATTERN.test(trimmed)) {
        return null;
    }

    const expanded = expandShorthandHex(trimmed.replace(/^#/, ''));

    return {
        r: parseInt(expanded.substring(0, 2), 16),
        g: parseInt(expanded.substring(2, 4), 16),
        b: parseInt(expanded.substring(4, 6), 16),
    };
}

function sRGBtoLinear(channel: number): number {
    const normalised = channel / 255;

    if (normalised <= 0.03928) {
        return normalised / 12.92;
    }

    return Math.pow((normalised + 0.055) / 1.055, 2.4);
}

function relativeLuminance(rgb: RGB): number {
    const r = sRGBtoLinear(rgb.r);
    const g = sRGBtoLinear(rgb.g);
    const b = sRGBtoLinear(rgb.b);

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/**
 * Calculates the WCAG contrast ratio between two hex colors. Returns
 * `null` when either color cannot be parsed so callers can distinguish
 * "no warning to show" from "ratio is genuinely too low".
 */
export function getContrastRatio(
    foreground: string,
    background: string
): number | null {
    const fg = hexToRgb(foreground);
    const bg = hexToRgb(background);

    if (fg === null || bg === null) {
        return null;
    }

    const l1 = relativeLuminance(fg);
    const l2 = relativeLuminance(bg);
    const lighter = Math.max(l1, l2);
    const darker = Math.min(l1, l2);

    return (lighter + 0.05) / (darker + 0.05);
}

/**
 * Mirrors `A11y::a11yCheckContrastColor` in the PHP package. Returns
 * `true` when the two hex colors meet WCAG AA for normal text
 * (≥ 4.5:1). Invalid hex inputs are treated as failing checks — same as
 * the PHP helper, which catches `InvalidArgumentException` and returns
 * `false`.
 */
export function a11yCheckContrastColor(
    foreground: string,
    background: string
): boolean {
    const ratio = getContrastRatio(foreground, background);

    if (ratio === null) {
        return false;
    }

    return ratio >= WCAG_AA_NORMAL_TEXT_RATIO;
}

/**
 * Mirrors `A11y::a11yGetContrastColor` in the PHP package. Returns
 * `#000000` or `#ffffff` based on whichever has the higher contrast
 * ratio against the supplied background. Falls back to `#000000` for
 * unparseable input — matches the PHP behaviour of leaning on dark text
 * when the algorithm can't decide.
 */
export function a11yGetContrastColor(background: string): '#000000' | '#ffffff' {
    const blackRatio = getContrastRatio('#000000', background);
    const whiteRatio = getContrastRatio('#ffffff', background);

    if (blackRatio === null || whiteRatio === null) {
        return '#000000';
    }

    return blackRatio >= whiteRatio ? '#000000' : '#ffffff';
}
