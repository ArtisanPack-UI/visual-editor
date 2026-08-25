/**
 * Installed-fonts CSS builder — unit tests (#636).
 *
 * The style-book preview renders inline in the main document, so it needs the
 * installed fonts' `@font-face` rules and preset custom properties injected
 * there. These tests lock the output to parity with the PHP
 * `FontsCssGenerator`: spaces preserved in family names, `ttf` normalized to
 * `truetype`, the `", sans-serif"` preset fallback, and url-less faces skipped.
 *
 * @since 1.7.0
 */

import { describe, expect, it } from 'vitest';

import type { FontFace, InstalledFont } from '../api-client';
import { buildInstalledFontFacesCss } from '../installed-fonts-css';

function makeFace(overrides: Partial<FontFace> = {}): FontFace {
    return {
        id: 1,
        weight: 400,
        style: 'normal',
        format: 'woff2',
        axes: null,
        url: 'https://example.test/fonts/roboto-slab/400.woff2',
        ...overrides,
    };
}

function makeFont(overrides: Partial<InstalledFont> = {}): InstalledFont {
    return {
        id: 1,
        provider: 'google',
        family: 'Roboto Slab',
        slug: 'roboto-slab',
        is_variable: false,
        license: null,
        source_url: null,
        installed_at: null,
        faces: [makeFace()],
        ...overrides,
    };
}

describe('buildInstalledFontFacesCss', () => {
    it('returns an empty string when nothing is installed', () => {
        expect(buildInstalledFontFacesCss([])).toBe('');
    });

    it('emits an @font-face rule and preset property, keeping spaces in the family', () => {
        const css = buildInstalledFontFacesCss([makeFont()]);

        expect(css).toContain('@font-face');
        expect(css).toContain('font-family: "Roboto Slab"');
        expect(css).toContain('font-style: normal');
        expect(css).toContain('font-weight: 400');
        expect(css).toContain('format("woff2")');
        expect(css).toContain(
            '--wp--preset--font-family--roboto-slab: "Roboto Slab", sans-serif;'
        );
    });

    it('normalizes a ttf format to truetype', () => {
        const css = buildInstalledFontFacesCss([
            makeFont({ faces: [makeFace({ format: 'ttf' })] }),
        ]);

        expect(css).toContain('format("truetype")');
    });

    it('skips a face with no resolvable url but still emits the preset', () => {
        const css = buildInstalledFontFacesCss([
            makeFont({ faces: [makeFace({ url: null })] }),
        ]);

        expect(css).not.toContain('@font-face');
        expect(css).toContain('--wp--preset--font-family--roboto-slab');
    });

    it('neutralizes a family name that tries to break out of the style element', () => {
        const css = buildInstalledFontFacesCss([
            makeFont({ family: 'Ev"il</style><script>\n', slug: 'evil' }),
        ]);

        // Angle brackets are stripped, so a literal </style> can never form;
        // the quote is escaped so it can't close the CSS string.
        expect(css).not.toContain('<');
        expect(css).not.toContain('>');
        expect(css).toContain('Ev\\"il');
    });

    it('neutralizes a url that tries to break out of the style element', () => {
        const css = buildInstalledFontFacesCss([
            makeFont({
                faces: [makeFace({ url: 'https://x/f.woff2")}</style><b>' })],
            }),
        ]);

        expect(css).not.toContain('<');
        expect(css).not.toContain('>');
        // The closing quote in the URL is escaped, keeping it inside url("…").
        expect(css).toContain('f.woff2\\")}');
    });

    it('omits the format() token when the face format is unknown', () => {
        const css = buildInstalledFontFacesCss([
            makeFont({ faces: [makeFace({ format: null })] }),
        ]);

        expect(css).toContain('src: url("https://example.test/fonts/roboto-slab/400.woff2");');
        expect(css).not.toContain('format(');
    });
});
