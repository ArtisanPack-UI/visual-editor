/**
 * Tests for the editor-canvas visibility preview (#805): the
 * `editor.BlockListBlock` HOC and the List View label wrapper.
 */

import { afterEach, describe, expect, it, vi } from 'vitest';
import { act, render } from '@testing-library/react';

const selection = vi.hoisted(() => ({
    selected: new Set<string>(),
    innerSelected: new Set<string>(),
    activeVariation: undefined as { title?: string } | undefined,
}));

vi.mock('@wordpress/block-editor', () => ({ store: 'core/block-editor' }));
vi.mock('@wordpress/blocks', () => ({ store: 'core/blocks' }));
vi.mock('@wordpress/data', () => {
    const blockEditor = {
        isBlockSelected: (id: string) => selection.selected.has(id),
        isBlockMultiSelected: () => false,
        hasSelectedInnerBlock: (id: string) => selection.innerSelected.has(id),
    };
    const blocks = {
        getActiveBlockVariation: () => selection.activeVariation,
    };
    const selectStore = (store: string) => (store === 'core/blocks' ? blocks : blockEditor);

    return {
        select: selectStore,
        useSelect: (mapSelect: (select: typeof selectStore) => unknown) => mapSelect(selectStore),
    };
});

import { publishCanvasPreviewWidth } from '../../responsive/use-canvas-preview-width';
import { getCanvasScopedStyleElement } from '../../support/canvas-scoped-styles';
import {
    addVisibilityListViewLabel,
    buildVisibilityCanvasCss,
    VISIBILITY_STYLE_CHANNEL,
    visibilityBreakpoints,
    withVisibilityCanvas,
} from '../with-visibility-canvas';
import type { VisibilityAttribute } from '../types';

const BlockListBlock = (props: { wrapperProps?: { className?: string } }) => (
    <div data-testid="block" className={props.wrapperProps?.className ?? ''} />
);
const Wrapped = withVisibilityCanvas(BlockListBlock);

function hostStyle(): HTMLStyleElement | null {
    return getCanvasScopedStyleElement(VISIBILITY_STYLE_CHANNEL);
}

function renderBlock(visibility: VisibilityAttribute | null, clientId = 'abc-123') {
    return render(
        <Wrapped
            name="core/paragraph"
            clientId={clientId}
            attributes={{ artisanpackVisibility: visibility }}
            wrapperProps={{ className: 'existing' }}
        />,
    );
}

afterEach(() => {
    selection.selected.clear();
    selection.innerSelected.clear();
    selection.activeVariation = undefined;
    publishCanvasPreviewWidth(null);
});

describe('visibilityBreakpoints', () => {
    it('lists the legacy min-width breakpoints from the registry', () => {
        expect(visibilityBreakpoints()).toEqual([
            { key: 'sm', minWidthPx: 640 },
            { key: 'md', minWidthPx: 768 },
            { key: 'lg', minWidthPx: 1024 },
            { key: 'xl', minWidthPx: 1280 },
            { key: '2xl', minWidthPx: 1536 },
        ]);
    });
});

describe('buildVisibilityCanvasCss', () => {
    const breakpoints = visibilityBreakpoints();
    const mdHidden    = { screenSize: { direction: 'hide' as const, breakpoints: ['md'] } };

    it('emits nothing without rules', () => {
        expect(buildVisibilityCanvasCss('id', null, breakpoints, null)).toBe('');
    });

    it('emits the front-end media ranges at base', () => {
        expect(buildVisibilityCanvasCss('id', mdHidden, breakpoints, null)).toBe(
            '@media (min-width:768px) and (max-width:1023px){'
            + '.ap-vis-id:not(.is-ap-vis-revealed){display:none !important;}'
            + '.ap-vis-id.is-ap-vis-revealed{opacity:0.4;}}',
        );
    });

    it('hides unconditionally when the previewed width falls in a hidden range', () => {
        expect(buildVisibilityCanvasCss('id', mdHidden, breakpoints, 768)).toBe(
            '.ap-vis-id:not(.is-ap-vis-revealed){display:none !important;}'
            + '.ap-vis-id.is-ap-vis-revealed{opacity:0.4;}',
        );
    });

    it('shows the block when the previewed width is outside every range', () => {
        expect(buildVisibilityCanvasCss('id', mdHidden, breakpoints, 375)).toBe('');
    });

    it('dims blocks with rules the canvas cannot evaluate', () => {
        expect(buildVisibilityCanvasCss('id', { hide: { hidden: true } }, breakpoints, null))
            .toBe('.ap-vis-id{opacity:0.4;}');
    });
});

describe('withVisibilityCanvas', () => {
    it('renders the block untouched without rules', () => {
        const { container, getByTestId } = renderBlock(null);

        expect(container.querySelector('style')).toBeNull();
        expect(hostStyle()).toBeNull();
        expect(getByTestId('block').className).toBe('existing');
    });

    it('scopes the block and injects its rules', () => {
        const { container, getByTestId } = renderBlock({ screenSize: { direction: 'hide', breakpoints: ['md'] } });

        expect(getByTestId('block').className).toBe('existing ap-vis-abc-123');
        expect(container.querySelector('style')).toBeNull();
        expect(hostStyle()?.parentElement).toBe(document.head);
        expect(hostStyle()?.textContent).toContain('@media (min-width:768px)');
    });

    it('keeps no <style> sibling next to a gated first child (FE-6)', () => {
        const { getAllByTestId } = render(
            <div data-testid="list">
                <Wrapped
                    name="core/paragraph"
                    clientId="first"
                    attributes={{ artisanpackVisibility: { screenSize: { direction: 'hide', breakpoints: ['md'] } } }}
                />
                <Wrapped
                    name="core/paragraph"
                    clientId="second"
                    attributes={{ artisanpackVisibility: { hide: { hidden: true } } }}
                />
            </div>,
        );

        const [first, second] = getAllByTestId('block');

        expect(first?.previousElementSibling).toBeNull();
        expect(second?.previousElementSibling).toBe(first);
        expect(document.querySelectorAll(`style[data-ap-canvas-styles="${VISIBILITY_STYLE_CHANNEL}"]`)).toHaveLength(1);
        expect(hostStyle()?.textContent).toContain('.ap-vis-first');
        expect(hostStyle()?.textContent).toContain('.ap-vis-second');
    });

    it('removes a block\'s rules on unmount and drops the empty host', () => {
        const first  = renderBlock({ screenSize: { direction: 'hide', breakpoints: ['md'] } }, 'one');
        const second = renderBlock({ hide: { hidden: true } }, 'two');

        first.unmount();

        expect(hostStyle()?.textContent).not.toContain('.ap-vis-one');
        expect(hostStyle()?.textContent).toContain('.ap-vis-two');

        second.unmount();

        expect(hostStyle()).toBeNull();
        expect(document.querySelector('style[data-ap-canvas-styles]')).toBeNull();
    });

    it('publishes into the iframe canvas document the block renders in', () => {
        const frame = document.createElement('iframe');
        document.body.appendChild(frame);
        const frameDocument = frame.contentDocument as Document;
        const mount         = frameDocument.createElement('div');
        frameDocument.body.appendChild(mount);

        const InFrame = withVisibilityCanvas(() => <div id="block-framed" />);
        const { unmount } = render(
            <InFrame
                name="core/paragraph"
                clientId="framed"
                attributes={{ artisanpackVisibility: { hide: { hidden: true } } }}
            />,
            { container: mount },
        );

        expect(getCanvasScopedStyleElement(VISIBILITY_STYLE_CHANNEL, frameDocument)?.parentElement)
            .toBe(frameDocument.head);
        expect(hostStyle()).toBeNull();

        unmount();
        frame.remove();
    });

    it('reveals a hidden block while it is selected', () => {
        selection.selected.add('abc-123');

        const { getByTestId } = renderBlock({ screenSize: { direction: 'hide', breakpoints: ['md'] } });

        expect(getByTestId('block').className).toBe('existing ap-vis-abc-123 is-ap-vis-revealed');
    });

    it('reveals a hidden block while one of its inner blocks is selected', () => {
        selection.innerSelected.add('abc-123');

        const { getByTestId } = renderBlock({ screenSize: { direction: 'show', breakpoints: ['lg'] } });

        expect(getByTestId('block').className).toContain('is-ap-vis-revealed');
    });

    it('never marks dim-only blocks as revealed', () => {
        selection.selected.add('abc-123');

        const { getByTestId } = renderBlock({ hide: { hidden: true } });

        expect(getByTestId('block').className).toBe('existing ap-vis-abc-123');
    });

    it('does not re-render blocks without screen-size rules on a viewport switch', () => {
        let renders = 0;
        const Counting = withVisibilityCanvas(() => {
            renders++;

            return <div />;
        });

        render(<Counting name="core/paragraph" clientId="dim" attributes={{ artisanpackVisibility: { hide: { hidden: true } } }} />);
        const before = renders;

        act(() => publishCanvasPreviewWidth(768));

        expect(renders).toBe(before);
    });

    it('follows the canvas preview width', () => {
        const { container } = renderBlock({ screenSize: { direction: 'hide', breakpoints: ['md'] } });

        act(() => publishCanvasPreviewWidth(375));
        expect(container.querySelector('style')).toBeNull();
        expect(hostStyle()).toBeNull();

        act(() => publishCanvasPreviewWidth(768));
        expect(hostStyle()?.textContent).not.toContain('@media');
        expect(hostStyle()?.textContent).toContain('display:none');
    });
});

describe('addVisibilityListViewLabel', () => {
    const settings = {
        title: 'Paragraph',
        attributes: { artisanpackVisibility: { type: 'object', default: null } },
    };

    it('leaves blocks without the visibility attribute alone', () => {
        const plain = { title: 'Raw', attributes: {} };

        expect(addVisibilityListViewLabel(plain, 'core/html')).toBe(plain);
    });

    it('suffixes the block title in List View', () => {
        const label = addVisibilityListViewLabel(settings, 'core/paragraph').__experimentalLabel!;

        expect(label({ artisanpackVisibility: { hide: { hidden: true } } }, { context: 'list-view' }))
            .toBe('Paragraph (Hidden)');
    });

    it('keeps other contexts and rule-less blocks unchanged', () => {
        const label = addVisibilityListViewLabel(settings, 'core/paragraph').__experimentalLabel!;

        expect(label({ artisanpackVisibility: { hide: { hidden: true } } }, { context: 'visual' })).toBeUndefined();
        expect(label({ artisanpackVisibility: null }, { context: 'list-view' })).toBeUndefined();
    });

    it('suffixes an existing label such as a custom block name', () => {
        const label = addVisibilityListViewLabel(
            { ...settings, __experimentalLabel: () => 'Hero CTA' },
            'core/paragraph',
        ).__experimentalLabel!;

        expect(
            label(
                { artisanpackVisibility: { screenSize: { direction: 'hide', breakpoints: ['md'] } } },
                { context: 'list-view' },
            ),
        ).toBe('Hero CTA (Hidden at some screen sizes)');
    });

    it('falls back to the active variation title', () => {
        selection.activeVariation = { title: 'Row' };

        const label = addVisibilityListViewLabel({ ...settings, title: 'Group' }, 'core/group').__experimentalLabel!;

        expect(label({ artisanpackVisibility: { userRole: { roles: ['editor'] } } }, { context: 'list-view' }))
            .toBe('Row (Conditionally visible)');
    });
});
