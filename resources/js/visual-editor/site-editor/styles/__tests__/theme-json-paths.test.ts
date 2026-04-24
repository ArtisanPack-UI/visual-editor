import { describe, expect, it } from 'vitest';

import {
    isCustomized,
    readPath,
    unsetPath,
    writePath,
} from '../theme-json-paths';

describe('readPath', () => {
    it('returns undefined for any miss along the path', () => {
        expect(readPath(undefined, ['a'])).toBeUndefined();
        expect(readPath({}, ['a'])).toBeUndefined();
        expect(readPath({ a: {} }, ['a', 'b'])).toBeUndefined();
        expect(readPath({ a: { b: null } }, ['a', 'b', 'c'])).toBeUndefined();
    });

    it('reads deep values', () => {
        expect(readPath({ a: { b: { c: 42 } } }, ['a', 'b', 'c'])).toBe(42);
    });
});

describe('writePath', () => {
    it('writes a value without mutating the source', () => {
        const source = { a: 1 };
        const next = writePath(source, ['b'], 2);

        expect(next).toEqual({ a: 1, b: 2 });
        expect(source).toEqual({ a: 1 });
    });

    it('creates intermediate objects on demand', () => {
        const next = writePath({}, ['a', 'b', 'c'], 'x');

        expect(next).toEqual({ a: { b: { c: 'x' } } });
    });

    it('preserves sibling branches', () => {
        const next = writePath(
            { a: { b: 1, c: 2 } },
            ['a', 'b'],
            'updated'
        );

        expect(next).toEqual({ a: { b: 'updated', c: 2 } });
    });
});

describe('unsetPath', () => {
    it('removes the leaf and prunes empty ancestors', () => {
        const next = unsetPath(
            { a: { b: { c: 1 } } },
            ['a', 'b', 'c']
        );

        expect(next).toEqual({});
    });

    it('keeps siblings untouched', () => {
        const next = unsetPath(
            { a: { b: 1, c: 2 } },
            ['a', 'b']
        );

        expect(next).toEqual({ a: { c: 2 } });
    });

    it('is a no-op when the path is missing', () => {
        const next = unsetPath({ a: 1 }, ['b', 'c']);

        expect(next).toEqual({ a: 1 });
    });
});

describe('isCustomized', () => {
    it('returns false when the user value is undefined', () => {
        expect(isCustomized(undefined, 'base')).toBe(false);
    });

    it('returns true when the user value differs from base', () => {
        expect(isCustomized('user', 'base')).toBe(true);
        expect(isCustomized(['a', 'b'], ['a'])).toBe(true);
    });

    it('returns false when the user value equals base', () => {
        expect(isCustomized('same', 'same')).toBe(false);
        expect(isCustomized({ a: 1 }, { a: 1 })).toBe(false);
    });
});
