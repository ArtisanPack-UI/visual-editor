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
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import { Alert, ToastProvider } from '@artisanpack-ui/react/feedback';
import { BlockEditorProvider } from '@wordpress/block-editor';
import { registerCoreBlocks } from '@wordpress/block-library';
import { Popover, SlotFillProvider } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { EntityProvider } from '@wordpress/core-data';
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
import { editorSettings } from '../editor-settings';
import { ensureMediaBridgeFilter } from '../media-bridge';

import { BlockLibrarySidebar } from './block-library-sidebar';
import { registerContrastWarning } from './contrast-warning';
import { ConvertToPatternControl } from './convert-to-pattern-control';
import { discoverAndRegisterCustomBlocks } from './custom-blocks';
import { EditorCanvas } from './editor-canvas';
import { registerCoreQueryBlockOverride } from './query-block-override';
import { registerSyncedPatternIndicator } from './synced-pattern-indicator';
import { registerTaxonomyAndArchiveBlockOverrides } from './taxonomy-archive-block-overrides';
import {
    DocumentPanels,
    type AuthorOption,
    type DocumentSupports,
    type DocumentType,
    type FeaturedImageValue,
    type PostStatus,
} from './document-panels';
import { entityTypeForResource } from './entity-type';
import { InspectorSidebar } from './inspector-sidebar';
import { KeyboardShortcutsModal } from './keyboard-shortcuts-modal';
import { useSaveNotifications } from './save-notifications';
import { TopBar } from './top-bar';
import { usePersistence } from './use-persistence';

// Side-effect imports for the editor *chrome* — the top bar, block
// toolbar, and inspector sidebar that render in the parent document,
// outside the `BlockCanvas` iframe. The same stylesheets are injected
// *into* the iframe separately via `canvas-styles.ts` (#347), because
// the iframe document doesn't inherit the parent's Vite-injected
// `<style>` tags.
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
    // Replace the disabled upstream checker with our attribute-driven
    // implementation (#348). Registers an `editor.BlockEdit` HOC, so it
    // also belongs before `registerCoreBlocks` to wrap every block at
    // first render rather than retroactively.
    registerContrastWarning();
    // Register D5's synced-pattern indicator filter pre-`registerCoreBlocks`
    // for the same reason — the wrapper sees `core/block` once the
    // block registers and applies the badge from then on.
    registerSyncedPatternIndicator();
    // G4b — swap the broken upstream Edit components for
    // `core/categories`, `core/tag-cloud`, and `core/archives` with our
    // ServerSideRender-backed wrappers BEFORE `registerCoreBlocks()` so
    // the override applies during initial registration.
    registerTaxonomyAndArchiveBlockOverrides();
    // G4c-2 — same idea for `core/query`: swap the upstream Edit (which
    // pulls a heavy chain of unsupported core-data selectors) with a
    // wrapper that previews via /visual-editor/api/query/resolve.
    registerCoreQueryBlockOverride();
    registerCoreBlocks();
    // Discover host-app custom blocks under
    // `resources/js/visual-editor/blocks/{block-name}/index.ts` and
    // register them plus the `artisanpack` category. Runs after
    // `registerCoreBlocks` so custom-block category registration sees
    // the full core category list.
    discoverAndRegisterCustomBlocks();
    blocksRegistered = true;
}

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
    /** Selected category ids — surfaced for `documentType === 'post'`. */
    categories: ReadonlyArray<number>;
    /** Selected tag ids — surfaced for `documentType === 'post'`. */
    tags: ReadonlyArray<number>;
    /** Parent page id; `null` clears the relationship. Page-only. */
    parent: number | null;
    /** Page menu_order. Page-only. */
    menuOrder: number;
    /** Theme template slug applied to this page. Page-only. */
    template: string;
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
    initialCategories?: ReadonlyArray<number>;
    initialTags?: ReadonlyArray<number>;
    initialParent?: number | null;
    initialMenuOrder?: number;
    initialTemplate?: string;
    authorOptions?: ReadonlyArray<AuthorOption>;
    supports?: DocumentSupports;
    previewUrl?: string | null;
    onMetadataChange?: (change: MetadataChange) => void;
}

export { entityTypeForResource } from './entity-type';

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
        initialCategories,
        initialTags,
        initialParent = null,
        initialMenuOrder = 0,
        initialTemplate = '',
        authorOptions,
        supports,
        previewUrl = null,
        onMetadataChange,
    } = props;

    const documentType = entityTypeForResource(props.resource);
    // Validate against a whole-digit regex *before* parsing so malformed
    // ids like "42abc" don't silently promote to 42 — the EntityProvider
    // wrap below would otherwise fetch the wrong entity record.
    const numericId = useMemo<number | null>(
        () => (/^\d+$/.test(props.id) ? Number.parseInt(props.id, 10) : null),
        [props.id]
    );

    // G4a: thread the entity identity (kind/name/id) through the
    // persistence layer so sidebar metadata edits and post-title block
    // edits round-trip via the G3 entity endpoint after the block save.
    // Bypasses for non-cms-framework resources (custom HasBlockContent
    // models without a shim entity registration) — the persistence loop
    // skips the metadata flush when `entity` is null.
    const entityIdentity = useMemo(
        () =>
            documentType !== null && numericId !== null
                ? { kind: 'postType', name: documentType, id: numericId }
                : null,
        [documentType, numericId]
    );

    // Memoized block context value. Stable reference across editor-app
    // re-renders (e.g. on entity-edit dispatches) so `BlockContextProvider`
    // doesn't fan out a new context object to every block on every render
    // — the cascading `Edit` re-renders that follows clobbers the
    // block-editor's selection state, leaving the inspector's "Block" tab
    // stuck on its empty placeholder mid-edit.
    const blockContextValue = useMemo(
        () =>
            documentType !== null && numericId !== null
                ? { postType: documentType, postId: numericId }
                : null,
        [documentType, numericId]
    );

    const {
        blocks,
        loadStatus,
        saveStatus,
        loadError,
        saveError,
        lastSavedAt,
        onBlocksChange,
        queueBlocksForSave,
        queueMetadataForSave,
        flush,
    } = usePersistence({ ...props, entity: entityIdentity });

    const { editEntityRecord } = useDispatch('core') as {
        editEntityRecord?: (
            kind: string,
            name: string,
            id: number,
            edits: Record<string, unknown>
        ) => void;
    };

    /**
     * Stages a metadata edit on the core-data entity record (when the
     * editor is mounted against a cms-framework Post/Page) and schedules
     * the debounced metadata flush. Sidebar handlers below call this
     * alongside their `setLocalState` mirror so the canvas's
     * `useEntityProp`-driven blocks (e.g. `core/post-title`) and the
     * inspector share a single source of truth on save.
     */
    const stageEntityEdit = useCallback(
        (field: string, value: unknown): void => {
            if (entityIdentity === null || editEntityRecord === undefined) {
                return;
            }

            editEntityRecord(
                entityIdentity.kind,
                entityIdentity.name,
                entityIdentity.id,
                { [field]: value }
            );
            queueMetadataForSave();
        },
        [entityIdentity, editEntityRecord, queueMetadataForSave]
    );

    // Watches the entity edits bag. Blocks rendered inside the canvas
    // (e.g. `core/post-title` typing into a `PlainText`) call
    // `editEntityRecord` directly through `useEntityProp`'s setter — they
    // never reach `stageEntityEdit`. Subscribing here picks up those
    // edits and re-arms the metadata-save debounce so block-level
    // metadata edits round-trip on the same cycle as sidebar edits.
    const stagedEntityEdits = useSelect(
        (select) => {
            if (entityIdentity === null) {
                return null;
            }

            const store = select('core') as
                | {
                      getEntityRecordEdits?: (
                          kind: string,
                          name: string,
                          id: number
                      ) => Record<string, unknown> | null;
                  }
                | undefined;

            return (
                store?.getEntityRecordEdits?.(
                    entityIdentity.kind,
                    entityIdentity.name,
                    entityIdentity.id
                ) ?? null
            );
        },
        [entityIdentity]
    );

    useEffect(() => {
        if (
            stagedEntityEdits !== null
            && Object.keys(stagedEntityEdits).length > 0
        ) {
            queueMetadataForSave();
        }
    }, [stagedEntityEdits, queueMetadataForSave]);

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
    const [categories, setCategories] = useState<ReadonlyArray<number>>(
        initialCategories ?? []
    );
    const [tags, setTags] = useState<ReadonlyArray<number>>(initialTags ?? []);
    const [parent, setParent] = useState<number | null>(initialParent);
    const [menuOrder, setMenuOrder] = useState<number>(initialMenuOrder);
    const [template, setTemplate] = useState<string>(initialTemplate);
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
            categories,
            tags,
            parent,
            menuOrder,
            template,
        }),
        [
            authorId,
            categories,
            commentsOpen,
            excerpt,
            featuredImage,
            menuOrder,
            parent,
            slug,
            status,
            tags,
            template,
            title,
        ]
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
            stageEntityEdit('title', value);
        },
        [emitMetadata, stageEntityEdit]
    );

    const handleSlugChange = useCallback(
        (value: string): void => {
            setSlug(value);
            emitMetadata({ slug: value });
            stageEntityEdit('slug', value);
        },
        [emitMetadata, stageEntityEdit]
    );

    const handleStatusChange = useCallback(
        (value: PostStatus): void => {
            setStatus(value);
            emitMetadata({ status: value });
            stageEntityEdit('status', value);
        },
        [emitMetadata, stageEntityEdit]
    );

    const handleExcerptChange = useCallback(
        (value: string): void => {
            setExcerpt(value);
            emitMetadata({ excerpt: value });
            stageEntityEdit('excerpt', value);
        },
        [emitMetadata, stageEntityEdit]
    );

    const handleFeaturedImageChange = useCallback(
        (value: FeaturedImageValue | null): void => {
            setFeaturedImage(value);
            emitMetadata({ featuredImage: value });
            // The G3 endpoint's WP-shape envelope expects
            // `featured_media: <id|null>`, not the host's
            // `{id, url, alt}` blob.
            stageEntityEdit('featured_media', value === null ? null : value.id);
        },
        [emitMetadata, stageEntityEdit]
    );

    const handleAuthorChange = useCallback(
        (value: number | string | null): void => {
            setAuthorId(value);
            emitMetadata({ authorId: value });
            // WP-shape entity field is `author`, scalar id.
            stageEntityEdit(
                'author',
                typeof value === 'string' && value !== '' && /^\d+$/.test(value)
                    ? Number.parseInt(value, 10)
                    : (value as number | null)
            );
        },
        [emitMetadata, stageEntityEdit]
    );

    const handleCommentsOpenChange = useCallback(
        (value: boolean): void => {
            setCommentsOpen(value);
            emitMetadata({ commentsOpen: value });
            // `comments_open` isn't part of the V1 UpdatePostRequest
            // surface, so we don't stage it on the entity record. Hosts
            // that wire a comments column persist it through
            // `onMetadataChange` until the entity adapter formalizes it.
        },
        [emitMetadata]
    );

    const handleCategoriesChange = useCallback(
        (value: ReadonlyArray<number>): void => {
            setCategories(value);
            emitMetadata({ categories: value });
            stageEntityEdit('categories', [...value]);
        },
        [emitMetadata, stageEntityEdit]
    );

    const handleTagsChange = useCallback(
        (value: ReadonlyArray<number>): void => {
            setTags(value);
            emitMetadata({ tags: value });
            stageEntityEdit('tags', [...value]);
        },
        [emitMetadata, stageEntityEdit]
    );

    const handleParentChange = useCallback(
        (value: number | null): void => {
            setParent(value);
            emitMetadata({ parent: value });
            stageEntityEdit('parent', value);
        },
        [emitMetadata, stageEntityEdit]
    );

    const handleMenuOrderChange = useCallback(
        (value: number): void => {
            setMenuOrder(value);
            emitMetadata({ menuOrder: value });
            stageEntityEdit('menu_order', value);
        },
        [emitMetadata, stageEntityEdit]
    );

    const handleTemplateChange = useCallback(
        (value: string): void => {
            setTemplate(value);
            emitMetadata({ template: value });
            stageEntityEdit('template', value);
        },
        [emitMetadata, stageEntityEdit]
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
            documentType={documentType}
            categories={categories}
            onCategoriesChange={
                documentType === 'post' ? handleCategoriesChange : undefined
            }
            tags={tags}
            onTagsChange={documentType === 'post' ? handleTagsChange : undefined}
            parent={parent}
            onParentChange={
                documentType === 'page' ? handleParentChange : undefined
            }
            menuOrder={menuOrder}
            onMenuOrderChange={
                documentType === 'page' ? handleMenuOrderChange : undefined
            }
            template={template}
            onTemplateChange={
                documentType === 'page' ? handleTemplateChange : undefined
            }
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

    const editorBody = (
        <BlockEditorProvider
            value={blocks}
            settings={editorSettings}
            onInput={handleInput}
            onChange={handleChange}
        >
            {inserterOpen ? (
                <div
                    className="ap-visual-editor__sidebar ap-visual-editor__sidebar--inserter"
                    data-testid="ap-visual-editor-inserter-panel"
                >
                    <BlockLibrarySidebar apiBase={props.apiBase} />
                </div>
            ) : null}
            {/*
             * #347: the block list renders inside `EditorCanvas`'s
             * `BlockCanvas` iframe. `blockContextValue` is non-null
             * exactly for cms-framework Post/Page edits — it stamps
             * `postType`/`postId` into block context so `core/post-*`
             * blocks (which declare `usesContext: ["postId",
             * "postType", "queryId"]`) see the active entity. Outside
             * an entity-mounted canvas it's null and the blocks render
             * their placeholder shell.
             */}
            <EditorCanvas
                showTitle={supports?.title !== false}
                title={title}
                onTitleChange={handleTitleChange}
                blockContext={blockContextValue}
                apiBase={props.apiBase}
            />
            <Popover.Slot />
            <ConvertToPatternControl apiBase={props.apiBase} />
            {inspectorOpen ? (
                <div
                    className="ap-visual-editor__sidebar ap-visual-editor__sidebar--inspector"
                    data-testid="ap-visual-editor-inspector-panel"
                >
                    <InspectorSidebar
                        documentContent={documentPanels}
                        showDocumentTab={supports?.document !== false}
                    />
                </div>
            ) : null}
        </BlockEditorProvider>
    );

    // G3: when the editor mounts for a cms-framework Post or Page, wrap
    // the canvas in `EntityProvider` so `core/post-*` blocks pick up
    // their entity context (kind, name, id) and resolve through the
    // core-data shim's `useEntityRecord` / `useEntityProp` hooks. Other
    // resources (custom HasBlockContent models, legacy fixtures) skip
    // the wrap and the blocks render the placeholder shell — same
    // behavior as before this phase.
    const wrappedBody =
        documentType !== null && numericId !== null ? (
            <EntityProvider
                kind="postType"
                name={documentType}
                id={numericId}
            >
                {editorBody}
            </EntityProvider>
        ) : (
            editorBody
        );

    return (
        <SlotFillProvider>
            <div
                className="ap-visual-editor__shell"
                data-inserter-open={inserterOpen}
                data-inspector-open={inspectorOpen}
                data-document-type={documentType ?? 'unset'}
            >
                {topBar}
                <div className="ap-visual-editor__body">{wrappedBody}</div>
                {shortcutsModal}
            </div>
        </SlotFillProvider>
    );
}
