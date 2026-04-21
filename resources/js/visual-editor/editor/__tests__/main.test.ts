import { describe, expect, it } from 'vitest';

import { normalizeAuthorId } from '../main';

describe('normalizeAuthorId', () => {
    it('returns undefined for empty or missing values', () => {
        expect(normalizeAuthorId(undefined, null)).toBeUndefined();
        expect(normalizeAuthorId('', null)).toBeUndefined();
    });

    it('preserves the original type from authorOptions when it matches by string', () => {
        const result = normalizeAuthorId('42', [
            { value: 42, label: 'Alice' },
            { value: 7, label: 'Bob' },
        ]);

        expect(result).toBe(42);
        expect(typeof result).toBe('number');
    });

    it('preserves a string value when authorOptions uses string ids', () => {
        const result = normalizeAuthorId('alice-01', [
            { value: 'alice-01', label: 'Alice' },
        ]);

        expect(result).toBe('alice-01');
        expect(typeof result).toBe('string');
    });

    it('coerces numeric-looking strings to numbers when no options provided', () => {
        expect(normalizeAuthorId('42', null)).toBe(42);
        expect(normalizeAuthorId('0', null)).toBe(0);
        expect(normalizeAuthorId('-3', null)).toBe(-3);
    });

    it('leaves non-numeric strings as strings when no options provided', () => {
        expect(normalizeAuthorId('alice', null)).toBe('alice');
        expect(normalizeAuthorId('user_1', null)).toBe('user_1');
    });

    it('falls back to numeric coercion when options exist but do not match', () => {
        const result = normalizeAuthorId('99', [
            { value: 1, label: 'Alice' },
            { value: 2, label: 'Bob' },
        ]);

        expect(result).toBe(99);
    });

    it('tolerates a non-array `authorOptions` without throwing', () => {
        // Guards the readMountConfig call site: parseJsonDataset returns
        // whatever the JSON resolves to, so a malformed data-author-options
        // could be `null`, an object, or a primitive. The Array.isArray
        // check up there funnels non-arrays into `null`; make sure
        // normalizeAuthorId also doesn't trip on the nullish case.
        expect(normalizeAuthorId('42', null)).toBe(42);
        expect(normalizeAuthorId('', null)).toBeUndefined();
    });
});
