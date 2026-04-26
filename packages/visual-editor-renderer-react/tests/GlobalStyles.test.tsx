import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import '../src/index';
import { BlockTree } from '../src/BlockTree';
import { GlobalStyles } from '../src/GlobalStyles';
import { Template } from '../src/Template';
import { makeBlock } from './helpers';

describe('GlobalStyles', () => {
    it('renders a <style data-ve-global-styles> tag for non-empty CSS', () => {
        const css = ':root { --wp--preset--color--brand: #abcdef; }';
        const { container } = render(<GlobalStyles css={css} />);

        const style = container.querySelector('style[data-ve-global-styles]');

        expect(style).not.toBeNull();
        expect(style?.innerHTML).toContain('--wp--preset--color--brand');
    });

    it('renders nothing for null, undefined, or empty CSS', () => {
        expect(render(<GlobalStyles css={null} />).container.innerHTML).toBe('');
        expect(render(<GlobalStyles css={undefined} />).container.innerHTML).toBe('');
        expect(render(<GlobalStyles css="" />).container.innerHTML).toBe('');
    });
});

describe('BlockTree global-styles wiring', () => {
    it('injects the <style> block when globalStylesCss is provided', () => {
        const { container } = render(
            <BlockTree
                tree={[makeBlock('core/paragraph', { content: 'Hi' })]}
                globalStylesCss=":root { --wp--preset--color--brand: #abcdef; }"
            />
        );

        const style = container.querySelector('style[data-ve-global-styles]');

        expect(style).not.toBeNull();
        expect(style?.innerHTML).toContain('--wp--preset--color--brand');
        expect(container.innerHTML).toContain('Hi');
    });

    it('omits the <style> block when no css is supplied', () => {
        const { container } = render(
            <BlockTree tree={[makeBlock('core/paragraph', { content: 'Hi' })]} />
        );

        expect(container.querySelector('style[data-ve-global-styles]')).toBeNull();
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
        const { container } = render(
            <Template
                slug="index"
                theme="artisanpack-base"
                templates={templates}
                globalStylesCss=":root { --wp--preset--color--brand: #abcdef; }"
            />
        );

        expect(container.querySelector('style[data-ve-global-styles]')).not.toBeNull();
        expect(container.innerHTML).toContain('data-ve-template="index"');
    });

    it('emits the <style> block even when nothing resolves', () => {
        const { container } = render(
            <Template
                slug="single-post"
                theme="artisanpack-base"
                templates={[]}
                globalStylesCss=":root { --wp--preset--color--brand: #abcdef; }"
            />
        );

        expect(container.querySelector('style[data-ve-global-styles]')).not.toBeNull();
    });
});
