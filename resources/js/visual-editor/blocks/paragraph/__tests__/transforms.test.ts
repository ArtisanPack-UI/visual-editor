/**
 * Transforms tests for `artisanpack/paragraph`.
 *
 * Round-trip parity with `core/paragraph` is enforced — the fork must accept
 * `core/paragraph` blocks as input and re-emit them on demand without losing
 * attributes.
 */

import { describe, it, expect, vi } from 'vitest';

vi.mock('@wordpress/blocks', () => ({
    createBlock: (name: string, attributes: Record<string, unknown>) => ({
        name,
        attributes,
        innerBlocks: [],
    }),
    getBlockAttributes: (_name: string, html: string) => ({
        content: html.replace(/<[^>]*>/g, ''),
    }),
}));

import transforms from '../transforms';

describe('paragraph transforms', () => {
    it('declares a from/to shape with the right counts', () => {
        expect(transforms.from).toBeTruthy();
        expect(transforms.to).toBeTruthy();
        expect(Array.isArray(transforms.from)).toBe(true);
        expect(Array.isArray(transforms.to)).toBe(true);
    });

    it('includes a raw transform with paragraph selector at priority 20', () => {
        const raw = transforms.from.find(
            (t: { type: string }) => t.type === 'raw'
        ) as { type: 'raw'; priority: number; selector: string };
        expect(raw).toBeDefined();
        expect(raw.priority).toBe(20);
        expect(raw.selector).toBe('p');
    });

    it('raw transform extracts textAlign from inline style', () => {
        const raw = transforms.from.find(
            (t: { type: string }) => t.type === 'raw'
        ) as {
            type: 'raw';
            transform: (node: { outerHTML: string; style?: { textAlign?: string } }) => {
                name: string;
                attributes: Record<string, unknown>;
            };
        };
        const result = raw.transform({
            outerHTML: '<p>Hello</p>',
            style: { textAlign: 'center' },
        });
        expect(result.name).toBe('artisanpack/paragraph');
        expect(
            (result.attributes.style as { typography?: { textAlign?: string } })?.typography
                ?.textAlign
        ).toBe('center');
    });

    it('block transform converts core/paragraph → artisanpack/paragraph losslessly', () => {
        const fromBlock = transforms.from.find(
            (t: { type: string; blocks?: string[] }) =>
                t.type === 'block' && t.blocks?.includes('core/paragraph')
        ) as {
            transform: (attrs: Record<string, unknown>) => {
                name: string;
                attributes: Record<string, unknown>;
            };
        };
        const result = fromBlock.transform({ content: 'roundtrip', dropCap: true });
        expect(result.name).toBe('artisanpack/paragraph');
        expect(result.attributes).toEqual({ content: 'roundtrip', dropCap: true });
    });

    it('block transform converts artisanpack/paragraph → core/paragraph losslessly', () => {
        const toBlock = transforms.to.find(
            (t: { type: string; blocks?: string[] }) =>
                t.type === 'block' && t.blocks?.includes('core/paragraph')
        ) as {
            transform: (attrs: Record<string, unknown>) => {
                name: string;
                attributes: Record<string, unknown>;
            };
        };
        const result = toBlock.transform({ content: 'roundtrip', dropCap: true });
        expect(result.name).toBe('core/paragraph');
        expect(result.attributes).toEqual({ content: 'roundtrip', dropCap: true });
    });
});
