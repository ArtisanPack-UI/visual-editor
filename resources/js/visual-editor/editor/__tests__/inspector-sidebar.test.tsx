import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

// Stub `BlockInspector` — it pulls in the full block-editor store, which
// isn't what we're testing. The shell behaviour (tab switching, empty
// state, focus) is what this test owns.
vi.mock('@wordpress/block-editor', () => ({
    BlockInspector: () => (
        <div data-testid="ap-visual-editor-block-inspector-stub">
            Block inspector stub
        </div>
    ),
}));

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
        />
    );
}

describe('<InspectorSidebar />', () => {
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
            />
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
            />
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
});
