/**
 * Patterns category for the post-editor block inserter (A2 / D5).
 *
 * Replaces the M7 stub with a real list of patterns pulled from C5.
 * The category is split by sync status — synced patterns get a header
 * "Synced patterns" and unsynced patterns get "Unsynced patterns" so
 * users can scan both at a glance without a tab dance.
 *
 * Insert behaviour:
 *   - Synced pattern → drops a `core/block` reference block. The B1
 *     core-data shim resolves the reference to its block tree at
 *     render time, so any later edit to the synced pattern updates
 *     every site it's inserted on. (E3 owns frontend resolution; the
 *     editor uses Gutenberg's built-in `core/block` rendering.)
 *   - Unsynced pattern → drops a *copy* of the saved block tree. No
 *     reference is created; subsequent edits to the pattern do not
 *     propagate. The brief commits to "pure block-tree copy" for V1
 *     unsynced patterns (no bindings — plan §8 closed).
 */

import {
    createBlock,
    createBlocksFromInnerBlocksTemplate,
    parse,
    type BlockInstance,
} from '@wordpress/blocks';
import { useDispatch, useSelect } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import {
    useCallback,
    useEffect,
    useId,
    useMemo,
    useRef,
    useState,
    type KeyboardEvent as ReactKeyboardEvent,
} from 'react';

import { TEXT_DOMAIN } from '../vendor/i18n';
import {
    listPatterns,
    SiteEditorApiError,
    type PatternRecord,
} from '../site-editor/patterns/api-client';
import { PatternThumbnail } from '../site-editor/patterns/pattern-thumbnail';

import './inserter-patterns-panel.css';

export interface InserterPatternsPanelProps {
    /**
     * Base URL for the visual-editor API (e.g. `/visual-editor/api`).
     * The patterns endpoint mounts under `{apiBase}/patterns`.
     */
    apiBase: string;
}

type LoadStatus = 'idle' | 'loading' | 'ready' | 'error';

function patternTitle(pattern: PatternRecord): string {
    const rendered = pattern.title?.rendered?.trim();

    if (rendered !== undefined && rendered !== '') {
        return rendered;
    }

    return pattern.slug;
}

function patternBlocks(pattern: PatternRecord): BlockInstance[] {
    const raw = pattern.content.raw;

    if (typeof raw === 'string' && raw.trim() !== '') {
        return parse(raw);
    }

    if (Array.isArray(pattern.content.blocks)) {
        // The server-side serialization round-trip already produced
        // BlockInstance-shaped data, but TypeScript widens this to
        // `unknown[]`. Cast at the boundary — the schema validator on
        // the server enforces the block-tree shape.
        return pattern.content.blocks as BlockInstance[];
    }

    return [];
}

interface PatternPreviewCardProps {
    pattern: PatternRecord;
    label: string;
    testId: string;
    onSelect: () => void;
    onKeyDown: (event: ReactKeyboardEvent<HTMLElement>) => void;
}

/**
 * Card layout for an inserter pattern row. Renders a lightweight
 * client-side block-tree summary above the title.
 *
 * `BlockPreview` from `@wordpress/block-editor` is the WordPress-native
 * alternative, but it mounts a `blob:` iframe per card and the
 * combination of CSP isolation + multiple iframes broke the editor's
 * render tree under our shim. The issue brief explicitly calls for a
 * "lightweight client-side renderer — do NOT spawn a full editor per
 * thumbnail", so the text-tree summary is the V1 ship; a server-
 * rendered thumbnail via the M6 dynamic-blocks endpoint can replace
 * it later.
 */
function PatternPreviewCard(props: PatternPreviewCardProps): JSX.Element {
    const { pattern, label, testId, onSelect, onKeyDown } = props;

    const blocks = useMemo(
        () => patternBlocks(pattern),
        [pattern]
    );

    return (
        <button
            type="button"
            data-ap-inserter-pattern-row=""
            data-pattern-id={pattern.id}
            data-synced={pattern.synced}
            className="ap-inserter-patterns__row"
            data-testid={testId}
            aria-label={label}
            onClick={onSelect}
            onKeyDown={onKeyDown}
        >
            <div
                className="ap-inserter-patterns__preview"
                aria-hidden="true"
                data-testid={`${testId}-preview`}
            >
                <PatternThumbnail
                    blocks={blocks}
                    title={patternTitle(pattern)}
                />
            </div>
            <span className="ap-inserter-patterns__row-title">
                {patternTitle(pattern)}
            </span>
            <span className="ap-inserter-patterns__row-meta">
                <code>{pattern.slug}</code>
                {pattern.categories.length > 0 ? (
                    <span className="ap-inserter-patterns__row-categories">
                        {pattern.categories.join(', ')}
                    </span>
                ) : null}
            </span>
        </button>
    );
}

function buildSyncedReference(
    pattern: PatternRecord
): BlockInstance | null {
    try {
        return createBlock('core/block', { ref: pattern.id });
    } catch {
        return null;
    }
}

type TemplateNode = [string, Record<string, unknown>?, TemplateNode[]?];

/**
 * Recursively converts a parsed `BlockInstance` tree to the template
 * form `createBlocksFromInnerBlocksTemplate` expects. Walks the full
 * `innerBlocks` chain so deeply nested patterns (e.g. `core/columns`
 * containing `core/column` containing `core/paragraph`) round-trip
 * with their structure intact.
 */
function blocksToTemplate(blocks: readonly BlockInstance[]): TemplateNode[] {
    return blocks.map((block): TemplateNode => {
        const inner = Array.isArray(block.innerBlocks)
            ? (block.innerBlocks as BlockInstance[])
            : [];

        return [
            block.name,
            block.attributes ?? {},
            inner.length > 0 ? blocksToTemplate(inner) : [],
        ];
    });
}

function buildUnsyncedCopy(pattern: PatternRecord): BlockInstance[] {
    const blocks = patternBlocks(pattern);

    if (blocks.length === 0) {
        return [];
    }

    // `parse()` already produces fresh client ids on every run, so
    // straight-up returning the parsed tree is enough for the
    // insertion to be a clean copy. When falling back to the parsed-
    // array path we run the tree through
    // `createBlocksFromInnerBlocksTemplate` to refresh ids.
    if (typeof pattern.content.raw === 'string' && pattern.content.raw.trim() !== '') {
        return blocks;
    }

    // Convert the parsed-array form to fresh BlockInstances so two
    // insertions of the same unsynced pattern don't collide on
    // clientId. The recursive conversion preserves nested
    // `innerBlocks` rather than flattening them at depth 2.
    return createBlocksFromInnerBlocksTemplate(blocksToTemplate(blocks));
}

interface BlockEditorInsertSelectors {
    getBlockInsertionPoint?: () => {
        rootClientId?: string;
        index?: number;
    };
}

interface BlockEditorInsertActions {
    insertBlock?: (
        block: BlockInstance,
        index?: number,
        rootClientId?: string,
        updateSelection?: boolean
    ) => void;
    insertBlocks?: (
        blocks: readonly BlockInstance[],
        index?: number,
        rootClientId?: string,
        updateSelection?: boolean
    ) => void;
}

interface CoreDataActions {
    receiveEntityRecords?: (
        kind: string,
        name: string,
        records: readonly Record<string, unknown>[]
    ) => void;
}

export function InserterPatternsPanel(
    props: InserterPatternsPanelProps
): JSX.Element {
    const { apiBase } = props;

    const sectionTitleId = useId();

    const [synced, setSynced] = useState<readonly PatternRecord[]>([]);
    const [unsynced, setUnsynced] = useState<readonly PatternRecord[]>([]);
    const [status, setStatus] = useState<LoadStatus>('idle');
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const requestRef = useRef(0);

    const apiConfig = useMemo(() => ({ apiBase }), [apiBase]);

    const insertionPoint = useSelect(
        (select) => {
            const store = select('core/block-editor') as
                | BlockEditorInsertSelectors
                | undefined;

            return store?.getBlockInsertionPoint?.() ?? null;
        },
        []
    );

    const blockEditorActions = useDispatch('core/block-editor') as
        | BlockEditorInsertActions
        | null
        | undefined;
    const insertBlock = blockEditorActions?.insertBlock;
    const insertBlocks = blockEditorActions?.insertBlocks;

    const coreActions = useDispatch('core') as
        | CoreDataActions
        | null
        | undefined;
    const receiveEntityRecords = coreActions?.receiveEntityRecords;

    // Pump fetched synced patterns into the core-data shim's cache so
    // `core/block` (the synced reference block) finds the record when it
    // resolves the `ref` attribute. Without this, the block's edit
    // component renders "Block has been deleted or is unavailable" — its
    // entity-record selector returns null because nobody dispatched a
    // `receiveEntityRecords` for the freshly-fetched pattern.
    const primeEntityCache = useCallback(
        (records: readonly PatternRecord[]): void => {
            if (records.length === 0) {
                return;
            }

            if (typeof receiveEntityRecords === 'function') {
                receiveEntityRecords(
                    'postType',
                    'wp_block',
                    records as unknown as readonly Record<string, unknown>[]
                );
            }
        },
        [receiveEntityRecords]
    );

    const fetchPatterns = useCallback(async (): Promise<void> => {
        const requestId = ++requestRef.current;

        setStatus('loading');
        setErrorMessage(null);

        try {
            const [syncedList, unsyncedList] = await Promise.all([
                listPatterns(apiConfig, { synced: true, perPage: 100 }),
                listPatterns(apiConfig, { synced: false, perPage: 100 }),
            ]);

            if (requestRef.current !== requestId) {
                return;
            }

            setSynced(syncedList.data);
            setUnsynced(unsyncedList.data);
            primeEntityCache(syncedList.data);
            setStatus('ready');
        } catch (error: unknown) {
            if (requestRef.current !== requestId) {
                return;
            }

            const message =
                error instanceof SiteEditorApiError
                    ? error.message
                    : __('Failed to load patterns.', TEXT_DOMAIN);

            setSynced([]);
            setUnsynced([]);
            setStatus('error');
            setErrorMessage(message);
        }
    }, [apiConfig, primeEntityCache]);

    useEffect(() => {
        void fetchPatterns();
    }, [fetchPatterns]);

    const handleInsertSynced = useCallback(
        (pattern: PatternRecord): void => {
            const block = buildSyncedReference(pattern);

            if (block === null) {
                return;
            }

            // Prime the cache one more time at insert time so a stale
            // pattern (e.g. one created in another tab while the panel
            // was open) still resolves on the very first render of the
            // new `core/block` reference.
            primeEntityCache([pattern]);

            const root = insertionPoint?.rootClientId;
            const index = insertionPoint?.index;

            if (typeof insertBlock === 'function') {
                insertBlock(block, index, root, true);
            }
        },
        [insertBlock, insertionPoint, primeEntityCache]
    );

    const handleInsertUnsynced = useCallback(
        (pattern: PatternRecord): void => {
            const blocks = buildUnsyncedCopy(pattern);

            if (blocks.length === 0) {
                return;
            }

            const root = insertionPoint?.rootClientId;
            const index = insertionPoint?.index;

            if (typeof insertBlocks === 'function') {
                insertBlocks(blocks, index, root, true);
            }
        },
        [insertBlocks, insertionPoint]
    );

    const handleListKey = useCallback(
        (event: ReactKeyboardEvent<HTMLElement>): void => {
            if (
                event.key !== 'ArrowUp' &&
                event.key !== 'ArrowDown' &&
                event.key !== 'Home' &&
                event.key !== 'End'
            ) {
                return;
            }

            const list = event.currentTarget.closest(
                '[data-ap-inserter-pattern-list]'
            );

            if (list === null) {
                return;
            }

            const buttons = Array.from(
                list.querySelectorAll<HTMLButtonElement>(
                    'button[data-ap-inserter-pattern-row]'
                )
            );

            if (buttons.length === 0) {
                return;
            }

            const active = document.activeElement;
            const activeButton =
                active instanceof HTMLButtonElement ? active : null;
            const currentIndex =
                activeButton === null ? -1 : buttons.indexOf(activeButton);

            let nextIndex: number | null = null;

            if (event.key === 'ArrowDown') {
                nextIndex =
                    currentIndex === -1
                        ? 0
                        : (currentIndex + 1) % buttons.length;
            } else if (event.key === 'ArrowUp') {
                nextIndex =
                    currentIndex === -1
                        ? buttons.length - 1
                        : (currentIndex - 1 + buttons.length) %
                          buttons.length;
            } else if (event.key === 'Home') {
                nextIndex = 0;
            } else if (event.key === 'End') {
                nextIndex = buttons.length - 1;
            }

            if (nextIndex === null) {
                return;
            }

            event.preventDefault();
            buttons[nextIndex]?.focus();
        },
        []
    );

    const isLoading = status === 'loading' || status === 'idle';
    const isError = status === 'error';
    const isEmpty =
        status === 'ready' && synced.length === 0 && unsynced.length === 0;

    return (
        <div
            className="ap-inserter-patterns"
            data-testid="ap-inserter-patterns"
            aria-labelledby={sectionTitleId}
        >
            <h3
                id={sectionTitleId}
                className="ap-inserter-patterns__heading"
            >
                {__('Patterns', TEXT_DOMAIN)}
            </h3>

            {isLoading ? (
                <p
                    role="status"
                    aria-live="polite"
                    className="ap-inserter-patterns__status"
                    data-testid="ap-inserter-patterns-loading"
                >
                    {__('Loading patterns…', TEXT_DOMAIN)}
                </p>
            ) : null}

            {isError ? (
                <div
                    className="ap-inserter-patterns__error"
                    role="alert"
                    data-testid="ap-inserter-patterns-error"
                >
                    <p>
                        {errorMessage ??
                            __('Failed to load patterns.', TEXT_DOMAIN)}
                    </p>
                    <button
                        type="button"
                        className="ap-inserter-patterns__retry"
                        onClick={() => void fetchPatterns()}
                    >
                        {__('Retry', TEXT_DOMAIN)}
                    </button>
                </div>
            ) : null}

            {isEmpty ? (
                <p
                    className="ap-inserter-patterns__empty"
                    data-testid="ap-inserter-patterns-empty"
                >
                    {__(
                        'No patterns yet. Create one from the site editor or by selecting blocks and using "Convert to pattern".',
                        TEXT_DOMAIN
                    )}
                </p>
            ) : null}

            {status === 'ready' && synced.length > 0 ? (
                <section className="ap-inserter-patterns__group">
                    <h4 className="ap-inserter-patterns__group-title">
                        {__('Synced patterns', TEXT_DOMAIN)}
                    </h4>
                    <ul
                        className="ap-inserter-patterns__list"
                        data-ap-inserter-pattern-list=""
                        data-testid="ap-inserter-patterns-list-synced"
                    >
                        {synced.map((pattern) => (
                            <li key={pattern.id}>
                                <PatternPreviewCard
                                    pattern={pattern}
                                    label={sprintf(
                                        /* translators: %s: pattern title. */
                                        __(
                                            'Insert synced pattern: %s',
                                            TEXT_DOMAIN
                                        ),
                                        patternTitle(pattern)
                                    )}
                                    testId={`ap-inserter-patterns-row-synced-${pattern.id}`}
                                    onSelect={() => handleInsertSynced(pattern)}
                                    onKeyDown={handleListKey}
                                />
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}

            {status === 'ready' && unsynced.length > 0 ? (
                <section className="ap-inserter-patterns__group">
                    <h4 className="ap-inserter-patterns__group-title">
                        {__('Unsynced patterns', TEXT_DOMAIN)}
                    </h4>
                    <ul
                        className="ap-inserter-patterns__list"
                        data-ap-inserter-pattern-list=""
                        data-testid="ap-inserter-patterns-list-unsynced"
                    >
                        {unsynced.map((pattern) => (
                            <li key={pattern.id}>
                                <PatternPreviewCard
                                    pattern={pattern}
                                    label={sprintf(
                                        /* translators: %s: pattern title. */
                                        __(
                                            'Insert unsynced pattern: %s',
                                            TEXT_DOMAIN
                                        ),
                                        patternTitle(pattern)
                                    )}
                                    testId={`ap-inserter-patterns-row-unsynced-${pattern.id}`}
                                    onSelect={() =>
                                        handleInsertUnsynced(pattern)
                                    }
                                    onKeyDown={handleListKey}
                                />
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}
        </div>
    );
}
