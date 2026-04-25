/**
 * Entity-editor orchestrator.
 *
 * Stitches together the canvas, the A1 inspector sidebar, and the kind-
 * specific document panel once the user opens a template or part. Also
 * owns the handoff of save status / dirty flag up to the shell top bar:
 * the parent passes an `onStateChange` callback and a `saveHandlerRef`
 * (a slot for the top-bar button to invoke), so the top bar stays in
 * sync with whichever editor is mounted without reaching into React
 * context.
 */

import { __ } from '@wordpress/i18n';
import { useCallback, useEffect, useMemo, type ReactNode } from 'react';

import { TEXT_DOMAIN } from '../vendor/i18n';

import {
    type EntityKind,
    type EntityRecord,
    type SiteEditorApiConfig,
    type UpdatePayload,
} from './api-client';
import { EntityEditorCanvas } from './entity-editor-canvas';
import { fallbackChainForSlug } from './fallback-chain';
import { useEntityEditor, type SaveStatus } from './use-entity-editor';
import { InspectorSidebar } from '../editor/inspector-sidebar';
import {
    TemplateDocumentPanel,
} from './templates-section';
import {
    TemplatePartDocumentPanel,
} from './template-parts-section';

export interface EntityEditorState {
    entityId: string | null;
    entityTitle: string;
    isDirty: boolean;
    saveStatus: SaveStatus;
    saveErrorMessage: string | null;
    lastSavedAt: Date | null;
    /**
     * Dispatcher the top-bar Save button invokes. `null` when no entity
     * is loaded (the top bar disables its Save in that case).
     */
    save: (() => Promise<void>) | null;
}

export interface EntityEditorOptions<K extends EntityKind> {
    apiConfig: SiteEditorApiConfig;
    kind: K;
    entityId: string | null;
    onStateChange: (state: EntityEditorState) => void;
}

export interface EntityEditorViews {
    canvas: JSX.Element;
    inspector: JSX.Element;
}

function resolveTitle(entity: EntityRecord<EntityKind> | null): string {
    if (entity === null) {
        return '';
    }

    const rendered = entity.title?.rendered?.trim();

    if (rendered !== undefined && rendered !== '') {
        return rendered;
    }

    return entity.slug;
}

export function useEntityEditorViews<K extends EntityKind>(
    options: EntityEditorOptions<K>
): EntityEditorViews {
    const { apiConfig, kind, entityId, onStateChange } = options;

    const editor = useEntityEditor({ apiConfig, kind, entityId });
    const {
        entity,
        loadStatus,
        loadErrorMessage,
        blocks,
        setBlocks,
        isDirty,
        saveStatus,
        saveErrorMessage,
        lastSavedAt,
        save,
        patch,
    } = editor;

    const entityTitle = useMemo(() => resolveTitle(entity), [entity]);

    // Memoize the top-bar save dispatcher so `onStateChange` receives a
    // stable reference between renders — otherwise the shell's
    // `entityState` re-equality check sees a new `save` closure every
    // render and re-runs its own state update loop.
    const memoizedSave = useCallback(async (): Promise<void> => {
        await save();
    }, [save]);

    const savePayload = entityId === null ? null : memoizedSave;

    useEffect(() => {
        onStateChange({
            entityId,
            entityTitle,
            isDirty,
            saveStatus,
            saveErrorMessage,
            lastSavedAt,
            save: savePayload,
        });
    }, [
        entityId,
        entityTitle,
        isDirty,
        saveStatus,
        saveErrorMessage,
        lastSavedAt,
        savePayload,
        onStateChange,
    ]);

    const headerContent = useMemo((): ReactNode => {
        if (entity === null) {
            return null;
        }

        const rows: ReactNode[] = [];

        if (kind === 'template') {
            const chain = fallbackChainForSlug(entity.slug);

            rows.push(
                <span
                    key="chain"
                    className="ap-site-editor__fallback-chain-header"
                    data-testid="ap-site-editor-entity-canvas-chain"
                >
                    {chain.map((slug, index) => (
                        <span key={slug}>
                            <code>{slug}</code>
                            {index < chain.length - 1 ? (
                                <span aria-hidden="true">{' ▸ '}</span>
                            ) : null}
                        </span>
                    ))}
                </span>
            );
        }

        if (isDirty) {
            rows.push(
                <span
                    key="dirty"
                    className="ap-site-editor__dirty-indicator"
                    role="status"
                    aria-live="polite"
                    data-testid="ap-site-editor-entity-canvas-dirty"
                >
                    {__('Unsaved changes', TEXT_DOMAIN)}
                </span>
            );
        }

        if (saveStatus === 'saved' && !isDirty) {
            rows.push(
                <span
                    key="saved"
                    className="ap-site-editor__save-indicator"
                    role="status"
                    data-testid="ap-site-editor-entity-canvas-saved"
                >
                    {__('Saved', TEXT_DOMAIN)}
                </span>
            );
        }

        if (saveStatus === 'error' && saveErrorMessage !== null) {
            rows.push(
                <span
                    key="save-error"
                    role="alert"
                    className="ap-site-editor__entity-canvas--error"
                    data-testid="ap-site-editor-entity-canvas-save-error"
                >
                    {saveErrorMessage}
                </span>
            );
        }

        return rows.length > 0 ? rows : null;
    }, [entity, isDirty, kind, saveErrorMessage, saveStatus]);

    const canvas = useMemo((): JSX.Element => {
        if (entityId === null) {
            return (
                <div
                    className="ap-site-editor__entity-canvas ap-site-editor__entity-canvas--loading"
                    data-testid="ap-site-editor-entity-canvas-inactive"
                >
                    {__('Select an entity to edit.', TEXT_DOMAIN)}
                </div>
            );
        }

        return (
            <EntityEditorCanvas
                entityTitle={entityTitle !== '' ? entityTitle : kind}
                blocks={blocks}
                onChange={setBlocks}
                onInput={setBlocks}
                header={headerContent}
                isLoading={loadStatus === 'loading'}
                errorMessage={loadStatus === 'error' ? loadErrorMessage : null}
                apiBase={apiConfig.apiBase}
            />
        );
    }, [
        apiConfig.apiBase,
        blocks,
        entityId,
        entityTitle,
        headerContent,
        kind,
        loadErrorMessage,
        loadStatus,
        setBlocks,
    ]);

    const documentPanel = useMemo((): ReactNode => {
        const handlePatch = (overrides: UpdatePayload): void => {
            patch(overrides);
        };

        if (kind === 'template') {
            return (
                <TemplateDocumentPanel
                    entity={entity as EntityRecord<'template'> | null}
                    onPatch={handlePatch}
                />
            );
        }

        return (
            <TemplatePartDocumentPanel
                entity={entity as EntityRecord<'template-part'> | null}
                onPatch={handlePatch}
            />
        );
    }, [entity, kind, patch]);

    const inspector = useMemo(
        (): JSX.Element => <InspectorSidebar documentContent={documentPanel} />,
        [documentPanel]
    );

    return { canvas, inspector };
}
