/**
 * Opaque CSS color → hex normalisation for contrast measurement (#695).
 *
 * `wcag-contrast.ts` deliberately parses only `#rgb` / `#rrggbb`, because
 * it mirrors the `artisanpack-ui/accessibility` PHP helpers one-for-one
 * and those are hex-only. That is the right contract for the block
 * contrast warning, whose inputs come from Gutenberg color pickers and
 * are always hex.
 *
 * theme.json is a different source. It is hand-authored as often as it
 * is generated, and `styles.color.background` legitimately carries
 * `rgb(17 24 39)`, `hsl(220 39% 11%)`, `black`, or `#111827ff` — every
 * one of which `canvas-color-tokens.ts` accepts and emits, but none of
 * which the hex-only parser can measure. Before this module existed the
 * consequence was silent and severe: an unpaired dark background in any
 * non-hex syntax emitted `--ap-editor-canvas-bg` while leaving
 * `--ap-editor-canvas-fg` on the `#1f2937` default, reproducing the
 * exact invisible-text failure #695 exists to remove.
 *
 * So this converts the opaque CSS color syntaxes to the `#rrggbb` form
 * the contrast math understands, and returns `null` for anything it
 * cannot resolve to a fixed opaque color:
 *
 *   - `var()` / `color-mix()` references, which resolve only in the
 *     browser;
 *   - translucent values (alpha < 1), whose rendered color depends on
 *     whatever is painted behind them;
 *   - `currentColor`, `inherit`, `transparent`, and typos.
 *
 * Kept local to the editor rather than folded into `wcag-contrast.ts` so
 * the contrast-warning contract stays a faithful port of the PHP side.
 *
 * @package @artisanpack-ui/visual-editor
 * @since   1.6.0
 */

interface RGBTriple {
    readonly r: number;
    readonly g: number;
    readonly b: number;
}

/**
 * CSS Color Module Level 4 named colors.
 *
 * The full table rather than a convenient subset — a partial list fails
 * exactly the way the hex-only parser did, silently and only for the
 * themes unlucky enough to use a name that didn't make the cut.
 * `transparent` is intentionally absent: it is not an opaque color.
 */
const NAMED_COLORS: Readonly<Record<string, string>> = {
    aliceblue: '#f0f8ff',
    antiquewhite: '#faebd7',
    aqua: '#00ffff',
    aquamarine: '#7fffd4',
    azure: '#f0ffff',
    beige: '#f5f5dc',
    bisque: '#ffe4c4',
    black: '#000000',
    blanchedalmond: '#ffebcd',
    blue: '#0000ff',
    blueviolet: '#8a2be2',
    brown: '#a52a2a',
    burlywood: '#deb887',
    cadetblue: '#5f9ea0',
    chartreuse: '#7fff00',
    chocolate: '#d2691e',
    coral: '#ff7f50',
    cornflowerblue: '#6495ed',
    cornsilk: '#fff8dc',
    crimson: '#dc143c',
    cyan: '#00ffff',
    darkblue: '#00008b',
    darkcyan: '#008b8b',
    darkgoldenrod: '#b8860b',
    darkgray: '#a9a9a9',
    darkgreen: '#006400',
    darkgrey: '#a9a9a9',
    darkkhaki: '#bdb76b',
    darkmagenta: '#8b008b',
    darkolivegreen: '#556b2f',
    darkorange: '#ff8c00',
    darkorchid: '#9932cc',
    darkred: '#8b0000',
    darksalmon: '#e9967a',
    darkseagreen: '#8fbc8f',
    darkslateblue: '#483d8b',
    darkslategray: '#2f4f4f',
    darkslategrey: '#2f4f4f',
    darkturquoise: '#00ced1',
    darkviolet: '#9400d3',
    deeppink: '#ff1493',
    deepskyblue: '#00bfff',
    dimgray: '#696969',
    dimgrey: '#696969',
    dodgerblue: '#1e90ff',
    firebrick: '#b22222',
    floralwhite: '#fffaf0',
    forestgreen: '#228b22',
    fuchsia: '#ff00ff',
    gainsboro: '#dcdcdc',
    ghostwhite: '#f8f8ff',
    gold: '#ffd700',
    goldenrod: '#daa520',
    gray: '#808080',
    green: '#008000',
    greenyellow: '#adff2f',
    grey: '#808080',
    honeydew: '#f0fff0',
    hotpink: '#ff69b4',
    indianred: '#cd5c5c',
    indigo: '#4b0082',
    ivory: '#fffff0',
    khaki: '#f0e68c',
    lavender: '#e6e6fa',
    lavenderblush: '#fff0f5',
    lawngreen: '#7cfc00',
    lemonchiffon: '#fffacd',
    lightblue: '#add8e6',
    lightcoral: '#f08080',
    lightcyan: '#e0ffff',
    lightgoldenrodyellow: '#fafad2',
    lightgray: '#d3d3d3',
    lightgreen: '#90ee90',
    lightgrey: '#d3d3d3',
    lightpink: '#ffb6c1',
    lightsalmon: '#ffa07a',
    lightseagreen: '#20b2aa',
    lightskyblue: '#87cefa',
    lightslategray: '#778899',
    lightslategrey: '#778899',
    lightsteelblue: '#b0c4de',
    lightyellow: '#ffffe0',
    lime: '#00ff00',
    limegreen: '#32cd32',
    linen: '#faf0e6',
    magenta: '#ff00ff',
    maroon: '#800000',
    mediumaquamarine: '#66cdaa',
    mediumblue: '#0000cd',
    mediumorchid: '#ba55d3',
    mediumpurple: '#9370db',
    mediumseagreen: '#3cb371',
    mediumslateblue: '#7b68ee',
    mediumspringgreen: '#00fa9a',
    mediumturquoise: '#48d1cc',
    mediumvioletred: '#c71585',
    midnightblue: '#191970',
    mintcream: '#f5fffa',
    mistyrose: '#ffe4e1',
    moccasin: '#ffe4b5',
    navajowhite: '#ffdead',
    navy: '#000080',
    oldlace: '#fdf5e6',
    olive: '#808000',
    olivedrab: '#6b8e23',
    orange: '#ffa500',
    orangered: '#ff4500',
    orchid: '#da70d6',
    palegoldenrod: '#eee8aa',
    palegreen: '#98fb98',
    paleturquoise: '#afeeee',
    palevioletred: '#db7093',
    papayawhip: '#ffefd5',
    peachpuff: '#ffdab9',
    peru: '#cd853f',
    pink: '#ffc0cb',
    plum: '#dda0dd',
    powderblue: '#b0e0e6',
    purple: '#800080',
    rebeccapurple: '#663399',
    red: '#ff0000',
    rosybrown: '#bc8f8f',
    royalblue: '#4169e1',
    saddlebrown: '#8b4513',
    salmon: '#fa8072',
    sandybrown: '#f4a460',
    seagreen: '#2e8b57',
    seashell: '#fff5ee',
    sienna: '#a0522d',
    silver: '#c0c0c0',
    skyblue: '#87ceeb',
    slateblue: '#6a5acd',
    slategray: '#708090',
    slategrey: '#708090',
    snow: '#fffafa',
    springgreen: '#00ff7f',
    steelblue: '#4682b4',
    tan: '#d2b48c',
    teal: '#008080',
    thistle: '#d8bfd8',
    tomato: '#ff6347',
    turquoise: '#40e0d0',
    violet: '#ee82ee',
    wheat: '#f5deb3',
    white: '#ffffff',
    whitesmoke: '#f5f5f5',
    yellow: '#ffff00',
    yellowgreen: '#9acd32',
};

const HEX_DIGITS = /^#([0-9a-f]+)$/i;
const FUNCTIONAL = /^(rgba?|hsla?)\(([^)]*)\)$/i;
const NUMBER = /^[+-]?(?:\d+\.?\d*|\.\d+)$/;
const ANGLE = /^([+-]?(?:\d+\.?\d*|\.\d+))(deg|grad|rad|turn)?$/i;
const PERCENTAGE = /^([+-]?(?:\d+\.?\d*|\.\d+))%$/;

function clamp(value: number, min: number, max: number): number {
    return Math.min(max, Math.max(min, value));
}

function toHexPair(channel: number): string {
    return clamp(Math.round(channel), 0, 255).toString(16).padStart(2, '0');
}

function toHex(rgb: RGBTriple): string {
    return `#${toHexPair(rgb.r)}${toHexPair(rgb.g)}${toHexPair(rgb.b)}`;
}

function expandShorthand(digits: string): string {
    return digits
        .split('')
        .map((character) => character + character)
        .join('');
}

/**
 * Hex in any of the four CSS lengths. The alpha-bearing forms resolve
 * only when fully opaque — a translucent color has no fixed rendered
 * value to measure.
 */
function fromHex(value: string): string | null {
    const match = HEX_DIGITS.exec(value);

    if (match === null) {
        return null;
    }

    const digits = match[1].toLowerCase();

    if (digits.length === 3) {
        return `#${expandShorthand(digits)}`;
    }

    if (digits.length === 4) {
        return digits[3] === 'f' ? `#${expandShorthand(digits.slice(0, 3))}` : null;
    }

    if (digits.length === 6) {
        return `#${digits}`;
    }

    if (digits.length === 8) {
        return digits.slice(6) === 'ff' ? `#${digits.slice(0, 6)}` : null;
    }

    return null;
}

/**
 * An `rgb()` color channel: `0`–`255`, or a percentage of 255.
 */
function parseRgbChannel(token: string): number | null {
    const percentage = PERCENTAGE.exec(token);

    if (percentage !== null) {
        return clamp((Number(percentage[1]) * 255) / 100, 0, 255);
    }

    if (!NUMBER.test(token)) {
        return null;
    }

    return clamp(Number(token), 0, 255);
}

/**
 * An alpha channel, as a `0`–`1` number or a percentage. Anything short
 * of fully opaque returns `null` from the callers — see {@link fromHex}.
 */
function parseAlpha(token: string): number | null {
    const percentage = PERCENTAGE.exec(token);

    if (percentage !== null) {
        return clamp(Number(percentage[1]) / 100, 0, 1);
    }

    if (!NUMBER.test(token)) {
        return null;
    }

    return clamp(Number(token), 0, 1);
}

/** A hue, in any of the CSS angle units, normalised to degrees. */
function parseHue(token: string): number | null {
    const match = ANGLE.exec(token);

    if (match === null) {
        return null;
    }

    const value = Number(match[1]);

    switch ((match[2] ?? 'deg').toLowerCase()) {
        case 'deg':
            return value;
        case 'grad':
            return value * 0.9;
        case 'rad':
            return (value * 180) / Math.PI;
        case 'turn':
            return value * 360;
        default:
            return null;
    }
}

function hslToRgb(hue: number, saturation: number, lightness: number): RGBTriple {
    const normalisedHue = ((hue % 360) + 360) % 360;
    const chroma = (1 - Math.abs(2 * lightness - 1)) * saturation;
    const secondary = chroma * (1 - Math.abs(((normalisedHue / 60) % 2) - 1));
    const offset = lightness - chroma / 2;

    let r = 0;
    let g = 0;
    let b = 0;

    if (normalisedHue < 60) {
        r = chroma;
        g = secondary;
    } else if (normalisedHue < 120) {
        r = secondary;
        g = chroma;
    } else if (normalisedHue < 180) {
        g = chroma;
        b = secondary;
    } else if (normalisedHue < 240) {
        g = secondary;
        b = chroma;
    } else if (normalisedHue < 300) {
        r = secondary;
        b = chroma;
    } else {
        r = chroma;
        b = secondary;
    }

    return {
        r: (r + offset) * 255,
        g: (g + offset) * 255,
        b: (b + offset) * 255,
    };
}

/**
 * Split the inside of an `rgb()` / `hsl()` call into its arguments.
 * Handles both the legacy comma syntax (`rgb(0, 0, 0)`) and the modern
 * space syntax with a slash-delimited alpha (`rgb(0 0 0 / 50%)`).
 */
function splitArguments(inner: string): string[] {
    return inner
        .trim()
        .split(/[\s,/]+/)
        .filter((token) => token !== '');
}

function fromFunctional(value: string): string | null {
    const match = FUNCTIONAL.exec(value);

    if (match === null) {
        return null;
    }

    const fn = match[1].toLowerCase();
    const args = splitArguments(match[2]);

    if (args.length < 3 || args.length > 4) {
        return null;
    }

    if (args.length === 4) {
        const alpha = parseAlpha(args[3]);

        // A translucent color renders against whatever sits behind it,
        // so there is no single value to measure contrast against.
        if (alpha === null || alpha < 1) {
            return null;
        }
    }

    if (fn === 'rgb' || fn === 'rgba') {
        const channels = args.slice(0, 3).map(parseRgbChannel);

        if (channels.some((channel) => channel === null)) {
            return null;
        }

        return toHex({
            r: channels[0] as number,
            g: channels[1] as number,
            b: channels[2] as number,
        });
    }

    const hue = parseHue(args[0]);
    const saturation = PERCENTAGE.exec(args[1]);
    const lightness = PERCENTAGE.exec(args[2]);

    if (hue === null || saturation === null || lightness === null) {
        return null;
    }

    return toHex(
        hslToRgb(
            hue,
            clamp(Number(saturation[1]) / 100, 0, 1),
            clamp(Number(lightness[1]) / 100, 0, 1)
        )
    );
}

/**
 * Resolve an opaque CSS color to `#rrggbb` so the WCAG helpers can
 * measure it. Returns `null` for values with no fixed opaque color —
 * `var()` references, translucent colors, keywords like `currentColor`,
 * and anything malformed.
 */
export function toMeasurableHex(value: string): string | null {
    const trimmed = value.trim();

    if (trimmed === '') {
        return null;
    }

    const named = NAMED_COLORS[trimmed.toLowerCase()];

    if (named !== undefined) {
        return named;
    }

    return fromHex(trimmed) ?? fromFunctional(trimmed);
}
