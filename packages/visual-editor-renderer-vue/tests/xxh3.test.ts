/**
 * XXH3-64 port tests (#712).
 *
 * Reference digests were produced with PHP's `hash( 'xxh3', $string )` — the
 * exact function the Blade renderer uses to mint the `ve-w-<hash>` column
 * scope class. Every length class is covered, including the class boundaries
 * (16/17, 128/129, 240/241) and the >240-byte long-hash path (#H1).
 */

import { describe, expect, it } from 'vitest';
import { xxh3_64_hex } from '../src/support/xxh3';

const REFERENCE: Array<[string, string]> = [
    ['', '2d06800538d394c2'],
    ['a', 'e6c632b61e964e1f'],
    ['abc', '78af5f94892f3950'],
    ['abcd', '6497a96f53a89890'],
    ['abcdefgh', '6f45a76842a96483'],
    ['abcdefghi', 'e0dde4fc174590a0'],
    ['abcdefghijklmnop', '3d3ccac9af14d8a8'],
    ['The quick brown fox', 'f8b92649fd8122b4'],
    ['{"base":50}', '43b5435e6e9fe34a'],
    ['{"base":60}', '971ccacfaf004c5f'],
    ['{"base":"100px"}', '99b2f912b2a363d2'],
    ['{"base":"33.33%","md":"50%"}', 'f9f82d661b805a3c'],
    ['x'.repeat(17), '89975e6b7d2f5a11'],
    ['w'.repeat(128), '1bbc3cbdf0d1ce93'],
    ['q'.repeat(129), '348020316d0e37b5'],
    ['s'.repeat(240), 'a06a3a0eb808db58'],
    // >240-byte long-hash path (#H1): boundary, block boundaries, and a
    // realistic long column-width map with several calc() overrides.
    ['z'.repeat(241), 'c9b6e99de4449036'],
    ['z'.repeat(512), '8421bafb2300a34b'],
    ['z'.repeat(1024), '4dd067f3fa10df7e'],
    ['z'.repeat(1025), '1453e498eaefa851'],
    [
        JSON.stringify({
            base: 'calc(100% - var(--wp--style--block-gap, 0.5em) * 0.5)'.repeat(5),
            md: 'calc(100% - var(--wp--style--block-gap, 0.5em) * 0.5)'.repeat(5),
        }),
        'ac402111e2bb229a',
    ],
];

describe('xxh3_64_hex', () => {
    it.each(REFERENCE)('matches PHP hash("xxh3", %j)', (input, expected) => {
        expect(xxh3_64_hex(input)).toBe(expected);
    });

    it('always returns 16 lowercase hex characters', () => {
        for (const [input] of REFERENCE) {
            expect(xxh3_64_hex(input)).toMatch(/^[0-9a-f]{16}$/);
        }
    });

    it('hashes input beyond 240 bytes via the long-hash path without throwing', () => {
        expect(() => xxh3_64_hex('z'.repeat(2000))).not.toThrow();
        expect(xxh3_64_hex('z'.repeat(241))).toMatch(/^[0-9a-f]{16}$/);
    });
});
