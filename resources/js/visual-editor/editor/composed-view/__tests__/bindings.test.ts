/**
 * Bindings survive the composed view's block pipeline (#622).
 *
 * `core/post-title` and `core/post-author` in the template chrome are
 * expected to show *this* post's title and author, not the template's
 * sample data. Two things have to hold for that:
 *
 *   1. the resolver has to be pointed at the content being edited — that
 *      is the `setBindingsResourceContext` / `BlockContextProvider` wiring,
 *      covered in `toggle-preserves-state.test.tsx`;
 *   2. the `bindings` map has to still be on the block by the time it
 *      reaches the preview.
 *
 * (2) is what this file pins. The chrome goes through two transforms
 * between the endpoint and the canvas — `splitTemplateAroundContentSlot`
 * (which *clones* wrappers onto both sides of the slot) and
 * `hydrateBlocks` — and either could drop an attribute it doesn't know
 * about. A binding lost here fails silently: the block renders the
 * template's placeholder text and looks plausible.
 *
 * `@wordpress/blocks` is mocked, per the convention in `hydrate.test.ts` —
 * the real module cannot be imported under vitest (JSON import attribute).
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { BlockInstance } from '@wordpress/blocks';

let clientIdCounter = 0;

vi.mock('@wordpress/blocks', () => ({
    createBlock: (
        name: string,
        attributes: Record<string, unknown>,
        innerBlocks: BlockInstance[]
    ) => {
        clientIdCounter += 1;

        return {
            clientId: `cid-${clientIdCounter}`,
            name,
            attributes,
            innerBlocks,
            isValid: true,
        };
    },
}));

import { hydrateBlocks } from '../hydrate';
import { splitTemplateAroundContentSlot } from '../split';

const TITLE_BINDING = {
    content: { source: 'artisanpack/entity-field', args: { field: 'title' } },
};
const AUTHOR_BINDING = {
    content: {
        source: 'artisanpack/entity-field',
        args: { field: 'author_name' },
    },
};

function block(
    name: string,
    attributes: Record<string, unknown> = {},
    innerBlocks: BlockInstance[] = []
): BlockInstance {
    return {
        clientId: `${name}-${(clientIdCounter += 1)}`,
        name,
        isValid: true,
        attributes,
        innerBlocks,
    } as unknown as BlockInstance;
}

function findByName(
    blocks: readonly BlockInstance[],
    name: string
): BlockInstance | null {
    for (const candidate of blocks) {
        if (candidate.name === name) {
            return candidate;
        }

        const nested = findByName(candidate.innerBlocks, name);

        if (nested !== null) {
            return nested;
        }
    }

    return null;
}

beforeEach(() => {
    clientIdCounter = 0;
});

describe('composed-view bindings pass-through (#622)', () => {
    it('keeps the title binding on header chrome through split + hydrate', () => {
        const split = splitTemplateAroundContentSlot(
            [
                block('artisanpack/group', { tagName: 'header' }, [
                    block('artisanpack/post-title', {
                        level: 1,
                        bindings: TITLE_BINDING,
                    }),
                ]),
                block('artisanpack/post-content'),
            ],
            'Single Post'
        );

        const title = findByName(
            hydrateBlocks(split.header),
            'artisanpack/post-title'
        );

        expect(title).not.toBeNull();
        expect(title?.attributes).toMatchObject({
            level: 1,
            bindings: TITLE_BINDING,
        });
    });

    it('keeps the author binding on footer chrome through split + hydrate', () => {
        const split = splitTemplateAroundContentSlot(
            [
                block('artisanpack/post-content'),
                block('artisanpack/group', { tagName: 'footer' }, [
                    block('artisanpack/post-author', {
                        bindings: AUTHOR_BINDING,
                    }),
                ]),
            ],
            'Single Post'
        );

        const author = findByName(
            hydrateBlocks(split.footer),
            'artisanpack/post-author'
        );

        expect(author).not.toBeNull();
        expect(author?.attributes).toMatchObject({ bindings: AUTHOR_BINDING });
    });

    it('keeps bindings on both halves when the slot splits a bound wrapper', () => {
        // The wrapper is cloned onto both sides of the slot. Its own
        // attributes — bindings included — have to ride along on both
        // copies, and the bound children either side have to keep theirs.
        const wrapperBinding = {
            style: { source: 'artisanpack/entity-field', args: { field: 'x' } },
        };

        const split = splitTemplateAroundContentSlot(
            [
                block('artisanpack/group', { bindings: wrapperBinding }, [
                    block('artisanpack/post-title', {
                        bindings: TITLE_BINDING,
                    }),
                    block('artisanpack/post-content'),
                    block('artisanpack/post-author', {
                        bindings: AUTHOR_BINDING,
                    }),
                ]),
            ],
            'Single Post'
        );

        const header = hydrateBlocks(split.header);
        const footer = hydrateBlocks(split.footer);

        expect(header[0]?.attributes).toMatchObject({
            bindings: wrapperBinding,
        });
        expect(footer[0]?.attributes).toMatchObject({
            bindings: wrapperBinding,
        });
        expect(
            findByName(header, 'artisanpack/post-title')?.attributes
        ).toMatchObject({ bindings: TITLE_BINDING });
        expect(
            findByName(footer, 'artisanpack/post-author')?.attributes
        ).toMatchObject({ bindings: AUTHOR_BINDING });
    });

    it('leaves an unbound chrome block without a bindings key', () => {
        // The complement of the assertions above: nothing in the pipeline
        // invents a binding, so a plain heading still renders its own
        // static text.
        const split = splitTemplateAroundContentSlot(
            [
                block('artisanpack/heading', { content: 'Static' }),
                block('artisanpack/post-content'),
            ],
            'Single Post'
        );

        const heading = findByName(
            hydrateBlocks(split.header),
            'artisanpack/heading'
        );

        expect(heading?.attributes).not.toHaveProperty('bindings');
    });
});
