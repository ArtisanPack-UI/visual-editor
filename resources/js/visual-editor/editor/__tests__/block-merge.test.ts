/**
 * Backspace / Delete merging, run through the real `mergeBlocks` action
 * against the real block-editor store and the forked block modules (#1043).
 */

import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { join, resolve } from 'node:path';

import { beforeAll, beforeEach, describe, expect, it } from 'vitest';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { createBlock, getBlockType } from '@wordpress/blocks';
import type { BlockInstance } from '@wordpress/blocks';
import { createRegistry } from '@wordpress/data';

import { discoverAndRegisterCustomBlocks } from '../custom-blocks';
import { registerForkedCoreBlocks } from '../register-forked-cores';

const UPSTREAM_ROOT = resolve(
    process.cwd(),
    'node_modules/@wordpress/block-library/src'
);
const BLOCKS_ROOT = resolve(process.cwd(), 'resources/js/visual-editor/blocks');

/**
 * Every fork in the bundled block set, with the upstream directory it came
 * from.
 */
function forkedBlocks(): Array<{ fork: string; upstream: string }> {
    return readdirSync(BLOCKS_ROOT).flatMap((fork: string) => {
        const statePath = join(BLOCKS_ROOT, fork, 'upstream-state.json');

        if (!existsSync(statePath)) {
            return [];
        }

        const subpath = JSON.parse(readFileSync(statePath, 'utf8')).upstream
            ?.subpath as string | undefined;

        return subpath ? [{ fork, upstream: subpath }] : [];
    });
}

function upstreamDefinesMerge(upstream: string): boolean {
    const entrypoint = join(UPSTREAM_ROOT, upstream, 'index.js');

    return (
        existsSync(entrypoint) &&
        /^\tmerge\s*[(:]/m.test(readFileSync(entrypoint, 'utf8'))
    );
}

describe('block merging', () => {
    let registry: ReturnType<typeof createRegistry>;

    beforeAll(() => {
        registerForkedCoreBlocks();
        discoverAndRegisterCustomBlocks();
    });

    beforeEach(() => {
        registry = createRegistry();
        registry.register(blockEditorStore);
    });

    function syncListSettings(
        dispatch: ReturnType<ReturnType<typeof createRegistry>['dispatch']>,
        blocks: ReadonlyArray<ReturnType<typeof createBlock>>
    ): void {
        for (const block of blocks) {
            if (block.name === 'artisanpack/list') {
                dispatch.updateBlockListSettings(block.clientId, {});
            }
        }
    }

    function merge(
        first: ReturnType<typeof createBlock>,
        second: ReturnType<typeof createBlock>,
        selection: { clientId: string; attributeKey: string; offset: number },
        wrapper?: string
    ) {
        const dispatch = registry.dispatch(blockEditorStore);

        if (wrapper) {
            // `artisanpack/list-item` and `artisanpack/button` are parent-locked,
            // and a parent with no list settings refuses inserts. InnerBlocks
            // sets them on mount in the real editor.
            const parent = createBlock(wrapper, {}, [first, second]);

            dispatch.resetBlocks([parent]);
            dispatch.updateBlockListSettings(parent.clientId, {});
        } else {
            dispatch.resetBlocks([first, second]);
        }

        syncListSettings(dispatch, [first, second]);

        dispatch.selectionChange(
            selection.clientId,
            selection.attributeKey,
            selection.offset,
            selection.offset
        );
        dispatch.mergeBlocks(first.clientId, second.clientId);

        const select = registry.select(blockEditorStore);
        const rootClientId = wrapper
            ? select.getBlockRootClientId(first.clientId) ?? undefined
            : undefined;

        return {
            blocks: select.getBlocks(rootClientId),
            start: select.getSelectionStart(),
        };
    }

    it('merges two paragraphs with the cursor at the join', () => {
        const first = createBlock('artisanpack/paragraph', { content: 'Hello' });
        const second = createBlock('artisanpack/paragraph', { content: 'World' });

        const { blocks, start } = merge(first, second, {
            clientId: second.clientId,
            attributeKey: 'content',
            offset: 0,
        });

        expect(blocks).toHaveLength(1);
        expect(blocks[0].clientId).toBe(first.clientId);
        expect(String(blocks[0].attributes.content)).toBe('HelloWorld');
        expect(start).toMatchObject({
            clientId: first.clientId,
            attributeKey: 'content',
            offset: 5,
        });
    });

    it('merges into a bound block, since bindings live in a sidecar the store ignores', () => {
        // Documents why `withBindingsPanel` guards `mergeBlocks` (#1043):
        // upstream's merge only checks `metadata.bindings`, so the store
        // appends the text to the hidden fallback behind this binding.
        const first = createBlock('artisanpack/heading', { content: 'Bound' });
        const second = createBlock('artisanpack/paragraph', { content: 'Important note' });

        first.attributes.bindings = { content: { source: 'post_core', args: { key: 'title' } } };

        const { blocks } = merge(first, second, {
            clientId: second.clientId,
            attributeKey: 'content',
            offset: 0,
        });

        expect(blocks).toHaveLength(1);
        expect(String(blocks[0].attributes.content)).toBe('BoundImportant note');
    });

    it('keeps the cursor at the join on a forward merge', () => {
        const first = createBlock('artisanpack/paragraph', { content: 'Hello' });
        const second = createBlock('artisanpack/paragraph', { content: 'World' });

        const { blocks, start } = merge(first, second, {
            clientId: first.clientId,
            attributeKey: 'content',
            offset: 5,
        });

        expect(String(blocks[0].attributes.content)).toBe('HelloWorld');
        expect(start).toMatchObject({ clientId: first.clientId, offset: 5 });
    });

    it('keeps inline formatting and links from both blocks', () => {
        const first = createBlock('artisanpack/paragraph', {
            content: 'One <strong>bold</strong>',
        });
        const second = createBlock('artisanpack/paragraph', {
            content: '<a href="https://example.com">link</a> <em>two</em>',
        });

        const { blocks, start } = merge(first, second, {
            clientId: second.clientId,
            attributeKey: 'content',
            offset: 0,
        });

        expect(String(blocks[0].attributes.content)).toBe(
            'One <strong>bold</strong><a href="https://example.com">link</a> <em>two</em>'
        );
        expect(start).toMatchObject({ offset: 8 });
    });

    it.each([
        ['artisanpack/heading', 'artisanpack/paragraph', 'content', 'FirstSecond'],
        ['artisanpack/paragraph', 'artisanpack/heading', 'content', 'FirstSecond'],
        ['artisanpack/code', 'artisanpack/paragraph', 'content', 'First\n\nSecond'],
        ['artisanpack/paragraph', 'artisanpack/code', 'content', 'FirstSecond'],
        ['artisanpack/verse', 'artisanpack/paragraph', 'content', 'First\n\nSecond'],
        ['artisanpack/paragraph', 'artisanpack/verse', 'content', 'FirstSecond'],
        ['artisanpack/preformatted', 'artisanpack/paragraph', 'content', 'First\n\nSecond'],
        ['artisanpack/paragraph', 'artisanpack/preformatted', 'content', 'FirstSecond'],
        ['artisanpack/code', 'artisanpack/preformatted', 'content', 'First\n\nSecond'],
        ['artisanpack/verse', 'artisanpack/preformatted', 'content', 'First\n\nSecond'],
        ['artisanpack/preformatted', 'artisanpack/code', 'content', 'First\n\nSecond'],
        ['artisanpack/preformatted', 'artisanpack/verse', 'content', 'First\n\nSecond'],
    ])('merges %s followed by %s', (firstName, secondName, key, expected) => {
        const first = createBlock(firstName, { content: 'First' });
        const second = createBlock(secondName, { content: 'Second' });

        const { blocks } = merge(first, second, {
            clientId: second.clientId,
            attributeKey: key,
            offset: 0,
        });

        expect(blocks).toHaveLength(1);
        expect(blocks[0].name).toBe(firstName);
        expect(String(blocks[0].attributes[key]).replace(/<br>/g, '\n')).toBe(
            expected
        );
    });

    it('merges a pullquote into a paragraph', () => {
        const first = createBlock('artisanpack/paragraph', { content: 'First' });
        const second = createBlock('artisanpack/pullquote', { value: 'Second' });

        const { blocks } = merge(first, second, {
            clientId: first.clientId,
            attributeKey: 'content',
            offset: 5,
        });

        expect(blocks[0].name).toBe('artisanpack/paragraph');
        expect(String(blocks[0].attributes.content)).toBe('FirstSecond');
    });

    it('merges two buttons, keeping the first button\'s link', () => {
        const first = createBlock('artisanpack/button', {
            text: 'Read',
            url: 'https://example.com',
        });
        const second = createBlock('artisanpack/button', { text: 'More' });

        const { blocks, start } = merge(first, second, {
            clientId: second.clientId,
            attributeKey: 'text',
            offset: 0,
        }, 'artisanpack/buttons');

        expect(blocks).toHaveLength(1);
        expect(String(blocks[0].attributes.text)).toBe('ReadMore');
        expect(blocks[0].attributes.url).toBe('https://example.com');
        expect(start).toMatchObject({ attributeKey: 'text', offset: 4 });
    });

    it('merges two list items', () => {
        const first = createBlock('artisanpack/list-item', { content: 'One' });
        const second = createBlock('artisanpack/list-item', { content: 'Two' });

        const { blocks } = merge(first, second, {
            clientId: second.clientId,
            attributeKey: 'content',
            offset: 0,
        }, 'artisanpack/list');

        expect(blocks).toHaveLength(1);
        expect(String(blocks[0].attributes.content)).toBe('OneTwo');
    });

    it('merges a paragraph into a list as a new item', () => {
        const first = createBlock('artisanpack/list', {}, [
            createBlock('artisanpack/list-item', { content: 'One' }),
        ]);
        const second = createBlock('artisanpack/paragraph', { content: 'Two' });

        const { blocks } = merge(first, second, {
            clientId: second.clientId,
            attributeKey: 'content',
            offset: 0,
        });

        expect(blocks).toHaveLength(1);
        expect(blocks[0].name).toBe('artisanpack/list');
        expect(
            blocks[0].innerBlocks.map((block: BlockInstance) =>
                String(block.attributes.content)
            )
        ).toEqual(['One', 'Two']);
    });

    it('merges two navigation links through the upstream block', () => {
        const first = createBlock('core/navigation-link', {
            label: 'Read',
            url: 'https://example.com',
        });
        const second = createBlock('core/navigation-link', { label: 'More' });

        const { blocks } = merge(first, second, {
            clientId: second.clientId,
            attributeKey: 'label',
            offset: 0,
        }, 'core/navigation');

        expect(blocks).toHaveLength(1);
        expect(String(blocks[0].attributes.label)).toBe('ReadMore');
        expect(blocks[0].attributes.url).toBe('https://example.com');
    });

    it('registers merge on every fork of a block that defines it upstream', () => {
        const forks = forkedBlocks().filter(({ upstream }) =>
            upstreamDefinesMerge(upstream)
        );

        expect(forks.map(({ fork }) => fork).sort()).toEqual([
            'button',
            'code',
            'heading',
            'list-item',
            'paragraph',
            'preformatted',
            'verse',
        ]);

        for (const { fork } of forks) {
            expect(
                getBlockType(`artisanpack/${fork}`)?.merge,
                `artisanpack/${fork} dropped upstream's merge`
            ).toBeTypeOf('function');
        }
    });
});
