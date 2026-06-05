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

    describe('post-only Categories & tags panel', () => {
        it('hides the panel when documentType is not "post"', () => {
            render(
                <DocumentPanels
                    {...baseProps({
                        documentType: 'page',
                        onCategoriesChange: vi.fn(),
                        onTagsChange: vi.fn(),
                    })}
                />
            );

            expect(
                screen.queryByRole('button', { name: /Categories & Tags/i })
            ).not.toBeInTheDocument();
        });

        it('renders both inputs when documentType is "post"', async () => {
            const user = userEvent.setup();

            render(
                <DocumentPanels
                    {...baseProps({
                        documentType: 'post',
                        categories: [1, 4],
                        tags: [7],
                        onCategoriesChange: vi.fn(),
                        onTagsChange: vi.fn(),
                    })}
                />
            );

            await user.click(
                screen.getByRole('button', { name: /Categories & Tags/i })
            );

            expect(
                screen.getByTestId('ap-visual-editor-document-categories')
            ).toHaveValue('1, 4');
            expect(
                screen.getByTestId('ap-visual-editor-document-tags')
            ).toHaveValue('7');
        });

        it('parses comma-separated input into a deduplicated id list', async () => {
            const onCategoriesChange = vi.fn();
            const user = userEvent.setup();

            render(
                <DocumentPanels
                    {...baseProps({
                        documentType: 'post',
                        categories: [],
                        tags: [],
                        onCategoriesChange,
                        onTagsChange: vi.fn(),
                    })}
                />
            );

            await user.click(
                screen.getByRole('button', { name: /Categories & Tags/i })
            );

            const input = screen.getByTestId(
                'ap-visual-editor-document-categories'
            );

            // userEvent.type fires onChange per keystroke; the panel
            // parses the *current* input each time, so the last call
            // is the only one we need to assert against.
            fireEvent.change(input, { target: { value: '1, 4, 4, abc, 9 ' } });

            expect(onCategoriesChange).toHaveBeenLastCalledWith([1, 4, 9]);
        });

        it('rejects truncatable tokens like "1.5" and "12abc" outright', async () => {
            const onCategoriesChange = vi.fn();
            const user = userEvent.setup();

            render(
                <DocumentPanels
                    {...baseProps({
                        documentType: 'post',
                        categories: [],
                        tags: [],
                        onCategoriesChange,
                        onTagsChange: vi.fn(),
                    })}
                />
            );

            await user.click(
                screen.getByRole('button', { name: /Categories & Tags/i })
            );

            const input = screen.getByTestId(
                'ap-visual-editor-document-categories'
            );

            // `parseInt('1.5', 10) === 1` and `parseInt('12abc', 10) === 12`
            // would silently promote both into the saved id list. The
            // regex pre-check rejects them outright; only the bare integer
            // 7 survives.
            fireEvent.change(input, {
                target: { value: '1.5, 12abc, 7' },
            });

            expect(onCategoriesChange).toHaveBeenLastCalledWith([7]);
        });
    });

    describe('page-only Page attributes panel', () => {
        it('hides the panel when documentType is not "page"', () => {
            render(
                <DocumentPanels
                    {...baseProps({
                        documentType: 'post',
                        onParentChange: vi.fn(),
                        onMenuOrderChange: vi.fn(),
                        onTemplateChange: vi.fn(),
                    })}
                />
            );

            expect(
                screen.queryByRole('button', { name: /Page attributes/i })
            ).not.toBeInTheDocument();
        });

        it('renders parent / menu_order / template inputs', async () => {
            const user = userEvent.setup();

            render(
                <DocumentPanels
                    {...baseProps({
                        documentType: 'page',
                        parent: 5,
                        menuOrder: 3,
                        template: 'sidebar',
                        onParentChange: vi.fn(),
                        onMenuOrderChange: vi.fn(),
                        onTemplateChange: vi.fn(),
                    })}
                />
            );

            await user.click(
                screen.getByRole('button', { name: /Page attributes/i })
            );

            expect(
                screen.getByTestId('ap-visual-editor-document-parent')
            ).toHaveValue('5');
            expect(
                screen.getByTestId('ap-visual-editor-document-menu-order')
            ).toHaveValue(3);
            expect(
                screen.getByTestId('ap-visual-editor-document-template')
            ).toHaveValue('sidebar');
        });

        it('clears parent when the input is blanked', async () => {
            const onParentChange = vi.fn();
            const user = userEvent.setup();

            render(
                <DocumentPanels
                    {...baseProps({
                        documentType: 'page',
                        parent: 5,
                        menuOrder: 0,
                        template: '',
                        onParentChange,
                        onMenuOrderChange: vi.fn(),
                        onTemplateChange: vi.fn(),
                    })}
                />
            );

            await user.click(
                screen.getByRole('button', { name: /Page attributes/i })
            );

            const input = screen.getByTestId(
                'ap-visual-editor-document-parent'
            );

            fireEvent.change(input, { target: { value: '' } });

            expect(onParentChange).toHaveBeenLastCalledWith(null);
        });

        it('coerces negative or non-numeric menu_order to 0', async () => {
            const onMenuOrderChange = vi.fn();
            const user = userEvent.setup();

            render(
                <DocumentPanels
                    {...baseProps({
                        documentType: 'page',
                        parent: null,
                        menuOrder: 0,
                        template: '',
                        onParentChange: vi.fn(),
                        onMenuOrderChange,
                        onTemplateChange: vi.fn(),
                    })}
                />
            );

            await user.click(
                screen.getByRole('button', { name: /Page attributes/i })
            );

            const input = screen.getByTestId(
                'ap-visual-editor-document-menu-order'
            );

            fireEvent.change(input, { target: { value: '-1' } });

            expect(onMenuOrderChange).toHaveBeenLastCalledWith(0);
        });
    });
});
