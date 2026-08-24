/**
 * SHA-1 port tests (#714).
 *
 * The `photo-grid-<hash>` scope class must match the Blade partial
 * byte-for-byte, so these pin the digest against published reference
 * vectors plus the exact declaration string the Photo Grid wrapper hashes.
 */

import { describe, expect, it } from 'vitest';
import { sha1Hex } from '../src/support/sha1';

describe('sha1Hex', () => {
    it('matches the published reference vectors', () => {
        expect(sha1Hex('')).toBe('da39a3ee5e6b4b0d3255bfef95601890afd80709');
        expect(sha1Hex('abc')).toBe('a9993e364706816aba3e25717850c26c9cd0d89d');
        expect(sha1Hex('The quick brown fox jumps over the lazy dog')).toBe(
            '2fd4e1c67a2d28fced849ee1bb76e7391b93eb12'
        );
    });

    it('spans a multi-block message (>55 bytes forces a second chunk)', () => {
        // 56 'a' characters: the padding no longer fits in the first
        // 64-byte block, so the two-chunk path is exercised.
        expect(sha1Hex('a'.repeat(56))).toBe('c2db330f6083854c99d4b5bfb6e8f29f201be699');
        expect(sha1Hex('a'.repeat(1000))).toBe('291e9a6c66994949b57ba5e650361e98fc36b1ba');
    });

    it('hashes UTF-8 bytes, matching PHP sha1()', () => {
        // "café" — the é is two UTF-8 bytes, so a naive charCode hash would
        // diverge from PHP.
        expect(sha1Hex('café')).toBe('f424452a9673918c6f09b0cdd35b20be8e6ae7d7');
    });

    it('hashes the Photo Grid declaration to Blade\'s scope token', () => {
        const declaration =
            '--ap-photo-grid-fit:cover;--ap-photo-grid-position:50% 50%;--ap-photo-grid-aspect:16/9;';

        expect(sha1Hex(declaration)).toBe('6873a2dd85dc07acd137b52bcebcb9b6a50ce7d5');
        expect(sha1Hex(declaration).slice(0, 12)).toBe('6873a2dd85dc');
    });
});
