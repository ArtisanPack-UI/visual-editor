import { useMemo } from 'react';
import { Tabs, type TabItem } from '@artisanpack-ui/react/layout';
import { InserterPanel } from './InserterPanel';
import { LayersPanel } from './LayersPanel';

export function LeftSidebar() {
    const tabs = useMemo<TabItem[]>(
        () => [
            {
                name: 'blocks',
                label: 'Blocks',
                content: <InserterPanel />,
            },
            {
                name: 'patterns',
                label: 'Patterns',
                content: (
                    <div className="ve-left-sidebar__empty" data-testid="ve-patterns-panel">
                        <p>Patterns coming soon.</p>
                    </div>
                ),
            },
            {
                name: 'layers',
                label: 'Layers',
                content: <LayersPanel />,
            },
        ],
        []
    );

    return (
        <aside
            className="ve-left-sidebar"
            data-testid="ve-left-sidebar"
            aria-label="Editor sidebar"
        >
            <Tabs
                tabs={tabs}
                defaultTab="blocks"
                variant="boxed"
                size="sm"
                className="ve-left-sidebar__tabs-root"
                tabListClassName="tabs-box"
                panelClassName="ve-left-sidebar__content"
            />
        </aside>
    );
}
