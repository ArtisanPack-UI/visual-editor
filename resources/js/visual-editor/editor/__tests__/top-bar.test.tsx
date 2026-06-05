import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { TopBar, type TopBarProps } from '../top-bar';

function defaultProps(overrides: Partial<TopBarProps> = {}): TopBarProps {
    return {
        saveStatus: 'idle',
        lastSavedAt: null,
        saveErrorMessage: null,
        canUndo: false,
        canRedo: false,
        onUndo: vi.fn(),
        onRedo: vi.fn(),
        isInserterOpen: false,
        isInspectorOpen: false,
        onToggleInserter: vi.fn(),
        onToggleInspector: vi.fn(),
        previewUrl: null,
        onSave: vi.fn(),
        onCopyStyles: vi.fn(),
        onPasteStyles: vi.fn(),
        onShowKeyboardShortcuts: vi.fn(),
        ...overrides,
    };
}

afterEach(() => {
    vi.restoreAllMocks();
});

describe('TopBar', () => {
    it('renders the top bar as a banner header', () => {
        render(<TopBar {...defaultProps()} />);

        const header = screen.getByTestId('ap-visual-editor-top-bar');

        expect(header).toBeInTheDocument();
        expect(header.tagName).toBe('HEADER');
    });

    it('does not render title, slug, or status inputs (moved to canvas/sidebar)', () => {
        render(<TopBar {...defaultProps()} />);

        expect(
            screen.queryByTestId('ap-visual-editor-top-bar-title')
        ).not.toBeInTheDocument();
        expect(
            screen.queryByTestId('ap-visual-editor-top-bar-slug')
        ).not.toBeInTheDocument();
        expect(
            screen.queryByTestId('ap-visual-editor-top-bar-status')
        ).not.toBeInTheDocument();
    });

    it('disables undo/redo when history has no entries', () => {
        render(<TopBar {...defaultProps()} />);

        expect(screen.getByTestId('ap-visual-editor-top-bar-undo')).toBeDisabled();
        expect(screen.getByTestId('ap-visual-editor-top-bar-redo')).toBeDisabled();
    });

    it('fires onUndo and onRedo when the buttons are clicked', async () => {
        const user = userEvent.setup();
        const onUndo = vi.fn();
        const onRedo = vi.fn();

        render(
            <TopBar
                {...defaultProps({
                    canUndo: true,
                    canRedo: true,
                    onUndo,
                    onRedo,
                })}
            />
        );

        await user.click(screen.getByTestId('ap-visual-editor-top-bar-undo'));
        expect(onUndo).toHaveBeenCalledTimes(1);

        await user.click(screen.getByTestId('ap-visual-editor-top-bar-redo'));
        expect(onRedo).toHaveBeenCalledTimes(1);
    });

    it('toggles the inserter and inspector with aria-expanded wired up', async () => {
        const user = userEvent.setup();
        const onToggleInserter = vi.fn();
        const onToggleInspector = vi.fn();

        const { rerender } = render(
            <TopBar
                {...defaultProps({
                    onToggleInserter,
                    onToggleInspector,
                })}
            />
        );

        const inserter = screen.getByTestId('ap-visual-editor-top-bar-inserter');
        const inspector = screen.getByTestId('ap-visual-editor-top-bar-inspector');

        expect(inserter).toHaveAttribute('aria-expanded', 'false');
        expect(inspector).toHaveAttribute('aria-expanded', 'false');

        await user.click(inserter);
        await user.click(inspector);

        expect(onToggleInserter).toHaveBeenCalledTimes(1);
        expect(onToggleInspector).toHaveBeenCalledTimes(1);

        rerender(
            <TopBar
                {...defaultProps({
                    onToggleInserter,
                    onToggleInspector,
                    isInserterOpen: true,
                    isInspectorOpen: true,
                })}
            />
        );

        expect(screen.getByTestId('ap-visual-editor-top-bar-inserter')).toHaveAttribute(
            'aria-expanded',
            'true'
        );
        expect(screen.getByTestId('ap-visual-editor-top-bar-inspector')).toHaveAttribute(
            'aria-expanded',
            'true'
        );
    });

    it('surfaces the save status label and lastSavedAt timestamp', () => {
        const { rerender } = render(
            <TopBar {...defaultProps({ saveStatus: 'saving' })} />
        );

        const status = screen.getByTestId('ap-visual-editor-top-bar-save-status');
        expect(status).toHaveTextContent(/Saving/);
        expect(status).toHaveAttribute('aria-live', 'polite');
        expect(status).toHaveAttribute('data-save-status', 'saving');

        rerender(
            <TopBar
                {...defaultProps({
                    saveStatus: 'saved',
                    lastSavedAt: '2026-04-19T10:00:00Z',
                })}
            />
        );

        expect(
            screen.getByTestId('ap-visual-editor-top-bar-save-status')
        ).toHaveTextContent(/Saved/);

        rerender(
            <TopBar
                {...defaultProps({
                    saveStatus: 'error',
                    saveErrorMessage: 'Network error',
                })}
            />
        );

        expect(
            screen.getByTestId('ap-visual-editor-top-bar-save-status')
        ).toHaveTextContent('Network error');
    });

    it('honors per-host overrides for the inserter and inspector aria labels', () => {
        const { rerender } = render(
            <TopBar
                {...defaultProps({
                    inserterToggleAriaLabel: {
                        open: 'Open navigator',
                        close: 'Close navigator',
                    },
                    inspectorToggleAriaLabel: {
                        open: 'Open settings',
                        close: 'Close settings',
                    },
                })}
            />
        );

        expect(
            screen.getByTestId('ap-visual-editor-top-bar-inserter')
        ).toHaveAttribute('aria-label', 'Open navigator');
        expect(
            screen.getByTestId('ap-visual-editor-top-bar-inspector')
        ).toHaveAttribute('aria-label', 'Open settings');

        rerender(
            <TopBar
                {...defaultProps({
                    isInserterOpen: true,
                    isInspectorOpen: true,
                    inserterToggleAriaLabel: {
                        open: 'Open navigator',
                        close: 'Close navigator',
                    },
                    inspectorToggleAriaLabel: {
                        open: 'Open settings',
                        close: 'Close settings',
                    },
                })}
            />
        );

        expect(
            screen.getByTestId('ap-visual-editor-top-bar-inserter')
        ).toHaveAttribute('aria-label', 'Close navigator');
        expect(
            screen.getByTestId('ap-visual-editor-top-bar-inspector')
        ).toHaveAttribute('aria-label', 'Close settings');
    });

    it('renders a preview link when a URL is provided', () => {
        render(
            <TopBar
                {...defaultProps({ previewUrl: 'https://example.test/preview' })}
            />
        );

        const preview = screen.getByTestId('ap-visual-editor-top-bar-preview');
        expect(preview).toHaveAttribute('href', 'https://example.test/preview');
        expect(preview).toHaveAttribute('target', '_blank');
        expect(preview).toHaveAttribute('rel', 'noopener noreferrer');
    });

    it('does not render the preview link when no URL is provided', () => {
        render(<TopBar {...defaultProps({ previewUrl: null })} />);

        expect(
            screen.queryByTestId('ap-visual-editor-top-bar-preview')
        ).not.toBeInTheDocument();
    });

    it('opens and closes the more-options menu and fires the handlers', async () => {
        const user = userEvent.setup();
        const onShowKeyboardShortcuts = vi.fn();
        const onCopyStyles = vi.fn();
        const onPasteStyles = vi.fn();

        render(
            <TopBar
                {...defaultProps({
                    onShowKeyboardShortcuts,
                    onCopyStyles,
                    onPasteStyles,
                })}
            />
        );

        const trigger = screen.getByTestId('ap-visual-editor-top-bar-menu-trigger');
        expect(trigger).toHaveAttribute('aria-expanded', 'false');

        await user.click(trigger);

        expect(trigger).toHaveAttribute('aria-expanded', 'true');
        expect(screen.getByRole('menu')).toBeInTheDocument();

        await user.click(
            screen.getByTestId('ap-visual-editor-top-bar-menu-shortcuts')
        );

        expect(onShowKeyboardShortcuts).toHaveBeenCalledTimes(1);
        expect(screen.queryByRole('menu')).not.toBeInTheDocument();

        await user.click(trigger);
        await user.click(
            screen.getByTestId('ap-visual-editor-top-bar-menu-copy-styles')
        );
        expect(onCopyStyles).toHaveBeenCalledTimes(1);

        await user.click(trigger);
        await user.click(
            screen.getByTestId('ap-visual-editor-top-bar-menu-paste-styles')
        );
        expect(onPasteStyles).toHaveBeenCalledTimes(1);
    });

    it('closes the menu on Escape and restores focus to the trigger', async () => {
        const user = userEvent.setup();

        render(<TopBar {...defaultProps()} />);

        const trigger = screen.getByTestId('ap-visual-editor-top-bar-menu-trigger');
        await user.click(trigger);

        expect(screen.getByRole('menu')).toBeInTheDocument();

        fireEvent.keyDown(window, { key: 'Escape' });

        expect(screen.queryByRole('menu')).not.toBeInTheDocument();
        expect(trigger).toHaveFocus();
    });

    it('disables menu items whose handler is not provided', async () => {
        const user = userEvent.setup();

        render(
            <TopBar
                {...defaultProps({
                    onCopyStyles: undefined,
                    onPasteStyles: undefined,
                    onShowKeyboardShortcuts: undefined,
                })}
            />
        );

        await user.click(
            screen.getByTestId('ap-visual-editor-top-bar-menu-trigger')
        );

        expect(
            screen.getByTestId('ap-visual-editor-top-bar-menu-shortcuts')
        ).toBeDisabled();
        expect(
            screen.getByTestId('ap-visual-editor-top-bar-menu-copy-styles')
        ).toBeDisabled();
        expect(
            screen.getByTestId('ap-visual-editor-top-bar-menu-paste-styles')
        ).toBeDisabled();
    });

    it('wires the ⌘Z / ⌘⇧Z / ⌘S keyboard shortcuts', () => {
        const onUndo = vi.fn();
        const onRedo = vi.fn();
        const onSave = vi.fn();

        render(
            <TopBar
                {...defaultProps({
                    canUndo: true,
                    canRedo: true,
                    onUndo,
                    onRedo,
                    onSave,
                })}
            />
        );

        fireEvent.keyDown(window, { key: 'z', metaKey: true });
        expect(onUndo).toHaveBeenCalledTimes(1);

        fireEvent.keyDown(window, { key: 'z', metaKey: true, shiftKey: true });
        expect(onRedo).toHaveBeenCalledTimes(1);

        fireEvent.keyDown(window, { key: 's', metaKey: true });
        expect(onSave).toHaveBeenCalledTimes(1);
    });

    it('ignores the undo shortcut when canUndo is false', () => {
        const onUndo = vi.fn();

        render(
            <TopBar
                {...defaultProps({
                    canUndo: false,
                    onUndo,
                })}
            />
        );

        fireEvent.keyDown(window, { key: 'z', metaKey: true });
        expect(onUndo).not.toHaveBeenCalled();
    });
});
