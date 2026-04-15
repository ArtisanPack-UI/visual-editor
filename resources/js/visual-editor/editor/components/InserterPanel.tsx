import {
    useCallback,
    useMemo,
    useState,
    useSyncExternalStore,
    type ChangeEvent,
} from 'react';
import { useEditorStore } from '../primitives';
import {
    getInserterBlocks,
    subscribeInserterBlocks,
    filterInserterBlocks,
    insertBlockAtSelection,
    type InserterBlock,
} from '../inserter';

export interface InserterPanelProps {
    className?: string;
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
        <aside
            className={['ve-inserter-panel', className].filter(Boolean).join(' ')}
            data-ve-inserter-panel=""
            aria-label="Block inserter"
        >
            <header className="ve-inserter-panel__header">
                <h2 className="ve-inserter-panel__title">Add block</h2>
                <label className="ve-inserter-panel__search">
                    <span className="ve-sr-only">Search blocks</span>
                    <input
                        type="search"
                        value={query}
                        onChange={onQueryChange}
                        placeholder="Search blocks"
                        aria-label="Search blocks"
                        data-testid="ve-inserter-search"
                    />
                </label>
            </header>
            <ul
                className="ve-inserter-panel__list"
                data-testid="ve-inserter-list"
                role="list"
            >
                {filtered.length === 0 ? (
                    <li className="ve-inserter-panel__empty" data-testid="ve-inserter-empty">
                        No blocks match &ldquo;{query}&rdquo;
                    </li>
                ) : (
                    filtered.map((block) => (
                        <li key={block.name}>
                            <button
                                type="button"
                                className="ve-inserter-panel__item"
                                data-testid={`ve-inserter-item-${block.name}`}
                                onClick={() => onInsert(block)}
                            >
                                <span className="ve-inserter-panel__item-title">
                                    {block.title}
                                </span>
                                {block.description ? (
                                    <span className="ve-inserter-panel__item-description">
                                        {block.description}
                                    </span>
                                ) : null}
                            </button>
                        </li>
                    ))
                )}
            </ul>
        </aside>
    );
}
