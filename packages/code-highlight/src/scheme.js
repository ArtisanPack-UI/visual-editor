/**
 * Picks the token palette from what's behind a block (#1044).
 *
 * Neither CSS option works here. `prefers-color-scheme` misses a dark section
 * on a light site, and mixing colours from `currentColor` trusts a text colour
 * that's often wrong: a dark section with no text colour set keeps dark text.
 * So the highlighter measures the background in the browser, then sets
 * `data-ap-code-scheme` on the `<code>` element for the stylesheet to read.
 */

/** Below this luminance, white has more contrast than black. */
const DARK_LUMINANCE = 0.18;

const AA_CONTRAST = 4.5;

/**
 * Background luminances each palette meets 4.5:1 on, for every token in
 * code-highlight.css (the comment colour is the binding one in both).
 * Outside them, token colours are dropped rather than shown unreadable.
 */
const LIGHT_PALETTE_MIN = 0.7;
const DARK_PALETTE_MAX = 0.04;

let context = null;

/**
 * Any CSS colour as `[r, g, b, a]`, 0-255. Painting it covers formats a parser
 * would have to know, since browsers report `oklch()` and `lab()` as written.
 */
function readColor(value) {
    context ??= document.createElement('canvas').getContext('2d', { willReadFrequently: true });

    if (context === null) {
        return null;
    }

    context.clearRect(0, 0, 1, 1);
    context.fillStyle = value;
    context.fillRect(0, 0, 1, 1);

    return Array.from(context.getImageData(0, 0, 1, 1).data);
}

function luminance([r, g, b]) {
    const [red, green, blue] = [r, g, b].map((channel) => {
        const value = channel / 255;

        return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
}

function contrast(first, second) {
    const [lighter, darker] = [first, second].sort((a, b) => b - a);

    return (lighter + 0.05) / (darker + 0.05);
}

/**
 * The luminance of what's behind an element: each translucent background
 * blended over the ones below it, down to the first opaque one. `null` if it
 * can't be known, as behind an image or gradient, or when nothing is painted
 * and the page asks for a dark canvas, whose colour the browser picks.
 */
function backgroundLuminance(element) {
    const layers = [];
    let base = null;

    for (let current = element; current !== null; current = current.parentElement) {
        const style = getComputedStyle(current);

        if (style.backgroundImage !== 'none' && style.backgroundImage !== '') {
            return null;
        }

        const color = readColor(style.backgroundColor);

        if (color === null || color[3] === 0) {
            continue;
        }

        if (color[3] >= 255) {
            base = color;
            break;
        }

        layers.push(color);
    }

    if (base === null) {
        if (layers.length === 0) {
            return null;
        }

        // Nothing opaque: the layers sit on the browser's canvas, which is
        // white unless the page opts into a dark one.
        const scheme = getComputedStyle(element.ownerDocument.documentElement).colorScheme ?? '';

        if (/\bdark\b/.test(scheme) && !/\blight\b/.test(scheme)) {
            return null;
        }

        base = [255, 255, 255, 255];
    }

    let [red, green, blue] = base;

    for (let index = layers.length - 1; index >= 0; index--) {
        const [r, g, b, a] = layers[index];
        const alpha = a / 255;

        red = r * alpha + red * (1 - alpha);
        green = g * alpha + green * (1 - alpha);
        blue = b * alpha + blue * (1 - alpha);
    }

    return luminance([red, green, blue]);
}

/**
 * Set a highlighted element's scheme. With no background to measure, the text
 * colour is the best remaining signal: light text implies a dark background.
 * `data-ap-code-contrast="low"` marks text that's unreadable on what's behind
 * it, so the stylesheet can replace it, as it does for punctuation.
 * `data-ap-code-tokens="mono"` marks a background neither palette reaches AA
 * on, so the tokens keep the text colour instead.
 *
 * @param {Element} element A highlighted `<code>` element.
 */
export function applyScheme(element) {
    // Off first: the flag's own replacement colour would read as readable
    // text, clear the flag, and flip it on every later measure.
    element.removeAttribute('data-ap-code-contrast');
    element.removeAttribute('data-ap-code-tokens');

    const text = readColor(getComputedStyle(element).color);
    const textLuminance = text === null ? 0 : luminance(text);
    const background = backgroundLuminance(element);
    const dark = background === null ? textLuminance > DARK_LUMINANCE : background < DARK_LUMINANCE;

    element.setAttribute('data-ap-code-scheme', dark ? 'dark' : 'light');

    if (background !== null && (dark ? background > DARK_PALETTE_MAX : background < LIGHT_PALETTE_MIN)) {
        element.setAttribute('data-ap-code-tokens', 'mono');
    }

    if (background !== null && contrast(textLuminance, background) < AA_CONTRAST) {
        element.setAttribute('data-ap-code-contrast', 'low');
    }
}

let watching = false;

/**
 * Re-measure every highlighted block when the page's theme can have changed:
 * a theme toggle usually sets an attribute on `<html>` or `<body>`, and the
 * system setting fires a media query change.
 */
export function watchScheme() {
    if (watching) {
        return;
    }

    watching = true;

    const refresh = () => {
        document.querySelectorAll('code[data-ap-code-scheme]').forEach(applyScheme);
    };
    const observer = new MutationObserver(refresh);
    const options = { attributes: true, attributeFilter: ['class', 'style', 'data-theme'] };

    observer.observe(document.documentElement, options);

    if (document.body !== null) {
        observer.observe(document.body, options);
    }

    window.matchMedia?.('(prefers-color-scheme: dark)').addEventListener('change', refresh);
}
