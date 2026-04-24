import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import {
    NAVIGATOR_PANEL_ID,
    NavigatorSidebar,
    navigatorTabId,
} from '../navigator-sidebar';

describe('NavigatorSidebar', () => {
    it('renders one tab per registered section', () => {
        render(
            <NavigatorSidebar
                activeSection="templates"
                onSelectSection={vi.fn()}
            />
        );

        for (const slug of [
            'templates',
            'template-parts',
            'patterns',
            'styles',
            'navigation',
        ]) {
            expect(
                screen.getByTestId(`ap-site-editor-navigator-${slug}`)
            ).toBeInTheDocument();
        }
    });

    it('marks the active section with aria-selected', () => {
        render(
            <NavigatorSidebar
                activeSection="patterns"
                onSelectSection={vi.fn()}
            />
        );

        const active = screen.getByTestId('ap-site-editor-navigator-patterns');
        const inactive = screen.getByTestId(
            'ap-site-editor-navigator-templates'
        );

        expect(active).toHaveAttribute('aria-selected', 'true');
        expect(inactive).toHaveAttribute('aria-selected', 'false');
    });

    it('fires onSelectSection with the slug when a section is clicked', async () => {
        const user = userEvent.setup();
        const onSelectSection = vi.fn();

        render(
            <NavigatorSidebar
                activeSection="templates"
                onSelectSection={onSelectSection}
            />
        );

        await user.click(screen.getByTestId('ap-site-editor-navigator-styles'));

        expect(onSelectSection).toHaveBeenCalledWith('styles');
    });

    it('renders an outlet when children are provided', () => {
        render(
            <NavigatorSidebar
                activeSection="templates"
                onSelectSection={vi.fn()}
            >
                <p>extra</p>
            </NavigatorSidebar>
        );

        expect(
            screen.getByTestId('ap-site-editor-navigator-outlet')
        ).toHaveTextContent('extra');
    });

    it('exposes a navigation landmark with an accessible name', () => {
        render(
            <NavigatorSidebar
                activeSection="templates"
                onSelectSection={vi.fn()}
            />
        );

        expect(
            screen.getByRole('navigation', { name: 'Site editor sections' })
        ).toBeInTheDocument();
        expect(screen.getByRole('tablist')).toHaveAttribute(
            'aria-orientation',
            'vertical'
        );
    });

    it('uses a roving tabindex — only the active tab is in the tab sequence', () => {
        render(
            <NavigatorSidebar
                activeSection="patterns"
                onSelectSection={vi.fn()}
            />
        );

        const active = screen.getByTestId('ap-site-editor-navigator-patterns');
        const inactive = [
            'templates',
            'template-parts',
            'styles',
            'navigation',
        ].map((id) => screen.getByTestId(`ap-site-editor-navigator-${id}`));

        expect(active).toHaveAttribute('tabindex', '0');
        for (const tab of inactive) {
            expect(tab).toHaveAttribute('tabindex', '-1');
        }
    });

    it('emits a stable id per tab + exposes the panel id constant', () => {
        render(
            <NavigatorSidebar
                activeSection="templates"
                onSelectSection={vi.fn()}
            />
        );

        expect(
            screen.getByTestId('ap-site-editor-navigator-templates')
        ).toHaveAttribute('id', navigatorTabId('templates'));
        expect(
            screen.getByTestId('ap-site-editor-navigator-templates')
        ).toHaveAttribute('aria-controls', NAVIGATOR_PANEL_ID);
    });

    it('moves focus and selects on ArrowDown / ArrowUp', async () => {
        const user = userEvent.setup();
        const onSelectSection = vi.fn();

        render(
            <NavigatorSidebar
                activeSection="templates"
                onSelectSection={onSelectSection}
            />
        );

        const templates = screen.getByTestId(
            'ap-site-editor-navigator-templates'
        );
        templates.focus();

        await user.keyboard('{ArrowDown}');

        expect(
            screen.getByTestId('ap-site-editor-navigator-template-parts')
        ).toHaveFocus();
        expect(onSelectSection).toHaveBeenLastCalledWith('template-parts');

        await user.keyboard('{ArrowUp}');

        expect(templates).toHaveFocus();
        expect(onSelectSection).toHaveBeenLastCalledWith('templates');
    });

    it('wraps focus at the ends with ArrowDown / ArrowUp', () => {
        const onSelectSection = vi.fn();

        render(
            <NavigatorSidebar
                activeSection="navigation"
                onSelectSection={onSelectSection}
            />
        );

        const last = screen.getByTestId('ap-site-editor-navigator-navigation');
        last.focus();

        // `userEvent` doesn't always cooperate with the synchronous
        // focus assertions when the listener is on a parent ul; fire a
        // direct keydown so the bubbling target matches.
        fireEvent.keyDown(last, { key: 'ArrowDown' });

        expect(
            screen.getByTestId('ap-site-editor-navigator-templates')
        ).toHaveFocus();
        expect(onSelectSection).toHaveBeenLastCalledWith('templates');

        const first = screen.getByTestId('ap-site-editor-navigator-templates');
        fireEvent.keyDown(first, { key: 'ArrowUp' });

        expect(
            screen.getByTestId('ap-site-editor-navigator-navigation')
        ).toHaveFocus();
        expect(onSelectSection).toHaveBeenLastCalledWith('navigation');
    });

    it('jumps to the ends with Home and End', async () => {
        const user = userEvent.setup();
        const onSelectSection = vi.fn();

        render(
            <NavigatorSidebar
                activeSection="patterns"
                onSelectSection={onSelectSection}
            />
        );

        const patterns = screen.getByTestId('ap-site-editor-navigator-patterns');
        patterns.focus();

        await user.keyboard('{End}');

        expect(
            screen.getByTestId('ap-site-editor-navigator-navigation')
        ).toHaveFocus();
        expect(onSelectSection).toHaveBeenLastCalledWith('navigation');

        await user.keyboard('{Home}');

        expect(
            screen.getByTestId('ap-site-editor-navigator-templates')
        ).toHaveFocus();
        expect(onSelectSection).toHaveBeenLastCalledWith('templates');
    });
});
