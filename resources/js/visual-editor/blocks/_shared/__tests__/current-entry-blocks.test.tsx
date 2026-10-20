/**
 * Post Title / Post Excerpt / Post Content against the entry being
 * edited (#992), plus the Post Title heading-level control (#994).
 *
 * Covers custom content types (client-only seeded entity), the
 * inline-editable excerpt, the read-only Post Content preview with its
 * recursion guard, and the chip fallback when a value is genuinely
 * empty.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, fireEvent, render } from '@testing-library/react';
import {
    createReduxStore,
    dispatch,
    register,
    select,
} from '@wordpress/data';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: () => ({ className: 'wrapper' }),
    InspectorControls: ({ children }: { children: React.ReactNode }) => (
        <>{children}</>
    ),
    BlockControls: ({ children }: { children: React.ReactNode }) => (
        <>{children}</>
    ),
    HeadingLevelDropdown: ({
        options,
        value,
        onChange,
    }: {
        options?: number[];
        value: number;
        onChange: (next: number) => void;
    }) => {
        const levels = (options ?? [1, 2, 3, 4, 5, 6]).filter(
            (level) => level >= 0 && level <= 6
        );

        return (
            <div data-testid="heading-level-dropdown" data-value={value}>
                {levels.map((level) => (
                    <button
                        key={level}
                        type="button"
                        onClick={() => onChange(level)}
                    >
                        {level === 0 ? 'Paragraph' : `Heading ${level}`}
                    </button>
                ))}
            </div>
        );
    },
    PlainText: ({
        value,
        onChange,
        placeholder,
        tagName,
        __experimentalVersion: _version,
        ...rest
    }: {
        value: string;
        onChange: (next: string) => void;
        placeholder?: string;
        tagName?: string;
        __experimentalVersion?: number;
        [key: string]: unknown;
    }) => (
        <textarea
            data-tag={tagName}
            value={value}
            placeholder={placeholder}
            className={typeof rest.className === 'string' ? rest.className : undefined}
            onChange={(event) => onChange(event.target.value)}
        />
    ),
    store: 'core/block-editor',
}));

vi.mock('@wordpress/components', () => ({
    PanelBody: ({ children }: { children: React.ReactNode }) => <>{children}</>,
    ToggleControl: () => null,
    TextControl: () => null,
}));

// Serialize stub: the real serializer needs every block type registered.
// Emits paragraph-ish HTML so `htmlToText` has markup to strip.
vi.mock('@wordpress/blocks', () => {
    interface StubBlock {
        name: string;
        attributes?: { content?: string };
        innerBlocks?: StubBlock[];
    }

    const serializeOne = (block: StubBlock): string =>
        `<!-- wp:${block.name} --><p>${block.attributes?.content ?? ''}</p>${(block.innerBlocks ?? [])
            .map(serializeOne)
            .join('')}<!-- /wp:${block.name} -->`;

    return {
        serialize: (blocks: StubBlock[]) => blocks.map(serializeOne).join('\n'),
    };
});

import {
    __resetCoreDataShimConfig,
    configureCoreDataShim,
} from '../../../vendor/core-data-shim';
import { seedCurrentEntryRecord } from '../../../editor/current-entry';

import PostTitleEdit from '../../post-title/edit';
import PostExcerptEdit from '../../post-excerpt/edit';
import PostContentEdit, {
    entryContentPreviewText,
    withoutPostContentBlocks,
} from '../../post-content/edit';

// Minimal stand-in for the canvas's `core/block-editor` store — the
// real one is not loaded because the package is mocked above.
let canvasBlocks: unknown[] = [];

try {
    register(
        createReduxStore('core/block-editor', {
            reducer: (state: { version: number } = { version: 0 }, action: { type: string }) =>
                action.type === 'BUMP' ? { version: state.version + 1 } : state,
            actions: { bump: () => ({ type: 'BUMP' }) },
            selectors: { getBlocks: () => canvasBlocks },
        })
    );
} catch {
    // Already registered by another suite sharing the registry.
}

type CoreDispatch = Record<string, (...args: unknown[]) => unknown>;
const coreDispatch = (): CoreDispatch => dispatch('core') as CoreDispatch;
const fetcher = vi.fn(
    async () =>
        new Response(null, {
            status: 404,
            headers: { 'Content-Type': 'application/json' },
        })
);

async function resetStore(): Promise<void> {
    configureCoreDataShim({
        apiBase: '/_drain_',
        fetcher: async () => new Response(null, { status: 503 }),
    });

    await new Promise((resolve) => setTimeout(resolve, 50));

    coreDispatch().reset();
    coreDispatch().invalidateResolutionForStore();
}

const paragraph = (content: string, innerBlocks: { name: string }[] = []) => ({
    name: 'core/paragraph',
    attributes: { content },
    innerBlocks,
});

const postContentBlock = { name: 'artisanpack/post-content', attributes: {}, innerBlocks: [] };

beforeEach(async () => {
    await resetStore();
    __resetCoreDataShimConfig();
    fetcher.mockClear();
    configureCoreDataShim({ apiBase: '/visual-editor/api', fetcher });
    canvasBlocks = [];
});

afterEach(async () => {
    await resetStore();
    __resetCoreDataShimConfig();
});

const customEntry = { kind: 'postType' as const, name: 'package', id: 5 };
const customContext = { postType: 'package', postId: 5 };

describe('custom content type entries (#992)', () => {
    it('Post Title shows and edits the seeded custom-entry title without fetching', async () => {
        seedCurrentEntryRecord(customEntry, { title: 'Widget Pro', excerpt: '' });

        const { getByDisplayValue, queryByText } = render(
            <PostTitleEdit attributes={{}} context={customContext} />
        );

        expect(getByDisplayValue('Widget Pro')).not.toBeNull();
        expect(queryByText('Post Title')).toBeNull();

        act(() => {
            fireEvent.change(getByDisplayValue('Widget Pro'), {
                target: { value: 'Widget Pro 2' },
            });
        });

        const core = select('core') as unknown as {
            getEntityRecordEdits: (k: string, n: string, id: number) => Record<string, unknown> | null;
        };
        expect(core.getEntityRecordEdits('postType', 'package', 5)).toEqual({
            title: 'Widget Pro 2',
        });

        // The seeded record is marked resolved — no request to a
        // non-existent `/package/5` endpoint can clobber it.
        await new Promise((resolve) => setTimeout(resolve, 20));
        expect(fetcher).not.toHaveBeenCalled();
    });

    it('Post Excerpt shows the seeded excerpt and stages inline edits', () => {
        seedCurrentEntryRecord(customEntry, { title: '', excerpt: 'Small and fast.' });

        const { getByDisplayValue } = render(
            <PostExcerptEdit attributes={{}} context={customContext} />
        );

        act(() => {
            fireEvent.change(getByDisplayValue('Small and fast.'), {
                target: { value: 'Smaller and faster.' },
            });
        });

        expect(getByDisplayValue('Smaller and faster.')).not.toBeNull();
        const core = select('core') as unknown as {
            getEntityRecordEdits: (k: string, n: string, id: number) => Record<string, unknown> | null;
        };
        expect(core.getEntityRecordEdits('postType', 'package', 5)).toEqual({
            excerpt: 'Smaller and faster.',
        });
    });

    it('does not reseed (and so keeps staged edits) on a second seed call', () => {
        seedCurrentEntryRecord(customEntry, { title: 'First', excerpt: '' });
        seedCurrentEntryRecord(customEntry, { title: 'Second', excerpt: '' });

        const core = select('core') as unknown as {
            getEntityRecord: (k: string, n: string, id: number) => { title?: { raw?: string } } | null;
        };
        expect(core.getEntityRecord('postType', 'package', 5)?.title?.raw).toBe('First');
    });
});

describe('Post Excerpt readonly paths (#992)', () => {
    it('renders the query-preview excerpt readonly inside a loop', () => {
        seedCurrentEntryRecord(customEntry, { title: '', excerpt: 'Entry excerpt' });

        const { container } = render(
            <PostExcerptEdit
                attributes={{}}
                context={{
                    ...customContext,
                    queryId: 1,
                    'artisanpack/postPreview': { id: 9, excerpt: 'Loop excerpt' },
                }}
            />
        );

        expect(container.querySelector('textarea')).toBeNull();
        expect(container.textContent).toBe('Loop excerpt');
    });

    it('falls back to the chip when no entry is in scope', () => {
        const { getByText } = render(<PostExcerptEdit attributes={{}} context={{}} />);

        expect(getByText('Post Excerpt')).not.toBeNull();
    });
});

describe('Post Content preview (#992)', () => {
    it('renders the live entry content read-only, without itself', () => {
        canvasBlocks = [
            paragraph('Intro paragraph.'),
            postContentBlock,
            paragraph('Closing words.', [postContentBlock]),
        ];

        const { container, queryByText } = render(
            <PostContentEdit attributes={{}} context={customContext} />
        );

        expect(container.textContent).toContain('Intro paragraph.');
        expect(container.textContent).toContain('Closing words.');
        expect(container.querySelector('textarea')).toBeNull();
        expect(queryByText('Post Content')).toBeNull();
    });

    it('falls back to the chip when the entry has no other content', () => {
        canvasBlocks = [postContentBlock];

        const { getByText } = render(
            <PostContentEdit attributes={{}} context={customContext} />
        );

        expect(getByText('Post Content')).not.toBeNull();
    });

    it('renders the preview post content from block context', () => {
        const { container } = render(
            <PostContentEdit
                attributes={{}}
                context={{ 'artisanpack/postPreview': { id: 3, content: '<p>Preview <strong>body</strong></p>' } }}
            />
        );

        expect(container.textContent).toBe('Preview body');
    });

    it('falls back to the chip with no entry, preview, or stamped value', () => {
        const { getByText } = render(<PostContentEdit attributes={{}} context={{}} />);

        expect(getByText('Post Content')).not.toBeNull();
    });

    it('strips nested Post Content blocks before serializing', () => {
        const tree = withoutPostContentBlocks([
            { name: 'core/group', innerBlocks: [postContentBlock, paragraph('kept')] },
            { name: 'core/post-content', innerBlocks: [] },
        ]);

        expect(tree).toHaveLength(1);
        expect(tree[0]?.innerBlocks?.map((block) => block.name)).toEqual(['core/paragraph']);
        expect(entryContentPreviewText([postContentBlock])).toBe('');
    });
});

describe('Post Title heading level (#994)', () => {
    it.each([
        [1, 'H1'],
        [4, 'H4'],
        [6, 'H6'],
        [0, 'P'],
    ])('renders level %s as <%s> in the readonly preview', (level, tag) => {
        const { container } = render(
            <PostTitleEdit attributes={{ level, _resolvedTitle: 'Stamped' }} context={{}} />
        );

        expect(container.querySelector('.wrapper')?.tagName).toBe(tag);
    });

    it('passes the chosen tag to the editable title', () => {
        seedCurrentEntryRecord(customEntry, { title: 'Editable', excerpt: '' });

        const { getByDisplayValue } = render(
            <PostTitleEdit attributes={{ level: 0 }} context={customContext} />
        );

        expect(getByDisplayValue('Editable').getAttribute('data-tag')).toBe('p');
    });

    it('offers Paragraph and H1-H6 by default and writes the picked level', () => {
        const setAttributes = vi.fn();
        const { getByTestId, getByText } = render(
            <PostTitleEdit attributes={{ level: 2 }} setAttributes={setAttributes} context={{}} />
        );

        expect(getByTestId('heading-level-dropdown').getAttribute('data-value')).toBe('2');
        expect(getByTestId('heading-level-dropdown').querySelectorAll('button')).toHaveLength(7);

        fireEvent.click(getByText('Heading 5'));
        expect(setAttributes).toHaveBeenCalledWith({ level: 5 });

        fireEvent.click(getByText('Paragraph'));
        expect(setAttributes).toHaveBeenCalledWith({ level: 0 });
    });

    it('restricts the dropdown to levelOptions, including Paragraph', () => {
        const setAttributes = vi.fn();
        const { getByTestId, getByText } = render(
            <PostTitleEdit
                attributes={{ level: 0, levelOptions: [0, 2, 3] }}
                setAttributes={setAttributes}
                context={{}}
            />
        );

        const dropdown = getByTestId('heading-level-dropdown');
        expect(dropdown.getAttribute('data-value')).toBe('0');
        expect(
            Array.from(dropdown.querySelectorAll('button')).map((button) => button.textContent)
        ).toEqual(['Paragraph', 'Heading 2', 'Heading 3']);

        fireEvent.click(getByText('Paragraph'));
        expect(setAttributes).toHaveBeenCalledWith({ level: 0 });
    });
});
