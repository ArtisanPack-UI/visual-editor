/**
 * Transforms tests for `artisanpack/buttons`.
 */

import { describe, it, expect, vi } from 'vitest';

vi.mock('@wordpress/blocks', () => ({
    createBlock: (
        name: string,
        attributes?: Record<string, unknown>,
        innerBlocks?: unknown[]
    ) => ({
        name,
        attributes: attributes ?? {},
        innerBlocks: innerBlocks ?? [],
    }),
}));

import transforms from '../transforms';

describe('artisanpack/buttons transforms', () => {
    it('declares both directions', () => {
        expect(Array.isArray(transforms.from)).toBe(true);
        expect(Array.isArray(transforms.to)).toBe(true);
    });

    it('groups multi-selected buttons (artisanpack or core) into an artisanpack/buttons block', () => {
        const buttonGrouper = transforms.from.find(
            (t) =>
                t.type === 'block' &&
                Array.isArray((t as { blocks?: string[] }).blocks) &&
                (t as { blocks: string[] }).blocks.includes(
                    'artisanpack/button'
                )
        ) as {
            transform: (buttons: Array<Record<string, unknown>>) => {
                name: string;
                innerBlocks: Array<{ name: string }>;
            };
        };
        expect(buttonGrouper).toBeDefined();
        const block = buttonGrouper.transform([{ text: 'A' }, { text: 'B' }]);
        expect(block.name).toBe('artisanpack/buttons');
        expect(block.innerBlocks).toHaveLength(2);
        expect(block.innerBlocks[0].name).toBe('artisanpack/button');
    });

    it('converts core/buttons → artisanpack/buttons (from)', () => {
        const blockFrom = transforms.from.find(
            (t) =>
                t.type === 'block' &&
                Array.isArray((t as { blocks?: string[] }).blocks) &&
                (t as { blocks: string[] }).blocks.includes('core/buttons')
        ) as {
            transform: (
                attrs: Record<string, unknown>,
                inner: unknown[]
            ) => { name: string };
        };
        expect(blockFrom).toBeDefined();
        const block = blockFrom.transform({}, []);
        expect(block.name).toBe('artisanpack/buttons');
    });

    it('converts artisanpack/buttons → core/buttons (to)', () => {
        const blockTo = transforms.to.find(
            (t) =>
                t.type === 'block' &&
                Array.isArray((t as { blocks?: string[] }).blocks) &&
                (t as { blocks: string[] }).blocks.includes('core/buttons')
        ) as {
            transform: (
                attrs: Record<string, unknown>,
                inner: unknown[]
            ) => { name: string };
        };
        expect(blockTo).toBeDefined();
        const block = blockTo.transform({}, []);
        expect(block.name).toBe('core/buttons');
    });

    it('paragraph→buttons matcher rejects long text or multi-link paragraphs', () => {
        const paragraphMatcher = transforms.from.find(
            (t) =>
                t.type === 'block' &&
                Array.isArray((t as { blocks?: string[] }).blocks) &&
                (t as { blocks: string[] }).blocks.includes('core/paragraph')
        ) as {
            isMatch: (paragraphs: Array<Record<string, unknown>>) => boolean;
        };
        expect(paragraphMatcher).toBeDefined();
        expect(paragraphMatcher.isMatch([{ content: 'Short link' }])).toBe(
            true
        );
        expect(
            paragraphMatcher.isMatch([
                { content: 'x'.repeat(40) },
            ])
        ).toBe(false);
        expect(
            paragraphMatcher.isMatch([
                { content: '<a href="x">a</a><a href="y">b</a>' },
            ])
        ).toBe(false);
    });
});
