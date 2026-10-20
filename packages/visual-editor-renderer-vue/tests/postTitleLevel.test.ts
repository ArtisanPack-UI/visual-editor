import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';

import '../src/index';
import { BlockTree } from '../src/BlockTree';
import { resetBlockRegistry } from '../src/registry';
import { registerCoreBlocks } from '../src/blocks/registerCoreBlocks';
import { makeBlock, normalizeHtml } from './helpers';
import type { Block } from '../src/types';

function renderTitle(level: number): string {
    const wrapper = mount(BlockTree, {
        props: {
            tree: [makeBlock('core/post-title', { level, _resolvedTitle: 'Hello World' })] as Block[],
        },
    });

    return normalizeHtml(wrapper.html());
}

afterEach(() => {
    resetBlockRegistry();
    registerCoreBlocks();
});

// #994 — level 0 is the heading-level dropdown's "Paragraph" option.
describe('Post Title heading level', () => {
    it('renders level 0 as a paragraph', () => {
        expect(renderTitle(0)).toContain('<p class="wp-block-post-title">Hello World</p>');
    });

    it.each([1, 2, 3, 4, 5, 6])('renders level %i as the matching heading', (level) => {
        expect(renderTitle(level)).toContain(
            `<h${level} class="wp-block-post-title">Hello World</h${level}>`
        );
    });

    it('clamps out-of-range levels into h1-h6', () => {
        expect(renderTitle(-3)).toContain('<h1 class="wp-block-post-title">');
        expect(renderTitle(9)).toContain('<h6 class="wp-block-post-title">');
    });
});
