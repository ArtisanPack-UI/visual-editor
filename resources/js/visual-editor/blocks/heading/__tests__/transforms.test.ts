/**
 * Transforms tests for `artisanpack/heading`.
 *
 * Round-trip parity with `core/heading` is enforced.
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

describe('heading transforms', () => {
    it('declares from/to arrays', () => {
        expect(Array.isArray(transforms.from)).toBe(true);
        expect(Array.isArray(transforms.to)).toBe(true);
    });

    it('includes a raw transform with the h1-h6 selector', () => {
        const raw = transforms.from.find(
            (t: { type: string }) => t.type === 'raw'
        ) as { type: 'raw'; selector: string };
        expect(raw).toBeDefined();
        expect(raw.selector).toBe('h1,h2,h3,h4,h5,h6');
    });

    it('raw transform infers level from node name', () => {
        const raw = transforms.from.find(
            (t: { type: string }) => t.type === 'raw'
        ) as {
            type: 'raw';
            transform: (node: {
                outerHTML: string;
                nodeName: string;
                style?: { textAlign?: string };
            }) => { name: string; attributes: Record<string, unknown> };
        };
        const result = raw.transform({
            outerHTML: '<h3>Hi</h3>',
            nodeName: 'H3',
        });
        expect(result.name).toBe('artisanpack/heading');
        expect((result.attributes as { level?: number }).level).toBe(3);
    });

    it('includes six prefix transforms (one per heading level)', () => {
        const prefixTransforms = transforms.from.filter(
            (t: { type: string }) => t.type === 'prefix'
        );
        expect(prefixTransforms.length).toBe(6);
    });

    it('includes six enter transforms (/h1 through /h6)', () => {
        const enterTransforms = transforms.from.filter(
            (t: { type: string }) => t.type === 'enter'
        );
        expect(enterTransforms.length).toBe(6);
    });

    it('block transform converts core/heading → artisanpack/heading losslessly', () => {
        const fromBlock = transforms.from.find(
            (t: { type: string; blocks?: string[]; isMultiBlock?: boolean }) =>
                t.type === 'block' &&
                !t.isMultiBlock &&
                t.blocks?.includes('core/heading')
        ) as {
            transform: (attrs: Record<string, unknown>) => {
                name: string;
                attributes: Record<string, unknown>;
            };
        };
        const result = fromBlock.transform({ content: 'Hi', level: 4 });
        expect(result.name).toBe('artisanpack/heading');
        expect(result.attributes).toEqual({ content: 'Hi', level: 4 });
    });

    it('block transform converts artisanpack/heading → core/heading losslessly', () => {
        const toBlock = transforms.to.find(
            (t: { type: string; blocks?: string[]; isMultiBlock?: boolean }) =>
                t.type === 'block' &&
                !t.isMultiBlock &&
                t.blocks?.includes('core/heading')
        ) as {
            transform: (attrs: Record<string, unknown>) => {
                name: string;
                attributes: Record<string, unknown>;
            };
        };
        const result = toBlock.transform({ content: 'Hi', level: 4 });
        expect(result.name).toBe('core/heading');
        expect(result.attributes).toEqual({ content: 'Hi', level: 4 });
    });
});
