/**
 * Visual editor React app.
 *
 * Mounts a `BlockEditorProvider` for a single resource+id pair and wires the
 * persistence loop so keystrokes hit Laravel via debounced PUTs. M7 (#317)
 * added the top bar chrome; A1 (#343) moved the title input into the canvas
 * and introduced the real inspector sidebar (block + document tabs) in place
 * of the M7 placeholder.
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
    ObserveTyping,
    WritingFlow,
} from '@wordpress/block-editor';
import { registerCoreBlocks } from '@wordpress/block-library';
import { Popover, SlotFillProvider } from '@wordpress/components';
// Importing `@wordpress/format-library` is a side-effect — it registers
// the core rich-text formats (bold, italic, link, inline code, etc.) so
// the block toolbar's inline formatting controls render inside RichText
// blocks. Without this import the toolbar renders but the formats are
// empty (#343).
import '@wordpress/format-library';
import { __ } from '@wordpress/i18n';
import type { BlockInstance } from '@wordpress/blocks';

import { addFilter } from '@wordpress/hooks';

import { bootI18n, TEXT_DOMAIN } from '../vendor/i18n';
import {
    ensureMediaBridgeFilter,
    mediaUploadSetting,
} from '../media-bridge';

import {
    DocumentPanels,
    type AuthorOption,
    type DocumentSupports,
    type FeaturedImageValue,
    type PostStatus,
} from './document-panels';
import { InspectorSidebar } from './inspector-sidebar';
import { KeyboardShortcutsModal } from './keyboard-shortcuts-modal';
import { PostTitle } from './post-title';
import { useSaveNotifications } from './save-notifications';
import { TopBar } from './top-bar';
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

export type { PostStatus, FeaturedImageValue, AuthorOption, DocumentSupports };

let blocksRegistered = false;

/**
 * Filter namespace used by `disableContrastCheckerOnBlocks` below so the
 * registration is idempotent across HMR reloads.
 */
const CONTRAST_CHECKER_FILTER_NAMESPACE =
    'artisanpack-ui/visual-editor/disable-contrast-checker';

/**
 * Turn off `BlockColorContrastChecker` for every block that opts into
 * color support. The checker (block-editor v15.x) has a deps-less
 * `useLayoutEffect` that schedules a double-`requestAnimationFrame`
 * setColors chain on every render. During a color-picker drag those
 * RAF callbacks pile up faster than React can settle, tripping its
 * "Maximum update depth exceeded" guard and crashing the block via
 * `BlockCrashBoundary`. Disabling the checker via block supports stops
 * the component from mounting at all, which sidesteps the bug until
 * Gutenberg lands the upstream fix.
 */
function disableContrastCheckerOnBlocks(): void {
    addFilter(
        'blocks.registerBlockType',
        CONTRAST_CHECKER_FILTER_NAMESPACE,
        (settings: { supports?: Record<string, unknown> } | null | undefined) => {
            if (
                settings === null ||
                settings === undefined ||
                typeof settings !== 'object'
            ) {
                return settings;
            }

            const supports = settings.supports;

            if (
                supports === null ||
                supports === undefined ||
                typeof supports !== 'object' ||
                !('color' in supports)
            ) {
                return settings;
            }

            const color = (supports as { color: unknown }).color;
            const normalizedColor =
                color === true ? {} : color === null || color === undefined
                    ? null
                    : typeof color === 'object' ? { ...color } : null;

            if (normalizedColor === null) {
                return settings;
            }

            return {
                ...settings,
                supports: {
                    ...supports,
                    color: {
                        ...normalizedColor,
                        enableContrastChecker: false,
                    },
                },
            };
        }
    );
}

function registerOnce(): void {
    if (blocksRegistered) {
        return;
    }

    bootI18n();
    ensureMediaBridgeFilter();
    // Install the filter *before* `registerCoreBlocks` so the override
    // reaches every block as it registers. Doing it after would leave
    // already-registered blocks with the default (buggy) checker on.
    disableContrastCheckerOnBlocks();
    registerCoreBlocks();
    blocksRegistered = true;
}

/**
 * DaisyUI-aligned color palette shared between the editor settings and
 * block-editor's `__experimentalFeatures.color.palette`. Hosts can
 * override by publishing their own settings once theme.json integration
 * (B3) lands; for V1 we seed a coherent default so new installs aren't
 * staring at an empty color picker.
 */
// Preset labels are wrapped with `__()` so the pot-extraction command
// (`composer visual-editor:pot`) picks them up and host apps that load
// translations before the editor bundle executes get the translated
// strings. When translations aren't loaded, `__()` returns the English
// source unchanged — same behaviour as the raw strings had before.
const DEFAULT_PALETTE = [
    { name: __('Base content', TEXT_DOMAIN), slug: 'base-content', color: '#1f2937' },
    { name: __('Base muted', TEXT_DOMAIN), slug: 'base-muted', color: '#6b7280' },
    { name: __('Primary', TEXT_DOMAIN), slug: 'primary', color: '#2563eb' },
    { name: __('Secondary', TEXT_DOMAIN), slug: 'secondary', color: '#64748b' },
    { name: __('Accent', TEXT_DOMAIN), slug: 'accent', color: '#9333ea' },
    { name: __('Success', TEXT_DOMAIN), slug: 'success', color: '#16a34a' },
    { name: __('Warning', TEXT_DOMAIN), slug: 'warning', color: '#d97706' },
    { name: __('Error', TEXT_DOMAIN), slug: 'error', color: '#dc2626' },
];

const DEFAULT_FONT_SIZES = [
    { name: __('Small', TEXT_DOMAIN), slug: 'small', size: '13px' },
    { name: __('Regular', TEXT_DOMAIN), slug: 'regular', size: '16px' },
    { name: __('Medium', TEXT_DOMAIN), slug: 'medium', size: '20px' },
    { name: __('Large', TEXT_DOMAIN), slug: 'large', size: '28px' },
    { name: __('Huge', TEXT_DOMAIN), slug: 'huge', size: '36px' },
];

const DEFAULT_FONT_FAMILIES = [
    {
        name: __('System', TEXT_DOMAIN),
        slug: 'system',
        fontFamily:
            'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
    },
    {
        name: __('Serif', TEXT_DOMAIN),
        slug: 'serif',
        fontFamily:
            'Georgia, Cambria, "Times New Roman", Times, serif',
    },
    {
        name: __('Monospaced', TEXT_DOMAIN),
        slug: 'mono',
        fontFamily:
            'ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace',
    },
];

/**
 * Block editor settings seeded with the block-support features needed to
 * light up the inspector panels (Color, Typography, Dimensions, Border,
 * Layout) and the toolbar text-align control.
 *
 * `__experimentalFeatures` is intentionally minimal: options like
 * `link`, `defaultPalette`, `defaultGradients`, and `defaultDuotone`
 * assume WordPress-core preset data that we don't ship, and turning
 * them on without that data triggers infinite render loops inside the
 * color picker (observed with the ColorGradientControl during drag).
 * Only enable a feature once the data backing it is also wired.
 */
const editorSettings = {
    mediaUpload: mediaUploadSetting,
    alignWide: true,
    // Top-level legacy keys are still read by some core blocks that
    // haven't migrated to `__experimentalFeatures`.
    colors: DEFAULT_PALETTE,
    fontSizes: DEFAULT_FONT_SIZES,
    // Minimal `__experimentalFeatures` — only the keys needed for the
    // Typography panel's font-family picker and the text-alignment
    // toolbar. Turning on more keys (border, spacing, layout, duotone,
    // default palettes) re-introduces the color-picker drag loop we're
    // hunting; keep this config narrow until the upstream fix lands.
    __experimentalFeatures: {
        color: {
            custom: true,
            text: true,
            background: true,
        },
        typography: {
            customFontSize: true,
            fontStyle: true,
            fontWeight: true,
            letterSpacing: true,
            lineHeight: true,
            textAlign: true,
            textDecoration: true,
            textTransform: true,
            fontSizes: { custom: DEFAULT_FONT_SIZES },
            fontFamilies: { custom: DEFAULT_FONT_FAMILIES },
        },
    },
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
    excerpt: string;
    featuredImage: FeaturedImageValue | null;
    authorId: number | string | null;
    commentsOpen: boolean;
}

export interface EditorAppProps {
    apiBase: string;
    resource: string;
    id: string;
    initialTitle?: string;
    initialSlug?: string;
    initialStatus?: PostStatus | string;
    initialExcerpt?: string;
    initialFeaturedImage?: FeaturedImageValue | null;
    initialAuthorId?: number | string | null;
    initialCommentsOpen?: boolean;
    authorOptions?: ReadonlyArray<AuthorOption>;
    supports?: DocumentSupports;
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
        initialExcerpt = '',
        initialFeaturedImage = null,
        initialAuthorId = null,
        initialCommentsOpen = true,
        authorOptions,
        supports,
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
        queueBlocksForSave,
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
    const [excerpt, setExcerpt] = useState<string>(initialExcerpt);
    const [featuredImage, setFeaturedImage] = useState<FeaturedImageValue | null>(
        initialFeaturedImage
    );
    const [authorId, setAuthorId] = useState<number | string | null>(initialAuthorId);
    const [commentsOpen, setCommentsOpen] = useState<boolean>(initialCommentsOpen);
    const [inserterOpen, setInserterOpen] = useState<boolean>(false);
    const [inspectorOpen, setInspectorOpen] = useState<boolean>(true);
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

    const currentMetadata = useMemo(
        (): MetadataChange => ({
            title,
            slug,
            status,
            excerpt,
            featuredImage,
            authorId,
            commentsOpen,
        }),
        [authorId, commentsOpen, excerpt, featuredImage, slug, status, title]
    );

    const emitMetadata = useCallback(
        (next: Partial<MetadataChange>): void => {
            if (onMetadataChange === undefined) {
                return;
            }

            onMetadataChange({
                ...currentMetadata,
                ...next,
            });
        },
        [currentMetadata, onMetadataChange]
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

    const handleExcerptChange = useCallback(
        (value: string): void => {
            setExcerpt(value);
            emitMetadata({ excerpt: value });
        },
        [emitMetadata]
    );

    const handleFeaturedImageChange = useCallback(
        (value: FeaturedImageValue | null): void => {
            setFeaturedImage(value);
            emitMetadata({ featuredImage: value });
        },
        [emitMetadata]
    );

    const handleAuthorChange = useCallback(
        (value: number | string | null): void => {
            setAuthorId(value);
            emitMetadata({ authorId: value });
        },
        [emitMetadata]
    );

    const handleCommentsOpenChange = useCallback(
        (value: boolean): void => {
            setCommentsOpen(value);
            emitMetadata({ commentsOpen: value });
        },
        [emitMetadata]
    );

    const handleInput = useCallback(
        (next: BlockInstance[]): void => {
            // `onInput` fires on intermediate, non-persistent changes —
            // color-picker drag frames, mid-typing rich-text updates, etc.
            // Calling `setBlocks(next)` here re-renders the entire editor
            // tree (TopBar, InspectorSidebar, BlockInspector panels) on
            // every frame, which triggers a runaway layout-effect cascade
            // inside Gutenberg's block-support hooks and crashes the
            // block with "Maximum update depth exceeded" (#343 A1, #347).
            //
            // The block-editor store already has the latest tree from
            // the dispatch that triggered this callback, so the canvas
            // stays in sync without our mirror. We only need to queue a
            // debounced save — undo/redo still works because `onChange`
            // (fired on the persistent commit at the end of the drag)
            // does the React state update and history push.
            //
            // Invariant: letting the `value` prop go stale between
            // commits is SAFE. `use-block-sync` only reacts to
            // reference-changes of `controlledBlocks` (its effect deps
            // are `[controlledBlocks, clientId]`); when `value` doesn't
            // change, the sync effect is a no-op and the internal
            // store keeps itself consistent. Any attempt to "mirror"
            // per-frame into a transient state reintroduces the
            // cascade we're explicitly avoiding — there's no React
            // pattern that feeds a reactive `value` prop without
            // triggering a render.
            queueBlocksForSave(next);
        },
        [queueBlocksForSave]
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
        ]
    );

    const shortcutsModal = (
        <KeyboardShortcutsModal
            open={shortcutsOpen}
            onClose={handleCloseShortcuts}
        />
    );

    const documentPanels = (
        <DocumentPanels
            status={status}
            slug={slug}
            onStatusChange={handleStatusChange}
            onSlugChange={handleSlugChange}
            authorId={authorId}
            authorOptions={authorOptions}
            onAuthorChange={handleAuthorChange}
            excerpt={excerpt}
            onExcerptChange={handleExcerptChange}
            featuredImage={featuredImage}
            onFeaturedImageChange={handleFeaturedImageChange}
            commentsOpen={commentsOpen}
            onCommentsOpenChange={handleCommentsOpenChange}
            supports={supports}
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
                            <PostTitle value={title} onChange={handleTitleChange} />
                            <BlockTools>
                                <WritingFlow>
                                    <ObserveTyping>
                                        <BlockList />
                                    </ObserveTyping>
                                </WritingFlow>
                            </BlockTools>
                        </div>
                        <Popover.Slot />
                        {inspectorOpen ? (
                            <div
                                className="ap-visual-editor__sidebar ap-visual-editor__sidebar--inspector"
                                data-testid="ap-visual-editor-inspector-panel"
                            >
                                <InspectorSidebar documentContent={documentPanels} />
                            </div>
                        ) : null}
                    </BlockEditorProvider>
                </div>
                {shortcutsModal}
            </div>
        </SlotFillProvider>
    );
}
