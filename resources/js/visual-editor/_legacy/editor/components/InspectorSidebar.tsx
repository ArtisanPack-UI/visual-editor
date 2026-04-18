import { useMemo, useState } from 'react';
import { useStore } from 'zustand';
import { Button, Select } from '@artisanpack-ui/react/form';
import { Tabs, type TabItem } from '@artisanpack-ui/react/layout';
import { getBlock } from '../registry';
import { useEditorStore } from '../primitives';
import type { Block } from '../store';

export interface InspectorSidebarProps {
    open: boolean;
}

function findBlockInTree(blocks: Block[], clientId: string): Block | undefined {
    for (const block of blocks) {
        if (block.clientId === clientId) return block;
        const found = findBlockInTree(block.innerBlocks, clientId);
        if (found) return found;
    }
    return undefined;
}

export function InspectorSidebar({ open }: InspectorSidebarProps) {
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

    const tabs = useMemo<TabItem[]>(
        () => [
            {
                name: 'settings',
                label: 'Settings',
                content: (
                    <div data-testid="ve-inspector-panel-settings">
                        <SettingsPanel blockName={blockTitle} />
                    </div>
                ),
            },
            {
                name: 'styles',
                label: 'Styles',
                content: (
                    <div data-testid="ve-inspector-panel-styles">
                        <StylesPanel />
                    </div>
                ),
            },
        ],
        [blockTitle]
    );

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

                    <Tabs
                        tabs={tabs}
                        defaultTab="settings"
                        variant="boxed"
                        size="sm"
                        className="ve-inspector__tabs-root"
                        tabListClassName="tabs-box"
                        panelClassName="ve-inspector__content"
                    />
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
                <Select
                    value={styleState}
                    onChange={(e) => setStyleState(e.target.value as StyleState)}
                    aria-label="State"
                    data-testid="ve-inspector-state-select"
                >
                    <option value="default">Default</option>
                    <option value="hover">Hover</option>
                    <option value="focus">Focus</option>
                    <option value="active">Active</option>
                </Select>

                <div className="ve-inspector__styles-breakpoints" role="group" aria-label="Responsive breakpoints">
                    {(['desktop', 'tablet', 'mobile'] as const).map((bp) => (
                        <Button
                            key={bp}
                            size="xs"
                            color={breakpoint === bp ? 'primary' : 'ghost'}
                            onClick={() => setBreakpoint(bp)}
                            aria-pressed={breakpoint === bp}
                            data-testid={`ve-inspector-bp-${bp}`}
                            label={bp.charAt(0).toUpperCase() + bp.slice(1)}
                        />
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
