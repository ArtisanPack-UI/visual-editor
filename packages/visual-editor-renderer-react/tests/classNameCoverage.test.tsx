/**
 * Registry-wide `className` probe (M-7). The responsive (`ve-r-*`) and
 * effects (`ve-fx-*`) scopes ride on `attributes.className`, so a block
 * that drops `className` silently drops their CSS too. Every registered
 * React and Vue block must print it, as Blade's `wrapperAttrs()` does.
 */

import { createSSRApp, h as vueH } from 'vue';
import { renderToString as vueRenderToString } from '@vue/server-renderer';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';

import '../src/index';
import { BlockTree as ReactBlockTree } from '../src/BlockTree';
import { getRegisteredBlockNames as reactBlockNames } from '../src/registry';
import '../../visual-editor-renderer-vue/src/index';
import { BlockTree as VueBlockTree } from '../../visual-editor-renderer-vue/src/BlockTree';
import { getRegisteredBlockNames as vueBlockNames } from '../../visual-editor-renderer-vue/src/registry';
import type { Block } from '../src/types';

/**
 * Blocks with no wrapper element of their own, and why. Blade renders
 * them the same way.
 */
const NO_WRAPPER: Readonly<Record<string, string>> = {
    'artisanpack/post-variant':
        'A template override the query inliner strips; if one reaches a renderer it passes its inner blocks through without a wrapper (see post-variant.blade.php).',
};

/**
 * Enough resolved data for every context-driven block to render its
 * wrapper (most render nothing without their `_resolved*` stamps).
 */
const PROBE_ATTRIBUTES: Record<string, unknown> = {
    className: 'probe-x',
    content: 'Text',
    url: 'https://example.com/a.png',
    text: 'Text',
    label: 'Label',
    value: 'Value',
    title: 'Title',
    _resolvedContent: '<p>Content</p>',
    _resolvedTitle: 'Title',
    _resolvedEditLinkUrl: 'https://example.com/edit',
    _resolvedReplyLinkUrl: 'https://example.com/reply',
    _resolvedHasAdjacent: true,
    _resolvedNextPageUrl: 'https://example.com/next',
    _resolvedPreviousPageUrl: 'https://example.com/previous',
    _resolvedPageNumbers: [
        { number: 1, url: 'https://example.com/page/1' },
        { number: 2, url: 'https://example.com/page/2' },
    ],
    _resolvedQueryTitle: 'Query title',
    _resolvedHasPost: true,
    _resolvedItems: 1,
    _resolvedActive: true,
    _resolvedReadingTime: 1,
};

function probeTree(name: string): Block[] {
    return [
        {
            clientId: 'probe',
            name,
            attributes: PROBE_ATTRIBUTES,
            innerBlocks: [{ clientId: 'probe-child', name: 'core/paragraph', attributes: { content: 'Child' }, innerBlocks: [] }],
        },
    ];
}

describe.each([
    ['react', reactBlockNames, async (tree: Block[]) => renderToStaticMarkup(createElement(ReactBlockTree, { tree }))],
    [
        'vue',
        vueBlockNames,
        async (tree: Block[]) => vueRenderToString(createSSRApp({ render: () => vueH(VueBlockTree, { tree }) })),
    ],
])('%s className coverage', (_name, blockNames, render) => {
    const names = blockNames()
        .filter((name) => !(name in NO_WRAPPER))
        .sort();

    it('registers the blocks it probes', () => {
        expect(names.length).toBeGreaterThan(100);
    });

    it.each(names)('%s prints its className', async (name) => {
        const html = (await render(probeTree(name))).replace(/<!--[\s\S]*?-->/g, '');

        expect(html).not.toBe('');
        expect(html).toMatch(/class="[^"]*\bprobe-x\b[^"]*"/);
    });

    it('keeps every skip-list entry registered', () => {
        expect(Object.keys(NO_WRAPPER).filter((name) => !blockNames().includes(name))).toEqual([]);
    });
});
