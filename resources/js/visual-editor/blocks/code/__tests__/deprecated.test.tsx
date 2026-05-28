/**
 * Locks the (empty) deprecation chain for `artisanpack/code`.
 */

import { describe, it, expect } from 'vitest';

import deprecated from '../deprecated';

describe('code deprecation chain', () => {
    it('ships an empty chain matching upstream (which has none)', () => {
        expect(Array.isArray(deprecated)).toBe(true);
        expect(deprecated).toHaveLength(0);
    });
});
