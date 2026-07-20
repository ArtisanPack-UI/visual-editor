/**
 * Tests for `PatternThumbnail`.
 *
 * Covers the block-tree summary happy path and the #667 fix where a
 * theme-shipped pattern arrives with `blocks: []` but a non-empty
 * `rawContent`. The component parses the raw string client-side so the
 * card shows the real tree instead of the "Empty pattern" placeholder.
 */

import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const parseMock = vi.fn();

vi.mock('@wordpress/blocks', () => ({
    parse: (source: string) => parseMock(source),
}));

beforeEach(() => {
    parseMock.mockReset();
});

// The i18n vendor module imports `@wordpress/i18n`. Its default export
// covers `__()` — no mocking needed. Import after the mock is declared
// so the parse override lands before the component's import graph.
import { PatternThumbnail } from '../pattern-thumbnail';

describe('<PatternThumbnail />', () => {
    it('renders a block tree summary when blocks is populated', () => {
        render(
            <PatternThumbnail
                blocks={[
                    { name: 'core/paragraph', innerBlocks: [] },
                    {
                        name: 'core/columns',
                        innerBlocks: [
                            { name: 'core/column', innerBlocks: [] },
                        ],
                    },
                ]}
                title="Hero"
            />
        );

        expect(screen.getByTestId('ap-pattern-thumb')).toBeInTheDocument();
        expect(screen.getByText('core/paragraph')).toBeInTheDocument();
        expect(screen.getByText('core/columns')).toBeInTheDocument();
        expect(screen.getByText('core/column')).toBeInTheDocument();
        expect(parseMock).not.toHaveBeenCalled();
    });

    it('renders the empty placeholder when blocks is empty and no rawContent is supplied', () => {
        render(<PatternThumbnail blocks={[]} title="Empty" />);

        expect(
            screen.getByTestId('ap-pattern-thumb-empty')
        ).toBeInTheDocument();
        expect(parseMock).not.toHaveBeenCalled();
    });

    it('renders the empty placeholder when rawContent is present but only whitespace', () => {
        render(
            <PatternThumbnail
                blocks={[]}
                rawContent={'   \n   '}
                title="Empty"
            />
        );

        expect(
            screen.getByTestId('ap-pattern-thumb-empty')
        ).toBeInTheDocument();
        expect(parseMock).not.toHaveBeenCalled();
    });

    it('parses rawContent when blocks is empty and shows the parsed tree (#667)', () => {
        parseMock.mockReturnValueOnce([
            { name: 'core/heading', innerBlocks: [] },
            { name: 'core/paragraph', innerBlocks: [] },
        ]);

        const raw =
            '<!-- wp:heading --><h2>Hi</h2><!-- /wp:heading -->' +
            '<!-- wp:paragraph --><p>Body</p><!-- /wp:paragraph -->';

        render(
            <PatternThumbnail
                blocks={[]}
                rawContent={raw}
                title="Theme pattern"
            />
        );

        expect(parseMock).toHaveBeenCalledWith(raw);
        expect(screen.getByTestId('ap-pattern-thumb')).toBeInTheDocument();
        expect(screen.getByText('core/heading')).toBeInTheDocument();
        expect(screen.getByText('core/paragraph')).toBeInTheDocument();
        expect(
            screen.queryByTestId('ap-pattern-thumb-empty')
        ).not.toBeInTheDocument();
    });

    it('falls back to the empty placeholder and logs a warning when parsing rawContent throws', () => {
        parseMock.mockImplementationOnce(() => {
            throw new Error('boom');
        });
        const warnSpy = vi
            .spyOn(console, 'warn')
            .mockImplementation(() => undefined);

        render(
            <PatternThumbnail
                blocks={[]}
                rawContent="<!-- wp:broken -->"
                title="Broken"
            />
        );

        expect(
            screen.getByTestId('ap-pattern-thumb-empty')
        ).toBeInTheDocument();
        expect(warnSpy).toHaveBeenCalledWith(
            expect.stringContaining('Failed to parse pattern rawContent'),
            expect.any(Error)
        );

        warnSpy.mockRestore();
    });

    it('prefers server-parsed blocks over rawContent when both are present', () => {
        render(
            <PatternThumbnail
                blocks={[{ name: 'core/paragraph', innerBlocks: [] }]}
                rawContent="<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->"
                title="Mixed"
            />
        );

        expect(parseMock).not.toHaveBeenCalled();
        expect(screen.getByText('core/paragraph')).toBeInTheDocument();
    });
});
