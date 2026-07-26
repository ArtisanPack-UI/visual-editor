/**
 * Tests for the artisanpack/template-part edit (#674).
 *
 * Before #674 this edit delegated to `core/template-part` via
 * `createForkedEntityEdit`, which no-ops since I7 (#415) stopped
 * registering core blocks. The replacement wires the block's `slug`
 * + `theme` attributes to the shim's `useEntityBlockEditor` composite-id
 * path so template-part references inside a template mount and render
 * their linked part's blocks inline.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render } from '@testing-library/react';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: () => ({ 'data-testid': 'tp-wrapper' }),
    useInnerBlocksProps: (
        wrapperProps: Record<string, unknown>,
        options: {
            value: unknown;
            templateLock?: unknown;
            renderAppender?: unknown;
        },
    ) => ({
        ...wrapperProps,
        // Surface what the edit passed to useInnerBlocksProps as data
        // attributes so the test can assert against the read-only
        // intent (templateLock, no appender) without mocking a full
        // inner-blocks renderer.
        'data-value-length': Array.isArray(options.value)
            ? String((options.value as unknown[]).length)
            : '0',
        'data-template-lock': String(options.templateLock ?? ''),
        'data-render-appender': String(options.renderAppender ?? ''),
    }),
}));

const useEntityBlockEditor = vi.fn();

vi.mock('../../../vendor/core-data-shim', () => ({
    useEntityBlockEditor: (...args: unknown[]) => useEntityBlockEditor(...args),
}));

import TemplatePartEdit from '../edit';

describe('TemplatePartEdit', () => {
    beforeEach(() => {
        useEntityBlockEditor.mockClear();
    });

    it('composes theme//slug composite id and passes it to the shim', () => {
        useEntityBlockEditor.mockReturnValue([[], vi.fn(), vi.fn()]);

        render(
            <TemplatePartEdit
                attributes={{
                    slug: 'header',
                    theme: 'artisanpack-ui',
                    tagName: 'header',
                }}
            />,
        );

        expect(useEntityBlockEditor).toHaveBeenCalledWith(
            'postType',
            'wp_template_part',
            { id: 'artisanpack-ui//header' },
        );
    });

    it('does not call useEntityBlockEditor when slug or theme is missing', () => {
        // Regression guard for a subtle failure mode: the shim's
        // `useEntityBlockEditor` resolves a missing id through the
        // ambient `EntityProvider` (`options?.id ?? ambientId ?? null`).
        // For a template-part block inside a template, that ambient id
        // is the parent template — so passing `{ id: null }` would
        // mount the template's own tree recursively under the
        // placeholder. Skipping the hook entirely when attributes are
        // incomplete keeps the ambient path from firing.
        useEntityBlockEditor.mockReturnValue([[], vi.fn(), vi.fn()]);

        render(<TemplatePartEdit attributes={{ slug: 'header' }} />);
        render(<TemplatePartEdit attributes={{ theme: 'artisanpack-ui' }} />);
        render(<TemplatePartEdit attributes={{}} />);

        expect(useEntityBlockEditor).not.toHaveBeenCalled();
    });

    it('renders an empty wrapper (no InnerBlocks) when attributes are incomplete', () => {
        useEntityBlockEditor.mockReturnValue([[], vi.fn(), vi.fn()]);

        const { container } = render(
            <TemplatePartEdit attributes={{ slug: 'header', tagName: 'header' }} />,
        );

        // Wrapper renders, but no `data-value-length` attribute (means
        // useInnerBlocksProps wasn't called) — the recursive-ambient
        // hazard from the shim never gets a chance to fire.
        const wrapper = container.querySelector('header');
        expect(wrapper).not.toBeNull();
        expect(wrapper?.getAttribute('data-value-length')).toBeNull();
    });

    it('renders under the tagName the block author picked', () => {
        useEntityBlockEditor.mockReturnValue([[], vi.fn(), vi.fn()]);

        const { container } = render(
            <TemplatePartEdit
                attributes={{ slug: 'footer', theme: 't', tagName: 'footer' }}
            />,
        );

        expect(container.querySelector('footer')).not.toBeNull();
        expect(container.querySelector('div')).toBeNull();
    });

    it('falls back to `div` when tagName is not set', () => {
        useEntityBlockEditor.mockReturnValue([[], vi.fn(), vi.fn()]);

        const { container } = render(
            <TemplatePartEdit attributes={{ slug: 'header', theme: 't' }} />,
        );

        expect(container.querySelector('div')).not.toBeNull();
    });

    it('surfaces the resolved blocks length through useInnerBlocksProps', () => {
        useEntityBlockEditor.mockReturnValue([
            [{ name: 'artisanpack/heading' }, { name: 'artisanpack/paragraph' }],
            vi.fn(),
            vi.fn(),
        ]);

        const { container } = render(
            <TemplatePartEdit
                attributes={{ slug: 'header', theme: 't', tagName: 'header' }}
            />,
        );

        expect(
            container.querySelector('header')?.getAttribute('data-value-length'),
        ).toBe('2');
    });

    it('locks the InnerBlocks surface against structural edits', () => {
        // The shim's `useEntityBlockEditor` returns `noopSetter` for
        // both `onInput` and `onChange` — those callbacks must be
        // passed for controlled `useInnerBlocksProps` to hydrate, but
        // `templateLock: 'all'` prevents structural mutations so the
        // noop setters never see a change they'd need to persist.
        // Save-side plumbing for inline template-part edits is a
        // separate follow-up; users edit template parts through the
        // Template Parts navigator today.
        useEntityBlockEditor.mockReturnValue([
            [{ name: 'artisanpack/heading' }],
            vi.fn(),
            vi.fn(),
        ]);

        const { container } = render(
            <TemplatePartEdit
                attributes={{ slug: 'header', theme: 't', tagName: 'header' }}
            />,
        );

        const wrapper = container.querySelector('header');
        expect(wrapper?.getAttribute('data-template-lock')).toBe('all');
        expect(wrapper?.getAttribute('data-render-appender')).toBe('false');
    });
});
