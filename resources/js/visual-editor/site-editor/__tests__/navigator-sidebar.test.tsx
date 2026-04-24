import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { NavigatorSidebar } from '../navigator-sidebar';

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
});
