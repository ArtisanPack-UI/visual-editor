/**
 * Tests for the `artisanpack/image` width control.
 *
 * Focus is on the pure helpers (`parseWidth`, `composeWidth`, `matchPreset`)
 * so the round-trip contract stays enforced without pulling the full
 * `@wordpress/components` render tree.
 */

import { describe, it, expect } from 'vitest';

import {
    composeWidth,
    matchPreset,
    parseWidth,
} from '../width-control';

describe('parseWidth', () => {
    it('returns undefined value and default unit for empty input', () => {
        expect(parseWidth(undefined, undefined)).toEqual({
            value: undefined,
            unit: 'px',
        });
        expect(parseWidth('', undefined)).toEqual({
            value: undefined,
            unit: 'px',
        });
    });

    it('parses a "300px" string as 300 px', () => {
        expect(parseWidth('300px', undefined)).toEqual({
            value: 300,
            unit: 'px',
        });
    });

    it('parses a "50%" string as 50 %', () => {
        expect(parseWidth('50%', undefined)).toEqual({
            value: 50,
            unit: '%',
        });
    });

    it('treats a bare number as the stored unit when known, else px', () => {
        expect(parseWidth('300', '%')).toEqual({ value: 300, unit: '%' });
        expect(parseWidth('300', undefined)).toEqual({
            value: 300,
            unit: 'px',
        });
    });

    it('returns undefined for garbage input, preserving stored unit', () => {
        expect(parseWidth('nope', '%')).toEqual({
            value: undefined,
            unit: '%',
        });
    });

    it('parses decimals', () => {
        expect(parseWidth('12.5%', undefined)).toEqual({
            value: 12.5,
            unit: '%',
        });
    });
});

describe('composeWidth', () => {
    it('composes numeric + unit back into a CSS length', () => {
        expect(composeWidth(300, 'px')).toBe('300px');
        expect(composeWidth(50, '%')).toBe('50%');
    });
});

describe('matchPreset', () => {
    it('matches the four registered preset percentages', () => {
        expect(matchPreset('25%')).toBe('small');
        expect(matchPreset('50%')).toBe('medium');
        expect(matchPreset('75%')).toBe('large');
        expect(matchPreset('100%')).toBe('full');
    });

    it('returns undefined for a custom or px-based width', () => {
        expect(matchPreset('300px')).toBeUndefined();
        expect(matchPreset('42%')).toBeUndefined();
        expect(matchPreset(undefined)).toBeUndefined();
    });

    it('does not false-positive on a bare number (legacy px)', () => {
        // "50" is 50px, not 50% — must not resolve to `medium`.
        expect(matchPreset('50')).toBeUndefined();
        expect(matchPreset('25')).toBeUndefined();
    });
});

describe('parseWidth negative-value handling', () => {
    it('rejects negative widths', () => {
        expect(parseWidth('-300px', undefined)).toEqual({
            value: undefined,
            unit: 'px',
        });
    });
});

describe('round-trip parseWidth → composeWidth', () => {
    it('is idempotent for canonical inputs', () => {
        const inputs = ['300px', '50%', '12.5%', '1000px'];
        for (const input of inputs) {
            const { value, unit } = parseWidth(input, undefined);
            expect(value).not.toBeUndefined();
            expect(composeWidth(value as number, unit)).toBe(input);
        }
    });
});
