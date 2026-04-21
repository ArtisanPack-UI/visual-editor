import { fireEvent, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';

// Lightweight stand-ins for `__experimentalLibrary` and
// `__experimentalListView`. The real components pull in the full
// `core/block-editor` store, the blocks registry, async render
// pipelines, and several layout effects. The shell behaviour (tab
// switching, patterns stub, ListView integration, and the library
// being mounted with the expected props) is what this test owns.
const libraryProps: Array<Record<string, unknown>> = [];
const listViewProps: Array<Record<string, unknown>> = [];
const inserted: Array<{ name: string; source: 'click' | 'drag' }> = [];
const searched: string[] = [];
const categoryClicks: string[] = [];
const reorders: Array<{ clientId: string; direction: 'up' | 'down' }> = [];

vi.mock('@wordpress/block-editor', () => ({
    __experimentalLibrary: (props: Record<string, unknown>) => {
        libraryProps.push(props);

        return (
            <div
                data-testid="ap-visual-editor-inserter-library-stub"
                className="block-editor-tabbed-sidebar"
            >
                <div className="block-editor-tabbed-sidebar__tablist-and-close-button">
                    <div
                        role="tablist"
                        className="block-editor-tabbed-sidebar__tablist"
                        data-testid="library-inner-tablist"
                    >
                        <button type="button">Blocks</button>
                    </div>
                </div>
                <input
                    type="search"
                    placeholder="Search"
                    aria-label="Search"
                    data-testid="library-search"
                    onChange={(event) => searched.push(event.target.value)}
                />
                <section data-testid="library-category-text">
                    <h2>Text</h2>
                    <button
                        type="button"
                        data-testid="library-block-paragraph"
                        draggable
                        onClick={() =>
                            inserted.push({
                                name: 'core/paragraph',
                                source: 'click',
                            })
                        }
                        onDragStart={() =>
                            inserted.push({
                                name: 'core/paragraph',
                                source: 'drag',
                            })
                        }
                    >
                        Paragraph
                    </button>
                </section>
                <section data-testid="library-category-media">
                    <h2
                        onClick={() => categoryClicks.push('media')}
                        role="button"
                        tabIndex={0}
                    >
                        Media
                    </h2>
                </section>
                <section data-testid="library-category-most-used">
                    <h2>Most Used</h2>
                </section>
            </div>
        );
    },
    __experimentalListView: (props: Record<string, unknown>) => {
        listViewProps.push(props);

        return (
            <div data-testid="ap-visual-editor-list-view-stub">
                <div
                    role="treegrid"
                    aria-label="Block list"
                    data-testid="list-view-tree"
                >
                    <button
                        type="button"
                        data-testid="list-view-row"
                        draggable
                        onDragStart={() =>
                            reorders.push({
                                clientId: 'block-1',
                                direction: 'up',
                            })
                        }
                    >
                        Group block
                    </button>
                </div>
            </div>
        );
    },
}));

import { BlockLibrarySidebar } from '../block-library-sidebar';

afterEach(() => {
    libraryProps.length = 0;
    listViewProps.length = 0;
    inserted.length = 0;
    searched.length = 0;
    categoryClicks.length = 0;
    reorders.length = 0;
});

function renderSidebar(overrides: {
    initialTab?: 'blocks' | 'patterns' | 'layouts';
} = {}) {
    return render(
        <BlockLibrarySidebar initialTab={overrides.initialTab} />
    );
}

describe('<BlockLibrarySidebar />', () => {
    it('exposes an accessible aside with a three-tab tablist', () => {
        renderSidebar();

        expect(
            screen.getByRole('complementary', { name: 'Block library' })
        ).toBeInTheDocument();

        const tablist = screen.getByRole('tablist', {
            name: 'Block library tabs',
        });

        const tabs = within(tablist).getAllByRole('tab');

        expect(tabs).toHaveLength(3);
        expect(tabs[0]).toHaveTextContent('Blocks');
        expect(tabs[1]).toHaveTextContent('Patterns');
        expect(tabs[2]).toHaveTextContent('Layouts');
    });

    it('opens on the Blocks tab by default', () => {
        renderSidebar();

        expect(
            screen.getByTestId('ap-visual-editor-block-library-tab-blocks')
        ).toHaveAttribute('aria-selected', 'true');
        expect(
            screen.getByTestId('ap-visual-editor-inserter-library-stub')
        ).toBeInTheDocument();
    });

    it('mounts the inserter library with showMostUsedBlocks and an initial blocks tab', () => {
        renderSidebar();

        expect(libraryProps).toHaveLength(1);
        expect(libraryProps[0]?.showMostUsedBlocks).toBe(true);
        expect(libraryProps[0]?.__experimentalInitialTab).toBe('blocks');
    });

    it('renders the search input, category headers, and Most Used section inside the Blocks tab', () => {
        renderSidebar();

        const panel = screen.getByTestId(
            'ap-visual-editor-block-library-blocks-panel'
        );

        expect(within(panel).getByTestId('library-search')).toBeInTheDocument();
        expect(
            within(panel).getByTestId('library-category-text')
        ).toBeInTheDocument();
        expect(
            within(panel).getByTestId('library-category-most-used')
        ).toBeInTheDocument();
    });

    it('forwards search input to the inserter (search filters blocks)', async () => {
        const user = userEvent.setup();

        renderSidebar();

        await user.type(screen.getByTestId('library-search'), 'para');

        // Each keystroke fires a change event with the running value; the
        // final entry is what the inserter sees as its filter.
        expect(searched.at(-1)).toBe('para');
        expect(searched.length).toBeGreaterThan(0);
    });

    it('routes click-insert through the inserter library', async () => {
        const user = userEvent.setup();

        renderSidebar();

        await user.click(screen.getByTestId('library-block-paragraph'));

        expect(inserted).toEqual([
            { name: 'core/paragraph', source: 'click' },
        ]);
    });

    it('routes drag-insert through the inserter library', () => {
        renderSidebar();

        fireEvent.dragStart(screen.getByTestId('library-block-paragraph'));

        expect(inserted).toEqual([
            { name: 'core/paragraph', source: 'drag' },
        ]);
    });

    it('activates category headers when clicked (category filter)', async () => {
        const user = userEvent.setup();

        renderSidebar();

        await user.click(
            within(screen.getByTestId('library-category-media')).getByText(
                'Media'
            )
        );

        expect(categoryClicks).toContain('media');
    });

    it('switches to the Patterns stub when the Patterns tab is clicked', async () => {
        const user = userEvent.setup();

        renderSidebar();

        await user.click(
            screen.getByTestId('ap-visual-editor-block-library-tab-patterns')
        );

        expect(
            screen.getByTestId('ap-visual-editor-block-library-patterns-panel')
        ).not.toHaveAttribute('hidden');
        expect(
            screen.getByTestId('ap-visual-editor-block-library-patterns-stub')
        ).toHaveTextContent(/pattern library lands in phase d/i);
    });

    it('keeps the Blocks panel mounted when another tab is active (preserves library state)', async () => {
        const user = userEvent.setup();

        renderSidebar();

        await user.click(
            screen.getByTestId('ap-visual-editor-block-library-tab-layouts')
        );

        // The blocks panel is hidden but not unmounted, so the stub and its
        // search/category children stay in the DOM across tab switches.
        expect(
            screen.getByTestId('ap-visual-editor-block-library-blocks-panel')
        ).toHaveAttribute('hidden');
        expect(
            screen.getByTestId('ap-visual-editor-inserter-library-stub')
        ).toBeInTheDocument();
    });

    it('renders the block ListView in the Layouts tab with drag-and-drop enabled', async () => {
        const user = userEvent.setup();

        renderSidebar();

        await user.click(
            screen.getByTestId('ap-visual-editor-block-library-tab-layouts')
        );

        const panel = screen.getByTestId(
            'ap-visual-editor-block-library-layouts-panel'
        );

        expect(
            within(panel).getByTestId('ap-visual-editor-list-view-stub')
        ).toBeInTheDocument();
        // `showBlockMovers` is what makes rows drag-and-drop reorderable;
        // `isExpanded` keeps every branch open by default so authors see
        // the whole document without clicking to expand.
        expect(listViewProps[0]?.showBlockMovers).toBe(true);
        expect(listViewProps[0]?.isExpanded).toBe(true);
    });

    it('forwards a drag interaction on the list view to the block-editor store', () => {
        renderSidebar({ initialTab: 'layouts' });

        fireEvent.dragStart(screen.getByTestId('list-view-row'));

        expect(reorders).toEqual([
            { clientId: 'block-1', direction: 'up' },
        ]);
    });

    it('supports ArrowRight/ArrowLeft/Home/End keyboard navigation across tabs', async () => {
        const user = userEvent.setup();

        renderSidebar();

        const blocksTab = screen.getByTestId(
            'ap-visual-editor-block-library-tab-blocks'
        );
        const patternsTab = screen.getByTestId(
            'ap-visual-editor-block-library-tab-patterns'
        );
        const layoutsTab = screen.getByTestId(
            'ap-visual-editor-block-library-tab-layouts'
        );

        blocksTab.focus();
        await user.keyboard('{ArrowRight}');

        expect(patternsTab).toHaveFocus();
        expect(patternsTab).toHaveAttribute('aria-selected', 'true');

        await user.keyboard('{End}');

        expect(layoutsTab).toHaveFocus();
        expect(layoutsTab).toHaveAttribute('aria-selected', 'true');

        await user.keyboard('{Home}');

        expect(blocksTab).toHaveFocus();
        expect(blocksTab).toHaveAttribute('aria-selected', 'true');

        // Wrap-around: ArrowLeft from the first tab lands on the last one.
        await user.keyboard('{ArrowLeft}');

        expect(layoutsTab).toHaveFocus();
        expect(layoutsTab).toHaveAttribute('aria-selected', 'true');
    });

    it('honours the initialTab prop', () => {
        renderSidebar({ initialTab: 'layouts' });

        expect(
            screen.getByTestId('ap-visual-editor-block-library-tab-layouts')
        ).toHaveAttribute('aria-selected', 'true');
    });
});
