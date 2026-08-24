/**
 * Column responsive-width scope tests (#712).
 *
 * The `ve-w-<hash>` scope class must match the Blade partial byte-for-byte,
 * so the expected class names below are pinned to
 * `substr( hash( 'xxh3', json_encode( $merged ) ), 0, 10 )`.
 */

import { describe, expect, it } from 'vitest';
import { columnWidthScope, stampColumnWidthScopes } from '../src/support/columnWidth';
import { getBreakpoints, setBreakpoints } from '../src/visibility';
import type { Block } from '../src/types';

function column(attributes: Record<string, unknown>): Block {
    return { name: 'core/column', attributes, innerBlocks: [] } as unknown as Block;
}

describe('columnWidthScope', () => {
    it('promotes a numeric width into the base slot and hashes it like Blade', () => {
        const scope = columnWidthScope({ width: 60 });

        expect(scope?.className).toBe('ve-w-971ccacfaf');
        expect(scope?.css).toBe(
            '.ve-w-971ccacfaf.ve-w-971ccacfaf.ve-w-971ccacfaf{flex-basis:calc(60% - var(--wp--style--block-gap, 0.5em) * 0.4)!important;flex-grow:0!important}'
        );
    });

    it('merges the legacy width with a responsive override', () => {
        const scope = columnWidthScope({
            width: '50%',
            responsive: { width: { md: '33.33%' } },
        });

        expect(scope?.className).toBe('ve-w-b3f909ad19');
        // Base rule + one min-width media query for the md breakpoint.
        expect(scope?.css).toContain('.ve-w-b3f909ad19.ve-w-b3f909ad19.ve-w-b3f909ad19{flex-basis:');
        expect(scope?.css).toContain('@media (min-width:768px){');
    });

    it('emits an absolute-unit width verbatim, without the block-gap calc', () => {
        const scope = columnWidthScope({ width: '100px' });

        expect(scope?.className).toBe('ve-w-99b2f912b2');
        expect(scope?.css).toContain('flex-basis:100px!important');
    });

    it('hashes a responsive-only override (no base promotion)', () => {
        const scope = columnWidthScope({ responsive: { width: { md: '40%' } } });

        expect(scope?.className).toBe('ve-w-d02e69acda');
    });

    it('returns null for a column with no width', () => {
        expect(columnWidthScope({})).toBeNull();
    });

    it('treats an empty / zero width as no width (PHP empty semantics)', () => {
        expect(columnWidthScope({ width: 0 })).toBeNull();
        expect(columnWidthScope({ width: '0' })).toBeNull();
        expect(columnWidthScope({ width: '' })).toBeNull();
    });

    it('returns null when every override is an orphan breakpoint', () => {
        // `xx` is not a registered breakpoint, so no rule survives and the
        // scope class must not be attached.
        expect(columnWidthScope({ responsive: { width: { xx: '40%' } } })).toBeNull();
    });

    it('accepts a bare CSS length as a flex-basis value', () => {
        expect(columnWidthScope({ width: '20rem' })?.css).toContain('flex-basis:20rem!important');
        expect(columnWidthScope({ width: '300px' })?.css).toContain('flex-basis:300px!important');
    });

    it('rejects a hostile width value so it never reaches the stylesheet', () => {
        // A value that could close the rule and inject CSS must be dropped;
        // with no surviving rule the scope class is not attached either.
        expect(columnWidthScope({ width: '10px}body{display:none' })).toBeNull();
        expect(columnWidthScope({ width: 'red;color:blue' })).toBeNull();

        // A valid base survives while a hostile per-breakpoint override is
        // skipped — only the safe base rule is emitted.
        const mixed = columnWidthScope({
            width: 60,
            responsive: { width: { md: '50%}html{opacity:0' } },
        });
        expect(mixed).not.toBeNull();
        expect(mixed?.css).toContain('flex-basis:calc(60% -');
        expect(mixed?.css).not.toContain('opacity');
        expect(mixed?.css).not.toContain('@media');
    });
});

describe('stampColumnWidthScopes', () => {
    it('stamps the scope onto matching columns and accumulates unique CSS', () => {
        const tree: Block[] = [
            {
                name: 'core/columns',
                attributes: {},
                innerBlocks: [column({ width: 60 }), column({ width: 60 }), column({})],
            } as unknown as Block,
        ];

        const { tree: stamped, css } = stampColumnWidthScopes(tree);
        const columns = (stamped[0].innerBlocks ?? []) as Block[];

        expect((columns[0].attributes as Record<string, unknown>)._veColumnWidthScope).toBe('ve-w-971ccacfaf');
        expect((columns[1].attributes as Record<string, unknown>)._veColumnWidthScope).toBe('ve-w-971ccacfaf');
        expect((columns[2].attributes as Record<string, unknown>)._veColumnWidthScope).toBeUndefined();

        // Identical width maps share one scope, so the rule is emitted once.
        const occurrences = css.split('.ve-w-971ccacfaf.ve-w-971ccacfaf.ve-w-971ccacfaf{').length - 1;
        expect(occurrences).toBe(1);
    });

    it('strips an author-supplied _veColumnWidthScope side-channel value', () => {
        // A widthless column carrying a crafted scope attribute must not
        // leak it into the rendered class list.
        const injected = column({ _veColumnWidthScope: 'fixed inset-0 z-50 bg-black' });
        const withWidth = column({ width: 60, _veColumnWidthScope: 'attacker-class' });

        const { tree: stamped } = stampColumnWidthScopes([injected, withWidth]);

        // No scope resolves → the injected value is removed entirely.
        expect((stamped[0].attributes as Record<string, unknown>)._veColumnWidthScope).toBeUndefined();
        // A real scope resolves → it overwrites the crafted value.
        expect((stamped[1].attributes as Record<string, unknown>)._veColumnWidthScope).toBe('ve-w-971ccacfaf');
    });

    it('leaves non-column blocks untouched', () => {
        const tree: Block[] = [column({ width: 60 })];
        // A paragraph carrying a stray `width` attribute must not be stamped.
        const para = { name: 'core/paragraph', attributes: { width: 60 }, innerBlocks: [] } as unknown as Block;

        const { tree: stamped } = stampColumnWidthScopes([...tree, para]);

        expect((stamped[1].attributes as Record<string, unknown>)._veColumnWidthScope).toBeUndefined();
    });

    it('honours host-configured breakpoints', () => {
        // Capture whatever config is currently installed so this test does
        // not clobber a host/setup-configured breakpoint list for later tests.
        const prior = getBreakpoints();
        setBreakpoints([{ key: 'md', minWidthPx: 900 }]);

        try {
            const scope = columnWidthScope({ responsive: { width: { md: '40%' } } });
            expect(scope?.css).toContain('@media (min-width:900px){');
        } finally {
            setBreakpoints(prior);
        }
    });
});
