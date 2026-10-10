import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

// Stub `BlockInspector` — it pulls in the full block-editor store, which
// isn't what we're testing. The shell behaviour (tab switching, empty
// state, focus) is what this test owns. `InspectorControls.Slot` wraps
// a real bubbles-virtually `Slot` for the `list` group, and the
// `BlockInspector` stub mounts one too (as upstream does), so tests can
// observe the single-slot guarantee from #813 against the real
// SlotFill registry.
vi.mock('@wordpress/block-editor', async () => {
    const { Slot: WordPressSlot } = await vi.importActual<
        typeof import('@wordpress/components')
    >('@wordpress/components');
    const Slot = ({ group }: { group?: string }): JSX.Element => (
        <div
            data-testid={`ap-visual-editor-inspector-slot-${group ?? 'default'}-stub`}
        >
            {group === 'list' && (
                <WordPressSlot name="InspectorControlsListView" bubblesVirtually />
            )}
        </div>
    );
    return {
        BlockInspector: () => (
            <div data-testid="ap-visual-editor-block-inspector-stub">
                Block inspector stub
                <Slot group="list" />
            </div>
        ),
        InspectorControls: { Slot },
    };
});

// Stub `@wordpress/blocks` so we don't transitively pull in its JSON
// modules (which require import attributes vitest can't supply). The
// sidebar only uses `hasBlockSupport` + `getBlockType` to read state
// supports; tests can return `null` because the override path skips
// the state-supports lookup anyway.
vi.mock('@wordpress/blocks', () => ({
    hasBlockSupport: () => false,
    getBlockType: () => null,
}));

// Stub `@wordpress/components` `useSlotFills` so the List View tab's
// visibility is controlled per-test. `mockUseSlotFills` is settable
// per-test so a case can pretend the `list` group has fills and force
// the List View tab to render. Everything else is the real module.
const mockUseSlotFills = vi.fn(
    (): unknown[] | undefined => undefined,
);
vi.mock('@wordpress/components', async () => ({
    ...(await vi.importActual<typeof import('@wordpress/components')>(
        '@wordpress/components'
    )),
    __experimentalUseSlotFills: (name: string) =>
        name === 'InspectorControlsListView' ? mockUseSlotFills() : undefined,
}));

import { Fill, SlotFillProvider } from '@wordpress/components';

import { InspectorSidebar } from '../inspector-sidebar';

function renderSidebar(props: {
    hasSelectedBlockOverride: boolean;
    documentContent?: React.ReactNode;
}) {
    return render(
        <InspectorSidebar
            hasSelectedBlockOverride={props.hasSelectedBlockOverride}
            documentContent={
                props.documentContent ?? (
                    <div data-testid="ap-visual-editor-document-panels-stub">
                        Document panels stub
                    </div>
                )
            }
        />,
        { wrapper: SlotFillProvider }
    );
}

describe('<InspectorSidebar />', () => {
    beforeEach(() => {
        mockUseSlotFills.mockReturnValue(undefined);
    });

    it('exposes an accessible aside with a tablist', () => {
        renderSidebar({ hasSelectedBlockOverride: false });

        expect(
            screen.getByRole('complementary', { name: 'Inspector' })
        ).toBeInTheDocument();
        expect(
            screen.getByRole('tablist', { name: 'Inspector tabs' })
        ).toBeInTheDocument();
    });

    it('always renders both the Block and Document tabs', () => {
        renderSidebar({ hasSelectedBlockOverride: false });

        expect(
            screen.getByTestId('ap-visual-editor-inspector-tab-block')
        ).toBeInTheDocument();
        expect(
            screen.getByTestId('ap-visual-editor-inspector-tab-document')
        ).toBeInTheDocument();
    });

    it('lands on the Document tab when nothing is selected', () => {
        renderSidebar({ hasSelectedBlockOverride: false });

        expect(
            screen.getByTestId('ap-visual-editor-inspector-tab-document')
        ).toHaveAttribute('aria-selected', 'true');
        expect(
            screen.getByTestId('ap-visual-editor-inspector-tab-block')
        ).toHaveAttribute('aria-selected', 'false');
    });

    it('keeps both tabpanels mounted and toggles `hidden` on the inactive one', async () => {
        const user = userEvent.setup();

        renderSidebar({ hasSelectedBlockOverride: true });

        const blockPanel = screen.getByTestId(
            'ap-visual-editor-inspector-block-panel'
        );
        const documentPanel = screen.getByTestId(
            'ap-visual-editor-inspector-document-panel'
        );

        // Block tab is active; document panel mounted but hidden.
        expect(blockPanel).not.toHaveAttribute('hidden');
        expect(documentPanel).toHaveAttribute('hidden');

        await user.click(
            screen.getByTestId('ap-visual-editor-inspector-tab-document')
        );

        // Flipped: document shown, block hidden. Both still in the DOM.
        expect(documentPanel).not.toHaveAttribute('hidden');
        expect(blockPanel).toHaveAttribute('hidden');
    });

    it('shows an empty-state message on the Block tab when nothing is selected', async () => {
        const user = userEvent.setup();

        renderSidebar({ hasSelectedBlockOverride: false });

        await user.click(
            screen.getByTestId('ap-visual-editor-inspector-tab-block')
        );

        expect(
            screen.getByTestId('ap-visual-editor-inspector-block-empty')
        ).toHaveTextContent(/click on a block/i);
        expect(
            screen.queryByTestId('ap-visual-editor-block-inspector-stub')
        ).not.toBeInTheDocument();
    });

    it('auto-switches to the Block tab when a block becomes selected', () => {
        const { rerender } = render(
            <InspectorSidebar
                hasSelectedBlockOverride={false}
                documentContent={<div>docs</div>}
            />,
            { wrapper: SlotFillProvider }
        );

        rerender(
            <InspectorSidebar
                hasSelectedBlockOverride={true}
                documentContent={<div>docs</div>}
            />
        );

        expect(
            screen.getByTestId('ap-visual-editor-inspector-tab-block')
        ).toHaveAttribute('aria-selected', 'true');
        expect(
            screen.getByTestId('ap-visual-editor-inspector-block-panel')
        ).toBeInTheDocument();
        expect(
            screen.getByTestId('ap-visual-editor-block-inspector-stub')
        ).toBeInTheDocument();
    });

    it('keeps the active tab when selection clears', () => {
        const { rerender } = render(
            <InspectorSidebar
                hasSelectedBlockOverride={true}
                documentContent={<div>docs</div>}
            />,
            { wrapper: SlotFillProvider }
        );

        rerender(
            <InspectorSidebar
                hasSelectedBlockOverride={false}
                documentContent={<div>docs</div>}
            />
        );

        // Block tab stays selected but flips to the empty-state panel.
        expect(
            screen.getByTestId('ap-visual-editor-inspector-tab-block')
        ).toHaveAttribute('aria-selected', 'true');
        expect(
            screen.getByTestId('ap-visual-editor-inspector-block-empty')
        ).toBeInTheDocument();
    });

    it('switches tabs when the user clicks them', async () => {
        const user = userEvent.setup();

        renderSidebar({ hasSelectedBlockOverride: true });

        // Auto-activated to Block; click Document to switch.
        await user.click(
            screen.getByTestId('ap-visual-editor-inspector-tab-document')
        );

        expect(
            screen.getByTestId('ap-visual-editor-inspector-tab-document')
        ).toHaveAttribute('aria-selected', 'true');
        expect(
            screen.getByTestId('ap-visual-editor-inspector-tab-block')
        ).toHaveAttribute('aria-selected', 'false');
        expect(
            screen.getByTestId('ap-visual-editor-inspector-document-panel')
        ).toBeInTheDocument();
    });

    it('moves focus with ArrowLeft/ArrowRight and activates the focused tab', async () => {
        const user = userEvent.setup();

        renderSidebar({ hasSelectedBlockOverride: true });

        const blockTab = screen.getByTestId(
            'ap-visual-editor-inspector-tab-block'
        );
        const documentTab = screen.getByTestId(
            'ap-visual-editor-inspector-tab-document'
        );

        blockTab.focus();
        await user.keyboard('{ArrowRight}');

        expect(documentTab).toHaveFocus();
        expect(documentTab).toHaveAttribute('aria-selected', 'true');

        await user.keyboard('{ArrowLeft}');

        expect(blockTab).toHaveFocus();
        expect(blockTab).toHaveAttribute('aria-selected', 'true');
    });

    it('focuses the active tab on first render', () => {
        renderSidebar({ hasSelectedBlockOverride: false });

        expect(
            screen.getByTestId('ap-visual-editor-inspector-tab-document')
        ).toHaveFocus();
    });

    it('renders the provided document content in the Document tab panel', () => {
        renderSidebar({
            hasSelectedBlockOverride: false,
            documentContent: (
                <span data-testid="ap-visual-editor-document-custom">Custom</span>
            ),
        });

        expect(
            screen.getByTestId('ap-visual-editor-document-custom')
        ).toBeInTheDocument();
    });

    it('surfaces a List View tab when the selected block has `list` group fills (issue #808)', async () => {
        mockUseSlotFills.mockReturnValue([{}]);
        renderSidebar({ hasSelectedBlockOverride: true });

        expect(
            screen.getByTestId('ap-visual-editor-inspector-tab-list'),
        ).toBeInTheDocument();

        const user = userEvent.setup();
        await user.click(
            screen.getByTestId('ap-visual-editor-inspector-tab-list'),
        );

        expect(
            screen.getByTestId('ap-visual-editor-inspector-list-panel'),
        ).not.toHaveAttribute('hidden');
        expect(
            screen.getByTestId('ap-visual-editor-inspector-slot-list-stub'),
        ).toBeInTheDocument();
    });

    it('hides the List View tab when no block is selected or the block has no list fills', () => {
        mockUseSlotFills.mockReturnValue([]);
        renderSidebar({ hasSelectedBlockOverride: true });

        expect(
            screen.queryByTestId('ap-visual-editor-inspector-tab-list'),
        ).toBeNull();
    });

    it('renders a `list` fill exactly once, inside the List View tab panel (#813)', async () => {
        mockUseSlotFills.mockReturnValue([{}]);
        const user = userEvent.setup();

        render(
            <>
                <InspectorSidebar
                    hasSelectedBlockOverride={true}
                    documentContent={<div>docs</div>}
                />
                <Fill name="InspectorControlsListView">
                    <span data-testid="ap-visual-editor-list-fill">
                        Menu items
                    </span>
                </Fill>
            </>,
            { wrapper: SlotFillProvider }
        );

        const listPanel = screen.getByTestId(
            'ap-visual-editor-inspector-list-panel'
        );

        const expectSingleListFillIn = async (
            container: HTMLElement
        ): Promise<void> => {
            const slots = screen.getAllByTestId(
                'ap-visual-editor-inspector-slot-list-stub'
            );
            expect(slots).toHaveLength(1);
            expect(container).toContainElement(slots[0]);

            const fills = await screen.findAllByTestId(
                'ap-visual-editor-list-fill'
            );
            expect(fills).toHaveLength(1);
            expect(container).toContainElement(fills[0]);
        };

        // Block tab active: only the BlockInspector's own slot is mounted.
        await expectSingleListFillIn(
            screen.getByTestId('ap-visual-editor-block-inspector-stub')
        );

        await user.click(
            screen.getByTestId('ap-visual-editor-inspector-tab-list')
        );

        expect(
            screen.queryByTestId('ap-visual-editor-block-inspector-stub')
        ).toBeNull();
        await expectSingleListFillIn(listPanel);

        // Round-trip: the fill must survive the slot swapping back and
        // forth rather than being dropped when one slot unregisters.
        await user.click(
            screen.getByTestId('ap-visual-editor-inspector-tab-block')
        );
        await user.click(
            screen.getByTestId('ap-visual-editor-inspector-tab-list')
        );

        await expectSingleListFillIn(listPanel);
    });

    // FE-8 follow-up: BlockInspector unmounts while the List View tab is
    // active (#813), so its own Settings/Styles tab, open panels and
    // scroll position reset on every round-trip. Keeping it mounted needs
    // its inner `list` slot suppressed, which `@wordpress/components`
    // offers no public API for (see the comment in `inspector-sidebar.tsx`).
    it.todo('keeps BlockInspector panel state across a List View tab round-trip');
});
