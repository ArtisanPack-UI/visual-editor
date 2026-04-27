import { describe, expect, it } from 'vitest';

import {
    a11yCheckContrastColor,
    a11yGetContrastColor,
    getContrastRatio,
    WCAG_AA_NORMAL_TEXT_RATIO,
} from '../wcag-contrast';

describe('getContrastRatio', () => {
    it('returns 21 for black on white (the WCAG max)', () => {
        const ratio = getContrastRatio('#000000', '#ffffff');
        expect(ratio).not.toBeNull();
        expect(ratio).toBeCloseTo(21, 1);
    });

    it('returns 1 for identical colors', () => {
        expect(getContrastRatio('#3b82f6', '#3b82f6')).toBeCloseTo(1, 5);
    });

    it('returns null for unparseable hex', () => {
        expect(getContrastRatio('not-a-color', '#ffffff')).toBeNull();
        expect(getContrastRatio('#ffffff', 'rgb(0,0,0)')).toBeNull();
    });

    it('handles 3-digit shorthand hex', () => {
        const expanded = getContrastRatio('#000', '#fff');
        const long = getContrastRatio('#000000', '#ffffff');
        expect(expanded).toBeCloseTo(long ?? 0, 5);
    });
});

describe('a11yCheckContrastColor', () => {
    it('passes for high-contrast pairs (≥ 4.5:1)', () => {
        expect(a11yCheckContrastColor('#000000', '#ffffff')).toBe(true);
        expect(a11yCheckContrastColor('#1f2937', '#ffffff')).toBe(true);
    });

    it('fails for low-contrast pairs (< 4.5:1)', () => {
        // Light grey text on white — classic readability failure.
        expect(a11yCheckContrastColor('#cccccc', '#ffffff')).toBe(false);
        expect(a11yCheckContrastColor('#999999', '#aaaaaa')).toBe(false);
    });

    it('returns false for invalid hex input rather than throwing', () => {
        expect(a11yCheckContrastColor('blue-500', '#ffffff')).toBe(false);
        expect(a11yCheckContrastColor('#ffffff', '')).toBe(false);
    });

    it('uses 4.5:1 as the AA threshold', () => {
        expect(WCAG_AA_NORMAL_TEXT_RATIO).toBe(4.5);
    });
});

describe('a11yGetContrastColor', () => {
    it('returns white for dark backgrounds', () => {
        expect(a11yGetContrastColor('#000000')).toBe('#ffffff');
        expect(a11yGetContrastColor('#1f2937')).toBe('#ffffff');
    });

    it('returns black for light backgrounds', () => {
        expect(a11yGetContrastColor('#ffffff')).toBe('#000000');
        expect(a11yGetContrastColor('#f3f4f6')).toBe('#000000');
    });

    it('falls back to black for unparseable input', () => {
        expect(a11yGetContrastColor('garbage')).toBe('#000000');
    });
});
