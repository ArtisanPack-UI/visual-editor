import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import '../src/index';
import { BlockTree } from '../src/BlockTree';
import { GlobalStyles } from '../src/GlobalStyles';
import { Template } from '../src/Template';
import { makeBlock } from './helpers';

describe('GlobalStyles', () => {
    it('renders a <style data-ve-global-styles> tag for non-empty CSS', () => {
        const wrapper = mount(GlobalStyles, {
            props: { css: ':root { --wp--preset--color--brand: #abcdef; }' },
        });

        expect(wrapper.find('style[data-ve-global-styles]').exists()).toBe(true);
        expect(wrapper.html()).toContain('--wp--preset--color--brand');
    });

    it('renders nothing for null, undefined, or empty CSS', () => {
        expect(mount(GlobalStyles, { props: { css: null } }).html()).toBe('');
        expect(mount(GlobalStyles, { props: { css: undefined } }).html()).toBe('');
        expect(mount(GlobalStyles, { props: { css: '' } }).html()).toBe('');
    });
});

describe('BlockTree global-styles wiring', () => {
    it('injects the <style> block when globalStylesCss is provided', () => {
        const wrapper = mount(BlockTree, {
            props: {
                tree: [makeBlock('core/paragraph', { content: 'Hi' })],
                globalStylesCss: ':root { --wp--preset--color--brand: #abcdef; }',
            },
        });

        expect(wrapper.find('style[data-ve-global-styles]').exists()).toBe(true);
        expect(wrapper.html()).toContain('--wp--preset--color--brand');
        expect(wrapper.html()).toContain('Hi');
    });

    it('omits the <style> block when no css is supplied', () => {
        const wrapper = mount(BlockTree, {
            props: {
                tree: [makeBlock('core/paragraph', { content: 'Hi' })],
            },
        });

        expect(wrapper.find('style[data-ve-global-styles]').exists()).toBe(false);
    });
});

describe('Template global-styles wiring', () => {
    const templates = [
        {
            slug: 'index',
            theme: 'artisanpack-base',
            blocks: [makeBlock('core/paragraph', { content: 'Indexed' })],
        },
    ];

    it('emits the <style> block before the wrapper when matched', () => {
        const wrapper = mount(Template, {
            props: {
                slug: 'index',
                theme: 'artisanpack-base',
                templates,
                globalStylesCss: ':root { --wp--preset--color--brand: #abcdef; }',
            },
        });

        expect(wrapper.find('style[data-ve-global-styles]').exists()).toBe(true);
        expect(wrapper.html()).toContain('data-ve-template="index"');
    });

    it('emits the <style> block even when nothing resolves', () => {
        const wrapper = mount(Template, {
            props: {
                slug: 'single-post',
                theme: 'artisanpack-base',
                templates: [],
                globalStylesCss: ':root { --wp--preset--color--brand: #abcdef; }',
            },
        });

        expect(wrapper.find('style[data-ve-global-styles]').exists()).toBe(true);
    });
});
