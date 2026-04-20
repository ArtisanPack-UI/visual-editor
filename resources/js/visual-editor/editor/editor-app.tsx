/**
 * Visual editor React app.
 *
 * Mounts a `BlockEditorProvider` for a single resource+id pair and wires the
 * persistence loop so keystrokes hit Laravel via debounced PUTs. M7 (#317)
 * adds the top bar chrome — title/slug/status inputs, save indicator,
 * undo/redo, and the inserter/inspector toggles — all controlled by this
 * component so the shell feels like a Laravel admin page, not `edit-post`.
 */

import {
    useCallback,
    useMemo,
    useRef,
    useState,
} from 'react';
import { Alert, ToastProvider } from '@artisanpack-ui/react/feedback';
import {
    BlockEditorProvider,
    BlockList,
    BlockTools,
    WritingFlow,
} from '@wordpress/block-editor';
import { registerCoreBlocks } from '@wordpress/block-library';
import { Popover, SlotFillProvider } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { BlockInstance } from '@wordpress/blocks';

import { bootI18n, TEXT_DOMAIN } from '../vendor/i18n';
import {
    ensureMediaBridgeFilter,
    mediaUploadSetting,
} from '../media-bridge';

import { KeyboardShortcutsModal } from './keyboard-shortcuts-modal';
import { useSaveNotifications } from './save-notifications';
import { TopBar, type PostStatus } from './top-bar';
import { usePersistence } from './use-persistence';

import '@wordpress/components/build-style/style.css';
import '@wordpress/block-editor/build-style/style.css';
import '@wordpress/block-editor/build-style/content.css';
import '@wordpress/block-library/build-style/style.css';
import '@wordpress/block-library/build-style/editor.css';

import './editor-app.css';
// Loaded last so DaisyUI-driven custom properties win the cascade against
// the `@wordpress/*` stylesheets above (M8 · #318).
import './visual-editor-theme.css';

let blocksRegistered = false;

function registerOnce(): void {
    if (blocksRegistered) {
        return;
    }

    bootI18n();
    ensureMediaBridgeFilter();
    registerCoreBlocks();
    blocksRegistered = true;
}

const editorSettings = {
    mediaUpload: mediaUploadSetting,
};

const VALID_STATUSES: readonly PostStatus[] = [
    'draft',
    'pending',
    'scheduled',
    'published',
    'private',
];

function normalizeStatus(value: string | undefined): PostStatus {
    if (value !== undefined) {
        const match = VALID_STATUSES.find((status) => status === value);

        if (match !== undefined) {
            return match;
        }
    }

    return 'draft';
}

export interface MetadataChange {
    title: string;
    slug: string;
    status: PostStatus;
}

export interface EditorAppProps {
    apiBase: string;
    resource: string;
    id: string;
    initialTitle?: string;
    initialSlug?: string;
    initialStatus?: PostStatus | string;
    previewUrl?: string | null;
    onMetadataChange?: (change: MetadataChange) => void;
}

interface HistoryState {
    past: BlockInstance[][];
    future: BlockInstance[][];
}

const EMPTY_HISTORY: HistoryState = { past: [], future: [] };

export function EditorApp(props: EditorAppProps): JSX.Element {
    registerOnce();

    return (
        <ToastProvider>
            <EditorAppShell {...props} />
        </ToastProvider>
    );
}

function EditorAppShell(props: EditorAppProps): JSX.Element {
    const {
        initialTitle = '',
        initialSlug = '',
        initialStatus,
        previewUrl = null,
        onMetadataChange,
    } = props;

    const {
        blocks,
        loadStatus,
        saveStatus,
        loadError,
        saveError,
        lastSavedAt,
        onBlocksChange,
        flush,
    } = usePersistence(props);

    useSaveNotifications({
        saveStatus,
        saveErrorMessage: saveError?.message ?? null,
    });

    const [title, setTitle] = useState<string>(initialTitle);
    const [slug, setSlug] = useState<string>(initialSlug);
    const [status, setStatus] = useState<PostStatus>(
        normalizeStatus(typeof initialStatus === 'string' ? initialStatus : undefined)
    );
    const [inserterOpen, setInserterOpen] = useState<boolean>(false);
    const [inspectorOpen, setInspectorOpen] = useState<boolean>(false);
    const [shortcutsOpen, setShortcutsOpen] = useState<boolean>(false);
    const [history, setHistory] = useState<HistoryState>(EMPTY_HISTORY);
    // Mirror history in a ref so undo/redo handlers can read the latest
    // snapshot without taking a dep on `history` (which would re-memoize the
    // TopBar on every edit). Writing side effects inside the setHistory
    // updater is unsafe under StrictMode double-invocation; keeping the reads
    // ref-based lets us compute the next state purely.
    const historyRef = useRef<HistoryState>(EMPTY_HISTORY);
    historyRef.current = history;

    // `onInput` fires on every keystroke; `onChange` fires on logical commits.
    // Track the last committed tree so history only records meaningful edits.
    const lastCommittedRef = useRef<BlockInstance[] | null>(null);

    const emitMetadata = useCallback(
        (next: Partial<MetadataChange>): void => {
            if (onMetadataChange === undefined) {
                return;
            }

            onMetadataChange({
                title: next.title ?? title,
                slug: next.slug ?? slug,
                status: next.status ?? status,
            });
        },
        [onMetadataChange, slug, status, title]
    );

    const handleTitleChange = useCallback(
        (value: string): void => {
            setTitle(value);
            emitMetadata({ title: value });
        },
        [emitMetadata]
    );

    const handleSlugChange = useCallback(
        (value: string): void => {
            setSlug(value);
            emitMetadata({ slug: value });
        },
        [emitMetadata]
    );

    const handleStatusChange = useCallback(
        (value: PostStatus): void => {
            setStatus(value);
            emitMetadata({ status: value });
        },
        [emitMetadata]
    );

    const handleInput = useCallback(
        (next: BlockInstance[]): void => {
            onBlocksChange(next);
        },
        [onBlocksChange]
    );

    const handleChange = useCallback(
        (next: BlockInstance[]): void => {
            // Capture `previous` before mutating the ref so the setHistory
            // updater (which may be deferred past the mutation below) reads
            // the right value. Otherwise the deferred updater sees
            // `lastCommittedRef.current === next` and never commits history.
            const previous = lastCommittedRef.current;
            lastCommittedRef.current = next;

            if (previous !== null && previous !== next) {
                const nextHistory: HistoryState = {
                    past: [...historyRef.current.past, previous],
                    future: [],
                };
                historyRef.current = nextHistory;
                setHistory(nextHistory);
            }

            onBlocksChange(next);
        },
        [onBlocksChange]
    );

    const handleUndo = useCallback((): void => {
        const current = historyRef.current;

        if (current.past.length === 0) {
            return;
        }

        const previous = current.past[current.past.length - 1];

        if (previous === undefined) {
            return;
        }

        const snapshot = lastCommittedRef.current ?? blocks;
        const nextHistory: HistoryState = {
            past: current.past.slice(0, -1),
            future: [...current.future, snapshot],
        };

        lastCommittedRef.current = previous;
        historyRef.current = nextHistory;
        setHistory(nextHistory);
        onBlocksChange(previous);
    }, [blocks, onBlocksChange]);

    const handleRedo = useCallback((): void => {
        const current = historyRef.current;

        if (current.future.length === 0) {
            return;
        }

        const next = current.future[current.future.length - 1];

        if (next === undefined) {
            return;
        }

        const snapshot = lastCommittedRef.current ?? blocks;
        const nextHistory: HistoryState = {
            past: [...current.past, snapshot],
            future: current.future.slice(0, -1),
        };

        lastCommittedRef.current = next;
        historyRef.current = nextHistory;
        setHistory(nextHistory);
        onBlocksChange(next);
    }, [blocks, onBlocksChange]);

    const handleToggleInserter = useCallback((): void => {
        setInserterOpen((open) => !open);
    }, []);

    const handleToggleInspector = useCallback((): void => {
        setInspectorOpen((open) => !open);
    }, []);

    const handleShowShortcuts = useCallback((): void => {
        setShortcutsOpen(true);
    }, []);

    const handleCloseShortcuts = useCallback((): void => {
        setShortcutsOpen(false);
    }, []);

    const topBar = useMemo(
        () => (
            <TopBar
                title={title}
                slug={slug}
                status={status}
                onTitleChange={handleTitleChange}
                onSlugChange={handleSlugChange}
                onStatusChange={handleStatusChange}
                saveStatus={saveStatus}
                lastSavedAt={lastSavedAt}
                saveErrorMessage={saveError?.message ?? null}
                canUndo={history.past.length > 0}
                canRedo={history.future.length > 0}
                onUndo={handleUndo}
                onRedo={handleRedo}
                isInserterOpen={inserterOpen}
                isInspectorOpen={inspectorOpen}
                onToggleInserter={handleToggleInserter}
                onToggleInspector={handleToggleInspector}
                previewUrl={previewUrl}
                onSave={flush}
                onShowKeyboardShortcuts={handleShowShortcuts}
            />
        ),
        [
            flush,
            handleRedo,
            handleShowShortcuts,
            handleSlugChange,
            handleStatusChange,
            handleTitleChange,
            handleToggleInserter,
            handleToggleInspector,
            handleUndo,
            history.future.length,
            history.past.length,
            inserterOpen,
            inspectorOpen,
            lastSavedAt,
            previewUrl,
            saveError,
            saveStatus,
            slug,
            status,
            title,
        ]
    );

    const shortcutsModal = (
        <KeyboardShortcutsModal
            open={shortcutsOpen}
            onClose={handleCloseShortcuts}
        />
    );

    if (loadStatus === 'loading') {
        return (
            <div className="ap-visual-editor__shell" data-state="loading">
                {topBar}
                <p className="ap-visual-editor__status ap-visual-editor__status--loading">
                    {__('Loading content…', TEXT_DOMAIN)}
                </p>
                {shortcutsModal}
            </div>
        );
    }

    if (loadStatus === 'error') {
        return (
            <div className="ap-visual-editor__shell" data-state="error">
                {topBar}
                <div
                    className="ap-visual-editor__status ap-visual-editor__status--error"
                    data-testid="ap-visual-editor-load-error"
                >
                    <Alert color="error">
                        {loadError?.message ??
                            __('Unable to load content.', TEXT_DOMAIN)}
                    </Alert>
                </div>
                {shortcutsModal}
            </div>
        );
    }

    return (
        <SlotFillProvider>
            <div
                className="ap-visual-editor__shell"
                data-inserter-open={inserterOpen}
                data-inspector-open={inspectorOpen}
            >
                {topBar}
                <div className="ap-visual-editor__body">
                    {inserterOpen ? (
                        <aside
                            className="ap-visual-editor__sidebar ap-visual-editor__sidebar--inserter"
                            aria-label={__('Block inserter', TEXT_DOMAIN)}
                            data-testid="ap-visual-editor-inserter-panel"
                        >
                            <h2 className="ap-visual-editor__sidebar-title">
                                {__('Block inserter', TEXT_DOMAIN)}
                            </h2>
                            <p className="ap-visual-editor__sidebar-note">
                                {__(
                                    'Block library UI lands in a later milestone. For now, use the “/” slash inserter inside the canvas.',
                                    TEXT_DOMAIN
                                )}
                            </p>
                        </aside>
                    ) : null}
                    <BlockEditorProvider
                        value={blocks}
                        settings={editorSettings}
                        onInput={handleInput}
                        onChange={handleChange}
                    >
                        <div className="editor-styles-wrapper ap-visual-editor__canvas">
                            <BlockTools>
                                <WritingFlow>
                                    <BlockList />
                                </WritingFlow>
                            </BlockTools>
                        </div>
                        <Popover.Slot />
                    </BlockEditorProvider>
                    {inspectorOpen ? (
                        <aside
                            className="ap-visual-editor__sidebar ap-visual-editor__sidebar--inspector"
                            aria-label={__('Block inspector', TEXT_DOMAIN)}
                            data-testid="ap-visual-editor-inspector-panel"
                        >
                            <h2 className="ap-visual-editor__sidebar-title">
                                {__('Block inspector', TEXT_DOMAIN)}
                            </h2>
                            <p className="ap-visual-editor__sidebar-note">
                                {__(
                                    'Settings UI lands in a later milestone. For now, use the block toolbar.',
                                    TEXT_DOMAIN
                                )}
                            </p>
                        </aside>
                    ) : null}
                </div>
                {shortcutsModal}
            </div>
        </SlotFillProvider>
    );
}
