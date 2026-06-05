/**
 * Tests for the `artisanpack/latest-posts` edit component.
 *
 * The preview is server-rendered, so these assertions focus on the
 * inspector controls and that the edit wires the package's
 * <ServerSideRender> seam to the `artisanpack/latest-posts` block name.
 */

import { describe, it, expect, vi } from 'vitest';
import { render } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
}));

vi.mock('../../../vendor/i18n', () => ({
    TEXT_DOMAIN: 'artisanpack-visual-editor',
}));

vi.mock('../../../editor/server-side-render', () => ({
    ServerSideRender: ({ block, attributes }: { block: string; attributes: Record<string, unknown> }) => (
        <div data-testid="ssr" data-block={block} data-attrs={JSON.stringify(attributes)} />
    ),
}));

vi.mock('@wordpress/components', () => {
    const Panel = ({ title, children }: { title?: string; children?: React.ReactNode }) => (
        <section data-panel={title}>{children}</section>
    );
    const Toggle = ({ label, checked }: { label?: string; checked?: boolean }) => (
        <label data-toggle={label} data-checked={checked ? 'true' : 'false'} />
    );
    return {
        PanelBody: Panel,
        ToggleControl: Toggle,
        RadioControl: ({ label }: { label?: string }) => <div data-radio={label} />,
        RangeControl: ({ label }: { label?: string }) => <div data-range={label} />,
        SelectControl: ({ label }: { label?: string }) => <div data-select={label} />,
    };
});

vi.mock('@wordpress/block-editor', () => ({
    InspectorControls: ({ children }: { children?: React.ReactNode }) => (
        <div data-testid="inspector">{children}</div>
    ),
    useBlockProps: (props?: Record<string, unknown>) => ({ ...props }),
}));

(globalThis as { React?: unknown }).React = require('react');

import LatestPostsEdit from '../edit';

describe('LatestPostsEdit', () => {
    it('previews via ServerSideRender for the artisanpack/latest-posts block', () => {
        const { getByTestId } = render(
            <LatestPostsEdit attributes={{ postsToShow: 5 }} setAttributes={vi.fn()} />
        );
        const ssr = getByTestId('ssr');
        expect(ssr.getAttribute('data-block')).toBe('artisanpack/latest-posts');
    });

    it('mounts the inspector with the sorting + filtering panel', () => {
        const { getByTestId, container } = render(
            <LatestPostsEdit attributes={{}} setAttributes={vi.fn()} />
        );
        expect(getByTestId('inspector')).toBeTruthy();
        expect(container.querySelector('[data-range="Number of items"]')).toBeTruthy();
        expect(container.querySelector('[data-select="Order by"]')).toBeTruthy();
    });

    it('reveals the excerpt-length control only when showing excerpt content', () => {
        const { container } = render(
            <LatestPostsEdit
                attributes={{ displayPostContent: true, displayPostContentRadio: 'excerpt' }}
                setAttributes={vi.fn()}
            />
        );
        expect(container.querySelector('[data-range="Max number of words in excerpt"]')).toBeTruthy();
    });

    it('reveals the columns control only in grid layout', () => {
        const { container } = render(
            <LatestPostsEdit attributes={{ postLayout: 'grid' }} setAttributes={vi.fn()} />
        );
        expect(container.querySelector('[data-range="Columns"]')).toBeTruthy();
    });
});
