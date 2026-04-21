import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { addFilter, removeAllFilters } from '@wordpress/hooks';
import { afterEach, describe, expect, it, vi } from 'vitest';

// MediaUpload + MediaUploadCheck pull in the full block-editor store at
// import time. We're testing the panel wiring, not the bridge itself, so
// stub both components. Callers of `onSelect` are still exercised through
// the bridge tests (`media-bridge/__tests__/`).
vi.mock('@wordpress/block-editor', () => {
    type MediaUploadRenderProp = (props: { open: () => void }) => JSX.Element;
    type MediaUploadProps = {
        onSelect: (media: unknown) => void;
        render: MediaUploadRenderProp;
    };

    return {
        MediaUploadCheck: ({ children }: { children: React.ReactNode }) => (
            <>{children}</>
        ),
        MediaUpload: ({ onSelect, render: renderProp }: MediaUploadProps) => (
            <div>
                {renderProp({
                    open: () =>
                        onSelect({
                            id: 42,
                            url: 'https://example.test/cover.jpg',
                            alt: 'Cover image',
                        }),
                })}
            </div>
        ),
    };
});

import { DocumentPanels } from '../document-panels';
import { DOCUMENT_PANELS_FILTER } from '../plugin-document-setting-panel';

afterEach(() => {
    // See plugin-document-setting-panel.test.tsx: `removeAllFilters`
    // ignores the namespace arg at runtime but its TS type requires one.
    removeAllFilters(DOCUMENT_PANELS_FILTER, '');
});

function baseProps(overrides = {}) {
    return {
        status: 'draft' as const,
        slug: '',
        onStatusChange: vi.fn(),
        onSlugChange: vi.fn(),
        ...overrides,
    };
}

describe('<DocumentPanels />', () => {
    it('renders the Status & visibility panel with status and slug', () => {
        render(
            <DocumentPanels {...baseProps({ status: 'draft', slug: 'my-post' })} />
        );

        expect(
            screen.getByTestId('ap-visual-editor-document-status')
        ).toHaveValue('draft');
        expect(
            screen.getByTestId('ap-visual-editor-document-slug')
        ).toHaveValue('my-post');
    });

    it('fires onStatusChange with a typed status value', () => {
        const onStatusChange = vi.fn();

        render(
            <DocumentPanels
                {...baseProps({ status: 'draft', onStatusChange })}
            />
        );

        fireEvent.change(screen.getByTestId('ap-visual-editor-document-status'), {
            target: { value: 'published' },
        });

        expect(onStatusChange).toHaveBeenCalledWith('published');
    });

    it('fires onSlugChange when the slug field changes', () => {
        const onSlugChange = vi.fn();

        render(
            <DocumentPanels
                {...baseProps({ slug: '', onSlugChange })}
            />
        );

        fireEvent.change(screen.getByTestId('ap-visual-editor-document-slug'), {
            target: { value: 'updated-slug' },
        });

        expect(onSlugChange).toHaveBeenCalledWith('updated-slug');
    });

    it('renders the Author select only when author options are provided', () => {
        const { rerender } = render(
            <DocumentPanels {...baseProps()} />
        );

        expect(
            screen.queryByTestId('ap-visual-editor-document-author')
        ).not.toBeInTheDocument();

        rerender(
            <DocumentPanels
                {...baseProps({
                    authorOptions: [
                        { value: 1, label: 'Alice' },
                        { value: 2, label: 'Bob' },
                    ],
                    authorId: 1,
                    onAuthorChange: vi.fn(),
                })}
            />
        );

        expect(
            screen.getByTestId('ap-visual-editor-document-author')
        ).toHaveValue('1');
    });

    it('resolves author option value back to its original type on change', () => {
        const onAuthorChange = vi.fn();

        render(
            <DocumentPanels
                {...baseProps({
                    authorOptions: [
                        { value: 1, label: 'Alice' },
                        { value: 2, label: 'Bob' },
                    ],
                    authorId: null,
                    onAuthorChange,
                })}
            />
        );

        fireEvent.change(screen.getByTestId('ap-visual-editor-document-author'), {
            target: { value: '2' },
        });

        expect(onAuthorChange).toHaveBeenCalledWith(2);
    });

    it('clears the author when the empty option is picked', () => {
        const onAuthorChange = vi.fn();

        render(
            <DocumentPanels
                {...baseProps({
                    authorOptions: [{ value: 1, label: 'Alice' }],
                    authorId: 1,
                    onAuthorChange,
                })}
            />
        );

        fireEvent.change(screen.getByTestId('ap-visual-editor-document-author'), {
            target: { value: '' },
        });

        expect(onAuthorChange).toHaveBeenCalledWith(null);
    });

    it('renders the Featured image panel when supports.featuredImage is not false', async () => {
        const user = userEvent.setup();
        const onFeaturedImageChange = vi.fn();

        render(
            <DocumentPanels
                {...baseProps({
                    featuredImage: null,
                    onFeaturedImageChange,
                })}
            />
        );

        // The PanelBody renders a collapsed panel by default; open it first.
        await user.click(screen.getByRole('button', { name: /Featured image/i }));

        // The stubbed MediaUpload immediately calls onSelect when the "Set
        // featured image" button is clicked.
        await user.click(
            screen.getByRole('button', { name: /Set featured image/i })
        );

        expect(onFeaturedImageChange).toHaveBeenCalledWith({
            id: 42,
            url: 'https://example.test/cover.jpg',
            alt: 'Cover image',
        });
    });

    it('hides the Featured image panel when supports.featuredImage is false', () => {
        render(
            <DocumentPanels
                {...baseProps({
                    supports: { featuredImage: false },
                    onFeaturedImageChange: vi.fn(),
                })}
            />
        );

        expect(
            screen.queryByRole('button', { name: /Featured image/i })
        ).not.toBeInTheDocument();
    });

    it('renders the Excerpt panel and forwards changes', async () => {
        const user = userEvent.setup();
        const onExcerptChange = vi.fn();

        render(
            <DocumentPanels
                {...baseProps({ excerpt: '', onExcerptChange })}
            />
        );

        await user.click(screen.getByRole('button', { name: /Excerpt/i }));

        fireEvent.change(
            screen.getByTestId('ap-visual-editor-document-excerpt'),
            { target: { value: 'A short summary.' } }
        );

        expect(onExcerptChange).toHaveBeenCalledWith('A short summary.');
    });

    it('hides the Excerpt panel when supports.excerpt is false', () => {
        render(
            <DocumentPanels
                {...baseProps({
                    supports: { excerpt: false },
                    onExcerptChange: vi.fn(),
                })}
            />
        );

        expect(
            screen.queryByRole('button', { name: /Excerpt/i })
        ).not.toBeInTheDocument();
    });

    it('hides the Discussion panel by default', () => {
        render(
            <DocumentPanels
                {...baseProps({ onCommentsOpenChange: vi.fn() })}
            />
        );

        expect(
            screen.queryByRole('button', { name: /Discussion/i })
        ).not.toBeInTheDocument();
    });

    it('renders the Discussion panel when supports.comments is true', async () => {
        const user = userEvent.setup();
        const onCommentsOpenChange = vi.fn();

        render(
            <DocumentPanels
                {...baseProps({
                    supports: { comments: true },
                    commentsOpen: true,
                    onCommentsOpenChange,
                })}
            />
        );

        await user.click(screen.getByRole('button', { name: /Discussion/i }));

        const toggle = screen.getByTestId(
            'ap-visual-editor-document-comments-open'
        );

        await user.click(toggle);

        expect(onCommentsOpenChange).toHaveBeenCalledWith(false);
    });

    it('renders filter-registered panels alongside the built-ins', () => {
        addFilter(DOCUMENT_PANELS_FILTER, 'test/seo', (panels) => [
            ...panels,
            {
                id: 'test/seo',
                title: 'SEO',
                render: () => (
                    <span data-testid="ap-visual-editor-filter-panel-body">
                        seo content
                    </span>
                ),
            },
        ]);

        render(<DocumentPanels {...baseProps()} />);

        expect(
            screen.getByRole('button', { name: /SEO/i })
        ).toBeInTheDocument();
    });
});
