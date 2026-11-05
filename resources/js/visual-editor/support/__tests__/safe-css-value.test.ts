/**
 * The editor's gradient shape check matches PHP `CssValue::isGradient()`.
 */

import { describe, expect, it } from 'vitest';

import { isGradient } from '../safe-css-value';

describe('isGradient', () => {
    it.each([
        'linear-gradient(135deg, rgba(0, 0, 0, 0.5), var(--x))',
        'repeating-radial-gradient(circle, red, color-mix(in srgb, red, blue))',
        'var(--wp--preset--gradient--sunset)',
    ])('accepts %s', (value) => {
        expect(isGradient(value)).toBe(true);
    });

    it.each([
        'url(t.png)',
        'image-set(t.png 1x)',
        'linear-gradient(url(t.png), blue)',
        'linear-gradient(red, blue),url(t.png)',
        'linear-gradient(red, blue) padding-box',
        'linear-gradient(red, blue',
        'red',
        'var(--x, url(t.png))',
    ])('rejects %s', (value) => {
        expect(isGradient(value)).toBe(false);
    });
});
