/**
 * Patterns section orchestrator.
 *
 * Mirrors `useStylesSectionViews` (D3) and `useNavigationSectionViews`
 * (D4) — combines the navigator outlet, canvas outlet, inspector
 * outlet, and any modal overlays the section needs into a single hook
 * the shell consumes. Keeps `site-editor-app.tsx` free of patterns-
 * specific conditionals beyond a single `else if (isPatternsSection)`.
 */

import { __ } from '@wordpress/i18n';
import {
    useCallback,
    useEffect,
    useMemo,
    useState,
    type ReactElement,
} from 'react';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import type { SiteEditorApiConfig } from '../api-client';
import { InspectorSidebar } from '../../editor/inspector-sidebar';
import type { EntityEditorState } from '../entity-editor';
import { SectionPortal } from '../section-portal';

import {
    type PatternRecord,
} from './api-client';
import { ConvertToUnsyncedDialog } from './convert-to-unsynced-dialog';
import { CreatePatternDialog } from './create-pattern-dialog';
import { DeletePatternDialog } from './delete-pattern-dialog';
import { PatternCanvas } from './pattern-canvas';
import { PatternDocumentPanel } from './pattern-inspector';
import { PatternGrid } from './pattern-grid';
import { PatternsBrowser, type SyncTabId } from './patterns-browser';
import { usePatternEditor } from './use-pattern-editor';

export interface UsePatternsSectionViewsOptions {
    apiConfig: SiteEditorApiConfig;
    enabled: boolean;
    activeEntityId: string | null;
    onOpenEntity: (entityId: string) => void;
    /**
     * Drop the URL back to the section's list view. The shell wires
     * this up to `routing.navigate(section, null)` so the canvas
     * doesn't keep trying to render a deleted pattern.
     */
    onCloseEntity: () => void;
    onStateChange: (state: EntityEditorState) => void;
}

export interface PatternsSectionViews {
    navigator: ReactElement;
    canvas: ReactElement;
    inspector: ReactElement;
    overlay: ReactElement | null;
}

const IDLE_STATE: EntityEditorState = {
    entityId: null,
    entityTitle: '',
    isDirty: false,
    saveStatus: 'idle',
    saveErrorMessage: null,
    lastSavedAt: null,
    save: null,
};

interface CreateRequest {
    sync: SyncTabId | null;
    sourceBlocks: readonly unknown[] | null;
    initialName?: string;
}

export function usePatternsSectionViews(
    options: UsePatternsSectionViewsOptions
): PatternsSectionViews {
    const {
        apiConfig,
        enabled,
        activeEntityId,
        onOpenEntity,
        onCloseEntity,
        onStateChange,
    } = options;

    const [activeTab, setActiveTab] = useState<SyncTabId>('synced');
    const [refreshKey, setRefreshKey] = useState(0);
    const [createRequest, setCreateRequest] =
        useState<CreateRequest | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<PatternRecord | null>(
        null
    );
    const [convertTarget, setConvertTarget] = useState<PatternRecord | null>(
        null
    );

    const editor = usePatternEditor({
        apiConfig,
        entityId: enabled ? activeEntityId : null,
    });

    // Push state into the shell's slot so the top bar's "Save pattern"
    // button hits our save dispatcher. Mirror of the hook shape D2/D3/
    // D4 use.
    useEffect(() => {
        if (!enabled) {
            onStateChange(IDLE_STATE);
            return;
        }

        if (activeEntityId === null || editor.pattern === null) {
            onStateChange(IDLE_STATE);
            return;
        }

        const title =
            editor.fields.title.trim() === ''
                ? editor.fields.slug
                : editor.fields.title;

        onStateChange({
            entityId: String(editor.pattern.id),
            entityTitle: title,
            isDirty: editor.isDirty,
            saveStatus: editor.saveStatus,
            saveErrorMessage: editor.saveErrorMessage,
            lastSavedAt: editor.lastSavedAt,
            save: async (): Promise<void> => {
                const updated = await editor.save();

                if (updated !== null) {
                    setRefreshKey((value) => value + 1);
                }
            },
        });
    }, [
        activeEntityId,
        editor.fields.slug,
        editor.fields.title,
        editor.isDirty,
        editor.lastSavedAt,
        editor.pattern,
        editor.save,
        editor.saveErrorMessage,
        editor.saveStatus,
        enabled,
        onStateChange,
    ]);

    const handleSelectTab = useCallback((tab: SyncTabId): void => {
        setActiveTab(tab);
    }, []);

    const handleCanvasCreate = useCallback((synced: boolean): void => {
        setCreateRequest({
            sync: synced ? 'synced' : 'unsynced',
            sourceBlocks: null,
        });
    }, []);

    const handleNavigatorCreate = useCallback((synced: boolean): void => {
        setCreateRequest({
            sync: synced ? 'synced' : 'unsynced',
            sourceBlocks: null,
        });
    }, []);

    const handleEdit = useCallback(
        (id: string): void => {
            onOpenEntity(id);
        },
        [onOpenEntity]
    );

    const handleConvert = useCallback((pattern: PatternRecord): void => {
        setConvertTarget(pattern);
    }, []);

    const handleDelete = useCallback((pattern: PatternRecord): void => {
        setDeleteTarget(pattern);
    }, []);

    const handleConvertFromInspector = useCallback((): void => {
        if (editor.pattern !== null) {
            setConvertTarget(editor.pattern);
        }
    }, [editor.pattern]);

    const navigator = useMemo(
        () => (
            <PatternsBrowser
                apiConfig={apiConfig}
                activeEntityId={activeEntityId}
                activeTab={activeTab}
                onSelectTab={handleSelectTab}
                onOpen={onOpenEntity}
                onRequestCreate={handleNavigatorCreate}
                refreshKey={refreshKey}
            />
        ),
        [
            activeEntityId,
            activeTab,
            apiConfig,
            handleNavigatorCreate,
            handleSelectTab,
            onOpenEntity,
            refreshKey,
        ]
    );

    const canvas = useMemo(() => {
        if (activeEntityId === null) {
            return (
                <PatternGrid
                    apiConfig={apiConfig}
                    synced={activeTab === 'synced'}
                    activeEntityId={activeEntityId}
                    refreshKey={refreshKey}
                    onEdit={handleEdit}
                    onConvertToUnsynced={handleConvert}
                    onDelete={handleDelete}
                    onCreate={handleCanvasCreate}
                />
            );
        }

        const title =
            editor.fields.title.trim() === ''
                ? editor.fields.slug || __('Untitled pattern', TEXT_DOMAIN)
                : editor.fields.title;
        const synced = editor.pattern?.synced ?? false;

        return (
            <PatternCanvas
                title={title}
                synced={synced}
                blocks={editor.blocks}
                onChange={editor.setBlocks}
                onInput={editor.setBlocks}
                isLoading={editor.loadStatus === 'loading'}
                errorMessage={
                    editor.loadStatus === 'error'
                        ? editor.loadErrorMessage
                        : null
                }
                apiBase={apiConfig.apiBase}
            />
        );
    }, [
        activeEntityId,
        activeTab,
        apiConfig,
        editor.blocks,
        editor.fields.slug,
        editor.fields.title,
        editor.loadErrorMessage,
        editor.loadStatus,
        editor.pattern,
        editor.setBlocks,
        handleCanvasCreate,
        handleConvert,
        handleDelete,
        handleEdit,
        refreshKey,
    ]);

    const inspector = useMemo(() => {
        const document = (
            <PatternDocumentPanel
                pattern={editor.pattern}
                fields={editor.fields}
                onFieldsChange={editor.setFields}
                validationErrors={editor.validationErrors}
                onConvertToUnsynced={handleConvertFromInspector}
            />
        );

        return <InspectorSidebar documentContent={document} />;
    }, [
        editor.fields,
        editor.pattern,
        editor.setFields,
        editor.validationErrors,
        handleConvertFromInspector,
    ]);

    let overlay: ReactElement | null = null;

    if (createRequest !== null) {
        overlay = (
            <CreatePatternDialog
                apiConfig={apiConfig}
                initialSync={createRequest.sync}
                sourceBlocks={createRequest.sourceBlocks}
                initialName={createRequest.initialName}
                onClose={() => setCreateRequest(null)}
                onCreated={(record, info) => {
                    setCreateRequest(null);
                    setRefreshKey((value) => value + 1);
                    setActiveTab(info.sync);
                    onOpenEntity(String(record.id));
                }}
            />
        );
    } else if (deleteTarget !== null) {
        overlay = (
            <DeletePatternDialog
                apiConfig={apiConfig}
                pattern={deleteTarget}
                onClose={() => setDeleteTarget(null)}
                onDeleted={(deleted) => {
                    setDeleteTarget(null);
                    setRefreshKey((value) => value + 1);

                    if (
                        activeEntityId !== null &&
                        String(deleted.id) === activeEntityId
                    ) {
                        // Drop back to the list view so the user isn't
                        // staring at a stale canvas pointed at a record
                        // that no longer exists.
                        onCloseEntity();
                    }
                }}
            />
        );
    } else if (convertTarget !== null) {
        overlay = (
            <ConvertToUnsyncedDialog
                apiConfig={apiConfig}
                source={convertTarget}
                workingBlocks={
                    editor.pattern !== null &&
                    String(editor.pattern.id) === String(convertTarget.id)
                        ? editor.blocks
                        : null
                }
                onClose={() => setConvertTarget(null)}
                onCreated={(record) => {
                    setConvertTarget(null);
                    setRefreshKey((value) => value + 1);
                    setActiveTab('unsynced');
                    onOpenEntity(String(record.id));
                }}
            />
        );
    }

    return { navigator, canvas, inspector, overlay };
}

/**
 * Lazy-mountable wrapper around `usePatternsSectionViews` — H7 (#432).
 *
 * Same role as `StylesSectionView` and `NavigationSectionView`: the
 * shell `React.lazy()`-imports this default export so the patterns
 * grid, canvas, inspector, and create / convert / delete dialogs stay
 * out of the initial site-editor boot chunk.
 */
export interface PatternsSectionViewProps extends UsePatternsSectionViewsOptions {
    navigatorSlot: HTMLElement | null;
    canvasSlot: HTMLElement | null;
    inspectorSlot: HTMLElement | null;
    overlaySlot: HTMLElement | null;
}

export default function PatternsSectionView(
    props: PatternsSectionViewProps
): ReactElement {
    const {
        navigatorSlot,
        canvasSlot,
        inspectorSlot,
        overlaySlot,
        ...hookOptions
    } = props;
    const views = usePatternsSectionViews(hookOptions);

    return (
        <>
            <SectionPortal slot={navigatorSlot}>{views.navigator}</SectionPortal>
            <SectionPortal slot={canvasSlot}>{views.canvas}</SectionPortal>
            <SectionPortal slot={inspectorSlot}>{views.inspector}</SectionPortal>
            <SectionPortal slot={overlaySlot}>{views.overlay}</SectionPortal>
        </>
    );
}
