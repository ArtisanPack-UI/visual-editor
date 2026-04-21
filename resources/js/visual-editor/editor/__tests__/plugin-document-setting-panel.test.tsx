import { render, screen } from '@testing-library/react';
import { SlotFillProvider } from '@wordpress/components';
import { addFilter, removeAllFilters } from '@wordpress/hooks';
import { afterEach, describe, expect, it } from 'vitest';

import {
    DOCUMENT_PANELS_FILTER,
    DocumentPanelSlot,
    PluginDocumentSettingPanel,
    getFilteredDocumentPanels,
} from '../plugin-document-setting-panel';

afterEach(() => {
    // `removeAllFilters` ignores the namespace argument at runtime when
    // called through the `removeAll` constructor, but the TS type still
    // requires one. Pass the wildcard namespace Gutenberg itself uses in
    // its own tests.
    removeAllFilters(DOCUMENT_PANELS_FILTER, '');
});

describe('getFilteredDocumentPanels', () => {
    it('returns an empty list when no filters are registered', () => {
        expect(getFilteredDocumentPanels()).toEqual([]);
    });

    it('includes panels registered via @wordpress/hooks addFilter', () => {
        addFilter(DOCUMENT_PANELS_FILTER, 'test/seo', (panels) => [
            ...panels,
            {
                id: 'test/seo',
                title: 'SEO',
                render: () => null,
            },
        ]);

        const panels = getFilteredDocumentPanels();

        expect(panels).toHaveLength(1);
        expect(panels[0]).toMatchObject({ id: 'test/seo', title: 'SEO' });
    });

    it('orders panels by their optional order field, stable within a tie', () => {
        addFilter(DOCUMENT_PANELS_FILTER, 'test/a', (panels) => [
            ...panels,
            { id: 'test/a', title: 'A', order: 50, render: () => null },
            { id: 'test/b', title: 'B', order: 10, render: () => null },
            { id: 'test/c', title: 'C', render: () => null },
            { id: 'test/d', title: 'D', order: 10, render: () => null },
        ]);

        const panels = getFilteredDocumentPanels();

        expect(panels.map((panel) => panel.id)).toEqual([
            'test/b',
            'test/d',
            'test/a',
            'test/c',
        ]);
    });

    it('ignores malformed panel entries returned by filters', () => {
        addFilter(DOCUMENT_PANELS_FILTER, 'test/bad', (panels) => [
            ...panels,
            null,
            { id: 'only-id' },
            { id: 'test/good', title: 'Good', render: () => null },
            { title: 'no id', render: () => null },
        ]);

        const panels = getFilteredDocumentPanels();

        expect(panels).toHaveLength(1);
        expect(panels[0].id).toBe('test/good');
    });

    it('deduplicates panels by id with last-wins policy, including position', () => {
        addFilter(DOCUMENT_PANELS_FILTER, 'test/dup', (panels) => [
            ...panels,
            { id: 'shared', title: 'First', render: () => 'first' },
            { id: 'unique', title: 'Unique', render: () => 'unique' },
            { id: 'shared', title: 'Second', render: () => 'second' },
        ]);

        const panels = getFilteredDocumentPanels();

        expect(panels).toHaveLength(2);

        // The later 'shared' wins AND takes the later position — the
        // earlier instance is completely replaced rather than just
        // having its value overwritten in place.
        expect(panels.map((panel) => panel.id)).toEqual(['unique', 'shared']);
        expect(panels.find((panel) => panel.id === 'shared')?.title).toBe(
            'Second'
        );
    });
});

describe('<PluginDocumentSettingPanel />', () => {
    it('renders its children through the DocumentPanelSlot', () => {
        render(
            <SlotFillProvider>
                <PluginDocumentSettingPanel
                    name="acme/demo"
                    title="Demo panel"
                    initialOpen={true}
                >
                    <p>Demo panel contents</p>
                </PluginDocumentSettingPanel>
                <DocumentPanelSlot />
            </SlotFillProvider>
        );

        expect(screen.getByText('Demo panel')).toBeInTheDocument();
        expect(screen.getByText('Demo panel contents')).toBeInTheDocument();
        expect(
            document.querySelector('[data-panel-name="acme/demo"]')
        ).not.toBeNull();
    });
});
