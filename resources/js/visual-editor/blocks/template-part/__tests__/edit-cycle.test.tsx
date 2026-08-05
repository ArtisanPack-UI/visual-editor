/**
 * Cycle protection for artisanpack/template-part (#655).
 *
 * Since #674/#675 the fork resolves its own referenced part, and #655
 * makes the post editor's composed view render template chrome the same
 * way. That means a part whose markup references itself — or two parts
 * that reference each other — mounts this component inside itself with
 * nothing to stop it. Parts are theme/DB content, so nothing upstream
 * guarantees they are acyclic.
 *
 * These tests mount the real nesting: `useInnerBlocksProps` is mocked to
 * render each resolved template-part block as a nested edit, which is what
 * Gutenberg does for real, so an unguarded implementation recurses until
 * the stack overflows.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render } from '@testing-library/react';
import type { ReactElement } from 'react';

interface FakeBlock {
    name: string;
    attributes?: Record<string, unknown>;
}

// Assigned after the import below — the vi.mock factory is hoisted above
// it, so the mock reaches the component through this late-bound holder.
let renderNested: ((blocks: readonly FakeBlock[]) => unknown) | null = null;

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: () => ({}),
    useInnerBlocksProps: (
        wrapperProps: Record<string, unknown>,
        options: { value?: readonly FakeBlock[] },
    ) => ({
        ...wrapperProps,
        children:
            renderNested === null ? null : renderNested(options.value ?? []),
    }),
}));

const useEntityBlockEditor = vi.fn();

vi.mock('../../../vendor/core-data-shim', () => ({
    useEntityBlockEditor: (...args: unknown[]) => useEntityBlockEditor(...args),
}));

import TemplatePartEdit from '../edit';

renderNested = (blocks) =>
    blocks.map((block, index) =>
        block?.name === 'artisanpack/template-part' ? (
            <TemplatePartEdit
                key={index}
                attributes={block.attributes ?? {}}
            />
        ) : null,
    );

/** `theme//slug` → the blocks that part resolves to. */
function mockParts(parts: Record<string, FakeBlock[]>): void {
    useEntityBlockEditor.mockImplementation(
        (_kind: unknown, _type: unknown, options: { id: string }) => [
            parts[options.id] ?? [],
            vi.fn(),
            vi.fn(),
        ],
    );
}

function partRef(slug: string, theme = 't'): FakeBlock {
    return {
        name: 'artisanpack/template-part',
        attributes: { slug, theme },
    };
}

function renderPart(slug: string): ReturnType<typeof render> {
    return render(
        <TemplatePartEdit attributes={{ slug, theme: 't' }} />,
    ) as ReturnType<typeof render>;
}

describe('template-part cycle protection', () => {
    beforeEach(() => {
        useEntityBlockEditor.mockReset();
    });

    it('terminates on a part that references itself', () => {
        mockParts({ 't//header': [partRef('header')] });

        expect(() => renderPart('header')).not.toThrow();

        // The outer part resolves once; the self-reference inside it is a
        // repeat and is cut off before fetching again.
        expect(useEntityBlockEditor).toHaveBeenCalledTimes(1);
    });

    it('terminates on mutually-referencing parts', () => {
        mockParts({
            't//header': [partRef('nav')],
            't//nav': [partRef('header')],
        });

        expect(() => renderPart('header')).not.toThrow();

        expect(useEntityBlockEditor).toHaveBeenCalledTimes(2);
        expect(useEntityBlockEditor).toHaveBeenNthCalledWith(
            1,
            'postType',
            'wp_template_part',
            { id: 't//header' },
        );
        expect(useEntityBlockEditor).toHaveBeenNthCalledWith(
            2,
            'postType',
            'wp_template_part',
            { id: 't//nav' },
        );
    });

    it('terminates on a longer reference cycle', () => {
        mockParts({
            't//a': [partRef('b')],
            't//b': [partRef('c')],
            't//c': [partRef('a')],
        });

        expect(() => renderPart('a')).not.toThrow();
        expect(useEntityBlockEditor).toHaveBeenCalledTimes(3);
    });

    it('still resolves a shared part referenced by two siblings', () => {
        // Not a cycle — `shared` is never its own ancestor, so both refs
        // must resolve. A naive global "already seen" set would drop one.
        mockParts({
            't//layout': [partRef('shared'), partRef('shared')],
            't//shared': [],
        });

        renderPart('layout');

        const sharedCalls = useEntityBlockEditor.mock.calls.filter(
            (call) => (call[2] as { id: string }).id === 't//shared',
        );

        expect(sharedCalls).toHaveLength(2);
    });

    it('survives a mounted block whose slug is filled in later', () => {
        // The empty branch used to call `useBlockProps()` inline, so the
        // component's hook count depended on its attributes — React throws
        // on the re-render where a placeholder ref gains a slug.
        mockParts({ 't//header': [] });

        const { rerender } = render(
            <TemplatePartEdit attributes={{ slug: '', theme: 't' }} />,
        );

        expect(() =>
            rerender(
                <TemplatePartEdit attributes={{ slug: 'header', theme: 't' }} />,
            ),
        ).not.toThrow();

        expect(useEntityBlockEditor).toHaveBeenCalledWith(
            'postType',
            'wp_template_part',
            { id: 't//header' },
        );
    });

    it('resolves a nested chain that has no cycle', () => {
        mockParts({
            't//a': [partRef('b')],
            't//b': [partRef('c')],
            't//c': [],
        });

        renderPart('a');

        expect(useEntityBlockEditor).toHaveBeenCalledTimes(3);
    });
});
