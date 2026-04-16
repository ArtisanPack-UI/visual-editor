import { useState } from 'react';
import { useStore } from 'zustand';
import { getBlock } from '../registry';
import { useEditorStore } from '../primitives';
import type { Block } from '../store';

export interface InspectorSidebarProps {
    open: boolean;
}

type InspectorTab = 'settings' | 'styles';

function findBlockInTree(blocks: Block[], clientId: string): Block | undefined {
    for (const block of blocks) {
        if (block.clientId === clientId) return block;
        const found = findBlockInTree(block.innerBlocks, clientId);
        if (found) return found;
    }
    return undefined;
}

export function InspectorSidebar({ open }: InspectorSidebarProps) {
    const [activeTab, setActiveTab] = useState<InspectorTab>('settings');
    const store = useEditorStore();

    const selectedBlock = useStore(store, (state) => {
        if (!state.selection.clientId) {
            return null;
        }
        return findBlockInTree(state.blocks, state.selection.clientId) ?? null;
    });

    const blockTitle = selectedBlock
        ? getBlock(selectedBlock.name)?.title ?? selectedBlock.name
        : null;

    return (
        <aside
            className={[
                've-inspector',
                open ? 've-inspector--open' : 've-inspector--closed',
            ].join(' ')}
            data-testid="ve-inspector"
            aria-label="Block inspector"
            aria-hidden={!open}
        >
            {open ? (
                <div className="ve-inspector__inner">
                    <header className="ve-inspector__header">
                        <h2 className="ve-inspector__title">
                            {blockTitle ?? 'Document'}
                        </h2>
                    </header>

                    <div className="ve-inspector__tabs" role="tablist" aria-label="Inspector tabs">
                        <button
                            type="button"
                            role="tab"
                            id="ve-inspector-tab-settings"
                            className={[
                                've-inspector__tab',
                                activeTab === 'settings' ? 've-inspector__tab--active' : null,
                            ].filter(Boolean).join(' ')}
                            aria-selected={activeTab === 'settings'}
                            aria-controls="ve-inspector-panel-settings"
                            data-testid="ve-inspector-tab-settings"
                            onClick={() => setActiveTab('settings')}
                        >
                            Settings
                        </button>
                        <button
                            type="button"
                            role="tab"
                            id="ve-inspector-tab-styles"
                            className={[
                                've-inspector__tab',
                                activeTab === 'styles' ? 've-inspector__tab--active' : null,
                            ].filter(Boolean).join(' ')}
                            aria-selected={activeTab === 'styles'}
                            aria-controls="ve-inspector-panel-styles"
                            data-testid="ve-inspector-tab-styles"
                            onClick={() => setActiveTab('styles')}
                        >
                            Styles
                        </button>
                    </div>

                    <div className="ve-inspector__content">
                        {activeTab === 'settings' ? (
                            <div
                                id="ve-inspector-panel-settings"
                                role="tabpanel"
                                aria-labelledby="ve-inspector-tab-settings"
                                data-testid="ve-inspector-panel-settings"
                            >
                                <SettingsPanel blockName={blockTitle} />
                            </div>
                        ) : (
                            <div
                                id="ve-inspector-panel-styles"
                                role="tabpanel"
                                aria-labelledby="ve-inspector-tab-styles"
                                data-testid="ve-inspector-panel-styles"
                            >
                                <StylesPanel />
                            </div>
                        )}
                    </div>
                </div>
            ) : null}
        </aside>
    );
}

function SettingsPanel({ blockName }: { blockName: string | null }) {
    if (!blockName) {
        return (
            <div className="ve-inspector__empty">
                <p>Select a block to view its settings.</p>
            </div>
        );
    }

    return (
        <div className="ve-inspector__empty">
            <p>No settings available for {blockName}.</p>
        </div>
    );
}

type StyleState = 'default' | 'hover' | 'focus' | 'active';
type Breakpoint = 'desktop' | 'tablet' | 'mobile';

function StylesPanel() {
    const [styleState, setStyleState] = useState<StyleState>('default');
    const [breakpoint, setBreakpoint] = useState<Breakpoint>('desktop');

    return (
        <div className="ve-inspector__styles">
            <div className="ve-inspector__styles-controls">
                <label className="ve-inspector__styles-label">
                    <span className="ve-sr-only">State</span>
                    <select
                        value={styleState}
                        onChange={(e) => setStyleState(e.target.value as StyleState)}
                        className="ve-inspector__styles-select"
                        data-testid="ve-inspector-state-select"
                    >
                        <option value="default">Default</option>
                        <option value="hover">Hover</option>
                        <option value="focus">Focus</option>
                        <option value="active">Active</option>
                    </select>
                </label>

                <div className="ve-inspector__styles-breakpoints" role="group" aria-label="Responsive breakpoints">
                    {(['desktop', 'tablet', 'mobile'] as const).map((bp) => (
                        <button
                            key={bp}
                            type="button"
                            className={[
                                've-inspector__styles-bp',
                                breakpoint === bp ? 've-inspector__styles-bp--active' : null,
                            ].filter(Boolean).join(' ')}
                            onClick={() => setBreakpoint(bp)}
                            aria-pressed={breakpoint === bp}
                            data-testid={`ve-inspector-bp-${bp}`}
                        >
                            {bp.charAt(0).toUpperCase() + bp.slice(1)}
                        </button>
                    ))}
                </div>
            </div>

            <div className="ve-inspector__empty">
                <p>Style controls coming in Phase 4.</p>
                <p className="ve-inspector__empty-meta">
                    State: {styleState} | Breakpoint: {breakpoint}
                </p>
            </div>
        </div>
    );
}
