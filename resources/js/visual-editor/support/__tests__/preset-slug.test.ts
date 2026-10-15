/**
 * The editor's preset slug segment (R-19) matches PHP
 * `PresetRegistry::presetSlug()` byte for byte. Expected values are the
 * PHP output.
 */

import { describe, expect, it } from 'vitest';

import { presetSlug } from '../preset-slug';

describe('presetSlug', () => {
    it.each([
        ['bigGap', 'big-gap'],
        ['Big Gap', 'big-gap'],
        ['x-2XL', 'x-2xl'],
        ['İnk', '--nk'],
        ['Café', 'caf--'],
        ['a_日😀', 'a--------'],
        // Kelvin sign: Unicode lowercases it to ASCII `k`; PHP doesn't.
        ['\u212Ax', '---x'],
    ])('%s → %s', (input, expected) => {
        expect(presetSlug(input)).toBe(expected);
    });
});
