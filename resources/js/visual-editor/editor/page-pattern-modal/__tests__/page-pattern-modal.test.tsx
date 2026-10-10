/**
 * Vitest for the page-pattern-inserter modal (#639).
 *
 * Exercises the presentational surface: grid rendering, category
 * grouping, template selector wiring, dismiss (X / Escape / backdrop),
 * empty-state handling, and pattern selection.
 */

import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@wordpress/blocks', () => ({
    createBlocksFromInnerBlocksTemplate: (template: Array<unknown>) =>
        template.map((entry) => ({
            name: (entry as [string])[0],
            attributes: {},
            innerBlocks: [],
        })),
    parse: (raw: string) => {
        if (raw.includes('core/paragraph')) {
            return [{ name: 'core/paragraph', attributes: {}, innerBlocks: [] }];
        }
        return [];
    },
}));

const PREVIEW_MOCK = vi.fn();

vi.mock('../../../site-editor/patterns/api-client', async () => {
    const actual =
        await vi.importActual<typeof import('../../../site-editor/patterns/api-client')>(
            '../../../site-editor/patterns/api-client'
        );

    return {
        ...actual,
        previewPatterns: (...args: unknown[]) => PREVIEW_MOCK(...args),
    };
});

const observerRoots: Array<Element | Document | null | undefined> = [];

class ImmediateIntersectionObserver {
    constructor(
        private readonly callback: (entries: Array<{ isIntersecting: boolean }>) => void,
        options?: IntersectionObserverInit
    ) {
        observerRoots.push(options?.root);
    }

    observe(): void {
        this.callback([{ isIntersecting: true }]);
    }

    disconnect(): void {}
}

import type { PatternRecord } from '../../../site-editor/patterns/api-client';
import { resetPatternPreviewLoaders } from '../../../site-editor/patterns/pattern-preview-loader';
import { PagePatternModal, type TemplateOption } from '../page-pattern-modal';

function makePattern(overrides: Partial<PatternRecord> = {}): PatternRecord {
    return {
        id: 1,
        slug: 'landing-hero',
        title: { rendered: 'Landing Hero', raw: 'Landing Hero' },
        content: { raw: '', blocks: [] },
        synced: false,
        categories: [ 'page' ],
        status: 'publish',
        type: 'wp_block',
        post_types: null,
        ...overrides,
    };
}

const NOOP_INSERT = vi.fn();
const NOOP_CLOSE = vi.fn();

beforeEach(() => {
    NOOP_INSERT.mockReset();
    NOOP_CLOSE.mockReset();
    PREVIEW_MOCK.mockReset();
    resetPatternPreviewLoaders();
    observerRoots.length = 0;
});

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('<PagePatternModal />', () => {
    it('does not render when open is false', () => {
        render(
            <PagePatternModal
                open={false}
                onClose={NOOP_CLOSE}
                patterns={[]}
                onInsertBlocks={NOOP_INSERT}
            />
        );

        expect(screen.queryByTestId('ap-page-pattern-modal')).not.toBeInTheDocument();
    });

    it('renders the pattern grid grouped by category when open', () => {
        render(
            <PagePatternModal
                open={true}
                onClose={NOOP_CLOSE}
                patterns={[
                    makePattern({ id: 1, slug: 'a', categories: ['page'] }),
                    makePattern({ id: 2, slug: 'b', categories: ['post'] }),
                ]}
                onInsertBlocks={NOOP_INSERT}
            />
        );

        expect(screen.getByTestId('ap-page-pattern-modal-category-page')).toBeInTheDocument();
        expect(screen.getByTestId('ap-page-pattern-modal-category-post')).toBeInTheDocument();
        expect(screen.getByTestId('ap-page-pattern-modal-pattern-a')).toBeInTheDocument();
        expect(screen.getByTestId('ap-page-pattern-modal-pattern-b')).toBeInTheDocument();
    });

    it('renders a multi-category pattern exactly once, in its primary category', () => {
        // Modal is a starter picker, not a taxonomy browser — rendering
        // the same card in every category would duplicate React keys /
        // `data-testid` and confuse users into thinking the pattern is
        // multiple different starters.
        render(
            <PagePatternModal
                open={true}
                onClose={NOOP_CLOSE}
                patterns={[
                    makePattern({
                        id: 42,
                        slug: 'multi-cat',
                        categories: [ 'featured', 'page' ],
                    }),
                ]}
                onInsertBlocks={NOOP_INSERT}
            />
        );

        expect(screen.getAllByTestId('ap-page-pattern-modal-pattern-multi-cat')).toHaveLength(1);
        expect(screen.getByTestId('ap-page-pattern-modal-category-featured')).toBeInTheDocument();
        expect(
            screen.queryByTestId('ap-page-pattern-modal-category-page')
        ).not.toBeInTheDocument();
    });

    it('surfaces the empty-state message when no patterns are provided', () => {
        render(
            <PagePatternModal
                open={true}
                onClose={NOOP_CLOSE}
                patterns={[]}
                onInsertBlocks={NOOP_INSERT}
            />
        );

        expect(screen.getByTestId('ap-page-pattern-modal-empty')).toBeInTheDocument();
    });

    it('renders a loading state when `loading` is true', () => {
        render(
            <PagePatternModal
                open={true}
                onClose={NOOP_CLOSE}
                patterns={[]}
                onInsertBlocks={NOOP_INSERT}
                loading={true}
            />
        );

        expect(screen.getByTestId('ap-page-pattern-modal-loading')).toBeInTheDocument();
    });

    it('surfaces an error message when provided', () => {
        render(
            <PagePatternModal
                open={true}
                onClose={NOOP_CLOSE}
                patterns={[]}
                onInsertBlocks={NOOP_INSERT}
                errorMessage="Something went wrong."
            />
        );

        expect(screen.getByTestId('ap-page-pattern-modal-error')).toHaveTextContent(
            'Something went wrong.'
        );
    });

    it('fires onClose when the X button is clicked', async () => {
        const user = userEvent.setup();

        render(
            <PagePatternModal
                open={true}
                onClose={NOOP_CLOSE}
                patterns={[makePattern()]}
                onInsertBlocks={NOOP_INSERT}
            />
        );

        await user.click(screen.getByTestId('ap-page-pattern-modal-close'));

        expect(NOOP_CLOSE).toHaveBeenCalledTimes(1);
    });

    it('fires onClose when Escape is pressed', async () => {
        const user = userEvent.setup();

        render(
            <PagePatternModal
                open={true}
                onClose={NOOP_CLOSE}
                patterns={[makePattern()]}
                onInsertBlocks={NOOP_INSERT}
            />
        );

        await user.keyboard('{Escape}');

        await waitFor(() => {
            expect(NOOP_CLOSE).toHaveBeenCalled();
        });
    });

    it('fires onClose when the backdrop is clicked', async () => {
        const user = userEvent.setup();

        render(
            <PagePatternModal
                open={true}
                onClose={NOOP_CLOSE}
                patterns={[makePattern()]}
                onInsertBlocks={NOOP_INSERT}
            />
        );

        await user.click(screen.getByTestId('ap-page-pattern-modal-backdrop'));

        expect(NOOP_CLOSE).toHaveBeenCalled();
    });

    it('does not fire onClose when the dialog interior is clicked', async () => {
        const user = userEvent.setup();

        render(
            <PagePatternModal
                open={true}
                onClose={NOOP_CLOSE}
                patterns={[makePattern()]}
                onInsertBlocks={NOOP_INSERT}
            />
        );

        // Click the modal container (not the backdrop). Clicks on the
        // dialog interior must not trigger the dismiss path — otherwise
        // the user can't interact with the pattern grid.
        await user.click(screen.getByTestId('ap-page-pattern-modal'));

        expect(NOOP_CLOSE).not.toHaveBeenCalled();
    });

    it('parses the pattern content and hands the block tree to onInsertBlocks', async () => {
        const user = userEvent.setup();

        render(
            <PagePatternModal
                open={true}
                onClose={NOOP_CLOSE}
                patterns={[
                    makePattern({
                        slug: 'hero',
                        content: { raw: '<!-- wp:core/paragraph /-->', blocks: [] },
                    }),
                ]}
                onInsertBlocks={NOOP_INSERT}
            />
        );

        await user.click(screen.getByTestId('ap-page-pattern-modal-pattern-hero'));

        expect(NOOP_INSERT).toHaveBeenCalledTimes(1);
        expect(NOOP_INSERT.mock.calls[0][0]).toEqual([
            { name: 'core/paragraph', attributes: {}, innerBlocks: [] },
        ]);
        // Selecting a pattern also closes the modal so the user lands
        // back in the canvas.
        expect(NOOP_CLOSE).toHaveBeenCalled();
    });

    it('produces an empty block tree for a Blank pattern (no raw, no blocks)', async () => {
        const user = userEvent.setup();

        render(
            <PagePatternModal
                open={true}
                onClose={NOOP_CLOSE}
                patterns={[
                    makePattern({
                        slug: 'page/blank',
                        title: { rendered: 'Blank', raw: 'Blank' },
                        content: { raw: '', blocks: [] },
                    }),
                ]}
                onInsertBlocks={NOOP_INSERT}
            />
        );

        await user.click(screen.getByTestId('ap-page-pattern-modal-pattern-page/blank'));

        expect(NOOP_INSERT).toHaveBeenCalledWith([]);
        expect(NOOP_CLOSE).toHaveBeenCalled();
    });

    it('renders the template selector when both options and handler are supplied', async () => {
        const user = userEvent.setup();
        const onTemplateChange = vi.fn();

        const options: TemplateOption[] = [
            { slug: '', label: 'Default template' },
            { slug: 'wide', label: 'Wide', source: 'Theme' },
        ];

        render(
            <PagePatternModal
                open={true}
                onClose={NOOP_CLOSE}
                patterns={[makePattern()]}
                onInsertBlocks={NOOP_INSERT}
                templateOptions={options}
                initialTemplate=""
                onTemplateChange={onTemplateChange}
            />
        );

        const select = screen.getByTestId('ap-page-pattern-modal-template-select');

        expect(select).toBeInTheDocument();
        expect(select).toHaveValue('');

        await user.selectOptions(select, 'wide');

        expect(onTemplateChange).toHaveBeenCalledWith('wide');
    });

    it('suppresses the template selector when no options are supplied', () => {
        render(
            <PagePatternModal
                open={true}
                onClose={NOOP_CLOSE}
                patterns={[makePattern()]}
                onInsertBlocks={NOOP_INSERT}
                templateOptions={[]}
                onTemplateChange={vi.fn()}
            />
        );

        expect(
            screen.queryByTestId('ap-page-pattern-modal-template-select')
        ).not.toBeInTheDocument();
    });

    it('suppresses the template selector when no handler is supplied even if options exist', () => {
        // Options without a setter means the caller has no persistence
        // path — rendering the select would be a footgun where user
        // clicks silently vanish. Suppress the row entirely.
        render(
            <PagePatternModal
                open={true}
                onClose={NOOP_CLOSE}
                patterns={[makePattern()]}
                onInsertBlocks={NOOP_INSERT}
                templateOptions={[{ slug: '', label: 'Default' }]}
                // onTemplateChange intentionally omitted.
            />
        );

        expect(
            screen.queryByTestId('ap-page-pattern-modal-template-select')
        ).not.toBeInTheDocument();
    });

    it('renders a front-end preview on each card when given an API base (#832)', async () => {
        vi.stubGlobal('IntersectionObserver', ImmediateIntersectionObserver);
        PREVIEW_MOCK.mockResolvedValue({
            styles: '',
            patterns: { '1': { html: '<p>Rendered landing hero</p>' } },
        });

        render(
            <PagePatternModal
                open
                onClose={NOOP_CLOSE}
                patterns={[
                    makePattern({
                        content: { raw: '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->', blocks: [] },
                    }),
                ]}
                onInsertBlocks={NOOP_INSERT}
                apiBase="/visual-editor/api"
            />
        );

        const preview = screen.getByTestId('ap-page-pattern-modal-pattern-preview-landing-hero');

        await waitFor(() => expect(preview.querySelector('iframe')).not.toBeNull());
        expect(preview).toHaveAttribute('aria-hidden', 'true');
        expect(PREVIEW_MOCK).toHaveBeenCalledWith(
            { apiBase: '/visual-editor/api' },
            ['1'],
            expect.objectContaining({ signal: expect.anything() })
        );
    });

    it('keeps the block-name tree on cards when no API base is given (#832)', () => {
        vi.stubGlobal('IntersectionObserver', ImmediateIntersectionObserver);

        render(
            <PagePatternModal
                open
                onClose={NOOP_CLOSE}
                patterns={[
                    makePattern({
                        content: { raw: '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->', blocks: [] },
                    }),
                ]}
                onInsertBlocks={NOOP_INSERT}
            />
        );

        const preview = screen.getByTestId('ap-page-pattern-modal-pattern-preview-landing-hero');

        expect(preview.querySelector('[data-preview-state="fallback"]')).not.toBeNull();
        expect(preview.querySelector('iframe')).toBeNull();
        expect(PREVIEW_MOCK).not.toHaveBeenCalled();
    });

    it('keeps the preview iframe out of the card button and observes the modal body (FE-3 · FE-10b)', async () => {
        vi.stubGlobal('IntersectionObserver', ImmediateIntersectionObserver);
        PREVIEW_MOCK.mockResolvedValue({
            styles: '',
            patterns: { '1': { html: '<p>Rendered landing hero</p>' } },
        });

        render(
            <PagePatternModal
                open
                onClose={NOOP_CLOSE}
                patterns={[
                    makePattern({
                        content: { raw: '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->', blocks: [] },
                    }),
                ]}
                onInsertBlocks={NOOP_INSERT}
                apiBase="/visual-editor/api"
            />
        );

        const preview = screen.getByTestId('ap-page-pattern-modal-pattern-preview-landing-hero');
        const button = screen.getByTestId('ap-page-pattern-modal-pattern-landing-hero');

        await waitFor(() => expect(preview.querySelector('iframe')).not.toBeNull());

        expect(button.tagName).toBe('BUTTON');
        expect(button.querySelector('iframe, div, p')).toBeNull();
        expect(preview.closest('button')).toBeNull();
        expect(button).toHaveAccessibleName('Landing Hero landing-hero');
        expect(observerRoots[0]).toBe(
            document.querySelector('.ap-page-pattern-modal__body')
        );
    });

    it('still selects a pattern from the keyboard (FE-10b)', async () => {
        const user = userEvent.setup();

        render(
            <PagePatternModal
                open
                onClose={NOOP_CLOSE}
                patterns={[
                    makePattern({
                        slug: 'hero',
                        content: { raw: '<!-- wp:core/paragraph /-->', blocks: [] },
                    }),
                ]}
                onInsertBlocks={NOOP_INSERT}
            />
        );

        screen.getByTestId('ap-page-pattern-modal-pattern-hero').focus();
        await user.keyboard('{Enter}');

        expect(NOOP_INSERT).toHaveBeenCalledTimes(1);
    });
});
