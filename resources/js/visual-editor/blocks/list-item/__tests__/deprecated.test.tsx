/**
 * Locks the (empty) deprecation chain for `artisanpack/list-item`.
 */

import { describe, it, expect } from 'vitest';

import deprecated from '../deprecated';

describe('list-item deprecation chain', () => {
    it('matches upstream (empty)', () => {
        expect(Array.isArray(deprecated)).toBe(true);
        expect(deprecated).toHaveLength(0);
    });
});
