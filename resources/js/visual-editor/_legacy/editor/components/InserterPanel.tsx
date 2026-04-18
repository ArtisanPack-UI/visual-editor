import {
    useCallback,
    useMemo,
    useState,
    useSyncExternalStore,
    type ChangeEvent,
} from 'react';
import { Input } from '@artisanpack-ui/react/form';
import { useEditorStore } from '../primitives';
import { getBlock } from '../registry';
import {
    getInserterBlocks,
    subscribeInserterBlocks,
    filterInserterBlocks,
    insertBlockAtSelection,
    type InserterBlock,
} from '../inserter';
import { Icon } from './Icon';
import { resolveBlockIcon } from './blockIconMap';

export interface InserterPanelProps {
    className?: string;
}

interface CategoryGroup {
    category: string;
    label: string;
    blocks: InserterBlock[];
}

const CATEGORY_ORDER: Record<string, number> = {
    text: 0,
    media: 1,
    design: 2,
    widgets: 3,
    theme: 4,
    embed: 5,
};

const CATEGORY_LABELS: Record<string, string> = {
    text: 'Text',
    media: 'Media',
    design: 'Design',
    widgets: 'Widgets',
    theme: 'Theme',
    embed: 'Embed',
};

function groupByCategory(blocks: InserterBlock[]): CategoryGroup[] {
    const groups = new Map<string, InserterBlock[]>();

    for (const block of blocks) {
        const def = getBlock(block.name);
        const category = def?.category ?? 'uncategorized';
        const existing = groups.get(category);

        if (existing) {
            existing.push(block);
        } else {
            groups.set(category, [block]);
        }
    }

    return Array.from(groups.entries())
        .map(([category, categoryBlocks]) => ({
            category,
            label: CATEGORY_LABELS[category] ?? category.charAt(0).toUpperCase() + category.slice(1),
            blocks: categoryBlocks,
        }))
        .sort((a, b) => {
            const orderA = CATEGORY_ORDER[a.category] ?? 99;
            const orderB = CATEGORY_ORDER[b.category] ?? 99;
            return orderA - orderB;
        });
}

export function InserterPanel({ className }: InserterPanelProps) {
    const store = useEditorStore();
    const inserterBlocks = useSyncExternalStore(
        subscribeInserterBlocks,
        getInserterBlocks,
        getInserterBlocks
    );

    const [query, setQuery] = useState('');

    const filtered = useMemo(
        () => filterInserterBlocks(inserterBlocks, query),
        [inserterBlocks, query]
    );

    const groups = useMemo(() => groupByCategory(filtered), [filtered]);

    const onInsert = useCallback(
        (block: InserterBlock) => {
            insertBlockAtSelection(store, block.name);
        },
        [store]
    );

    const onQueryChange = useCallback((event: ChangeEvent<HTMLInputElement>) => {
        setQuery(event.target.value);
    }, []);

    return (
        <div
            className={['ve-inserter-panel', className].filter(Boolean).join(' ')}
            data-ve-inserter-panel=""
        >
            <div className="ve-inserter-panel__search">
                <Input
                    type="search"
                    value={query}
                    onChange={onQueryChange}
                    placeholder="Search blocks"
                    aria-label="Search blocks"
                    data-testid="ve-inserter-search"
                />
            </div>

            {filtered.length === 0 ? (
                <div className="ve-inserter-panel__empty" data-testid="ve-inserter-empty">
                    No blocks match &ldquo;{query}&rdquo;
                </div>
            ) : (
                groups.map((group) => (
                    <div key={group.category} className="ve-inserter-panel__category">
                        <h3 className="ve-inserter-panel__category-title">
                            {group.label}
                        </h3>
                        <div
                            className="ve-inserter-panel__grid"
                            role="list"
                            data-testid={`ve-inserter-category-${group.category}`}
                        >
                            {group.blocks.map((block) => (
                                <button
                                    key={block.name}
                                    type="button"
                                    className="ve-inserter-panel__item"
                                    role="listitem"
                                    data-testid={`ve-inserter-item-${block.name}`}
                                    onClick={() => onInsert(block)}
                                >
                                    <span className="ve-inserter-panel__item-icon">
                                        <Icon icon={resolveBlockIcon(getBlock(block.name)?.icon)} />
                                    </span>
                                    <span className="ve-inserter-panel__item-title">
                                        {block.title}
                                    </span>
                                </button>
                            ))}
                        </div>
                    </div>
                ))
            )}
        </div>
    );
}
