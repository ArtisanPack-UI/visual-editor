/**
 * Site-editor template preview content (#993): the preview entry is
 * resolved from the template's post type, piped into the canvas as
 * `artisanpack/postPreview` block context, rendered read-only by the
 * post-* blocks, and switchable through the header picker.
 */

import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { createContext, useContext, type ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// Block context the stubbed `BlockContextProvider` hands to the
// stubbed `BlockList`, standing in for Gutenberg's per-block context.
const StubBlockContext = createContext<Record<string, unknown>>({});

// The stubbed `BlockList` renders whatever component the suite installs
// after its imports — importing the block edits inside this factory
// would deadlock on the very mock they import.
const blockListSlot = vi.hoisted(() => ({
    Component: null as null | (() => JSX.Element),
}));

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: () => ({ className: 'block' }),
    InspectorControls: () => null,
    BlockControls: () => null,
    HeadingLevelDropdown: () => null,
    PlainText: () => <textarea data-testid="editable" />,
    BlockToolbar: () => null,
    BlockTools: ({ children }: { children?: ReactNode }) => <>{children}</>,
    WritingFlow: ({ children }: { children?: ReactNode }) => <>{children}</>,
    ObserveTyping: ({ children }: { children?: ReactNode }) => <>{children}</>,
    BlockContextProvider: ({
        value,
        children,
    }: {
        value: Record<string, unknown>;
        children?: ReactNode;
    }) => <StubBlockContext.Provider value={value}>{children}</StubBlockContext.Provider>,
    BlockList: () => {
        const Component = blockListSlot.Component;

        return Component === null ? null : <Component />;
    },
    store: 'core/block-editor',
}));

// Post Content only serializes on the current-entry path, which a
// template never takes.
vi.mock('@wordpress/blocks', () => ({ serialize: () => '' }));

vi.mock('../../editor/spacing-preset-styles', () => ({
    SpacingPresetStyles: () => null,
}));

import PostTitleEdit from '../../blocks/post-title/edit';
import PostExcerptEdit from '../../blocks/post-excerpt/edit';
import PostContentEdit from '../../blocks/post-content/edit';
import { EntityEditorCanvas } from '../entity-editor-canvas';
import {
    TemplatePreviewEntryPicker,
    useTemplatePreviewEntry,
} from '../template-preview-entry';

blockListSlot.Component = function StubBlockList(): JSX.Element {
    const context = useContext(StubBlockContext);

    return (
        <div data-testid="stub-block-list">
            <PostTitleEdit attributes={{ level: 1 }} context={context} />
            <PostExcerptEdit attributes={{}} context={context} />
            <PostContentEdit attributes={{}} context={context} />
        </div>
    );
};

interface StubEntry {
    id: number;
    slug: string;
    title: string;
    excerpt: string;
    content: string;
}

const POSTS: StubEntry[] = [
    { id: 3, slug: 'newest', title: 'Newest post', excerpt: 'Newest excerpt', content: '<p>Newest body</p>' },
    { id: 2, slug: 'about', title: 'About us', excerpt: 'About excerpt', content: '<p>About body</p>' },
];

let entriesByType: Record<string, StubEntry[]> = {};
const fetchMock = vi.fn(async (_url: string, init?: RequestInit) => {
    const payload = JSON.parse(String(init?.body ?? '{}')) as { postType?: string };
    const entries = entriesByType[payload.postType ?? ''] ?? [];

    return new Response(
        JSON.stringify({
            data: entries.map((entry) => ({
                id: entry.id,
                slug: entry.slug,
                title: { rendered: entry.title, raw: entry.title },
                excerpt: { rendered: entry.excerpt, raw: entry.excerpt },
                content: { rendered: entry.content, raw: '' },
            })),
            meta: { total: entries.length },
        }),
        { status: 200, headers: { 'Content-Type': 'application/json' } }
    );
});

beforeEach(() => {
    entriesByType = { post: POSTS, page: POSTS, package: [] };
    fetchMock.mockClear();
    vi.stubGlobal('fetch', fetchMock);
});

afterEach(() => {
    vi.unstubAllGlobals();
});

function TemplateHarness({ slug }: { slug: string }): JSX.Element {
    const preview = useTemplatePreviewEntry('/visual-editor/api', slug);

    return (
        <EntityEditorCanvas
            entityTitle={slug}
            header={<TemplatePreviewEntryPicker state={preview} />}
            blockContext={
                preview.entry === null ? null : { 'artisanpack/postPreview': preview.entry }
            }
        />
    );
}

describe('template preview entry (#993)', () => {
    it('previews the latest entry of the template post type, read-only', async () => {
        render(<TemplateHarness slug="single" />);

        await waitFor(() =>
            expect(screen.getByTestId('stub-block-list').querySelector('h1')?.textContent).toBe('Newest post')
        );

        const list = screen.getByTestId('stub-block-list');
        expect(list.querySelector('h1')?.textContent).toBe('Newest post');
        expect(list.textContent).toContain('Newest excerpt');
        expect(list.textContent).toContain('Newest body');
        // Template context never mounts the inline-editable inputs.
        expect(screen.queryByTestId('editable')).toBeNull();

        const [url, init] = fetchMock.mock.calls[0] ?? [];
        expect(url).toBe('/visual-editor/api/query/resolve');
        expect(JSON.parse(String(init?.body))).toMatchObject({ postType: 'post', orderBy: 'date', order: 'desc' });
    });

    it('prefers the slug-matched page for page-{slug} templates', async () => {
        render(<TemplateHarness slug="page-about" />);

        await waitFor(() =>
            expect(screen.getByTestId('stub-block-list').querySelector('h1')?.textContent).toBe('About us')
        );
    });

    it('switches the previewed entry through the picker', async () => {
        render(<TemplateHarness slug="single" />);

        const select = (await screen.findByLabelText('Preview content')) as HTMLSelectElement;
        expect(select.value).toBe('3');

        fireEvent.change(select, { target: { value: '2' } });

        await waitFor(() =>
            expect(screen.getByTestId('stub-block-list').querySelector('h1')?.textContent).toBe('About us')
        );
        expect(screen.getByTestId('stub-block-list').textContent).toContain('About body');
    });

    it('falls back to the placeholder chips when the type has no entries', async () => {
        render(<TemplateHarness slug="single-package" />);

        await screen.findByTestId('ap-site-editor-preview-entry-empty');

        const list = screen.getByTestId('stub-block-list');
        expect(list.textContent).toContain('Post Title');
        expect(list.textContent).toContain('Post Excerpt');
        expect(list.textContent).toContain('Post Content');
    });

    it('skips the preview for templates without a single entry', async () => {
        render(<TemplateHarness slug="index" />);

        await new Promise((resolve) => setTimeout(resolve, 400));

        expect(fetchMock).not.toHaveBeenCalled();
        expect(screen.queryByLabelText('Preview content')).toBeNull();
        expect(screen.getByTestId('stub-block-list').textContent).toContain('Post Title');
    });
});
