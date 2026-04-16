import { useState } from 'react';
import { InserterPanel } from './InserterPanel';
import { LayersPanel } from './LayersPanel';

type LeftSidebarTab = 'blocks' | 'patterns' | 'layers';

export function LeftSidebar() {
    const [activeTab, setActiveTab] = useState<LeftSidebarTab>('blocks');

    return (
        <aside
            className="ve-left-sidebar"
            data-testid="ve-left-sidebar"
            aria-label="Editor sidebar"
        >
            <div className="ve-left-sidebar__tabs" role="tablist" aria-label="Sidebar tabs">
                <button
                    type="button"
                    role="tab"
                    className={[
                        've-left-sidebar__tab',
                        activeTab === 'blocks' ? 've-left-sidebar__tab--active' : null,
                    ].filter(Boolean).join(' ')}
                    aria-selected={activeTab === 'blocks'}
                    data-testid="ve-left-sidebar-tab-blocks"
                    onClick={() => setActiveTab('blocks')}
                >
                    Blocks
                </button>
                <button
                    type="button"
                    role="tab"
                    className={[
                        've-left-sidebar__tab',
                        activeTab === 'patterns' ? 've-left-sidebar__tab--active' : null,
                    ].filter(Boolean).join(' ')}
                    aria-selected={activeTab === 'patterns'}
                    data-testid="ve-left-sidebar-tab-patterns"
                    onClick={() => setActiveTab('patterns')}
                >
                    Patterns
                </button>
                <button
                    type="button"
                    role="tab"
                    className={[
                        've-left-sidebar__tab',
                        activeTab === 'layers' ? 've-left-sidebar__tab--active' : null,
                    ].filter(Boolean).join(' ')}
                    aria-selected={activeTab === 'layers'}
                    data-testid="ve-left-sidebar-tab-layers"
                    onClick={() => setActiveTab('layers')}
                >
                    Layers
                </button>
            </div>

            <div className="ve-left-sidebar__content">
                {activeTab === 'blocks' ? (
                    <InserterPanel />
                ) : activeTab === 'patterns' ? (
                    <div className="ve-left-sidebar__empty" data-testid="ve-patterns-panel">
                        <p>Patterns coming soon.</p>
                    </div>
                ) : (
                    <LayersPanel />
                )}
            </div>
        </aside>
    );
}
