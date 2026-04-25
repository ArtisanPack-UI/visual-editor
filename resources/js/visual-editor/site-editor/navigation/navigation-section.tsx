/**
 * Navigation section orchestrator.
 *
 * Parallel to `useStylesSectionViews` but for the D4 menu editor.
 * Combines:
 *   - the menu list + locations panel (navigator outlet)
 *   - the native tree editor (canvas outlet)
 *   - the inspector (entity OR item panel)
 *
 * Surfaces the entity-state contract the shell's top-bar Save button
 * already knows how to render, so wiring D4 in is just the same
 * `useNavigationSectionViews({ ... })` shape D3 uses.
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
import type {
    SiteEditorApiConfig,
} from '../api-client';
import type { EntityEditorState } from '../entity-editor';
import {
    fetchMenuLocations,
    listNavigations,
    updateNavigation,
    type MenuLocation,
    type NavigationRecord,
} from './api-client';
import { CreateMenuDialog } from './create-menu-dialog';
import { NavigationBrowser } from './navigation-browser';
import { NavigationCanvas } from './navigation-canvas';
import { NavigationInspector } from './navigation-inspector';
import { useNavigationEditor } from './use-navigation-editor';

export interface UseNavigationSectionViewsOptions {
    apiConfig: SiteEditorApiConfig;
    enabled: boolean;
    activeEntityId: string | null;
    onOpenEntity: (entityId: string) => void;
    onStateChange: (state: EntityEditorState) => void;
}

export interface NavigationSectionViews {
    navigator: ReactElement;
    canvas: ReactElement;
    inspector: ReactElement;
    /** Modal layer the shell renders alongside the existing dialogs. */
    overlay: ReactElement | null;
}

export function useNavigationSectionViews(
    options: UseNavigationSectionViewsOptions
): NavigationSectionViews {
    const {
        apiConfig,
        enabled,
        activeEntityId,
        onOpenEntity,
        onStateChange,
    } = options;

    const [locations, setLocations] = useState<readonly MenuLocation[]>([]);
    const [isLocationsLoading, setIsLocationsLoading] = useState(false);
    const [locationsError, setLocationsError] = useState<string | null>(null);
    const [browserRefreshKey, setBrowserRefreshKey] = useState(0);
    const [locationsRefreshKey, setLocationsRefreshKey] = useState(0);
    const [showCreateDialog, setShowCreateDialog] = useState(false);
    const [selectedItemId, setSelectedItemId] = useState<string | null>(null);

    useEffect(() => {
        if (!enabled) {
            return undefined;
        }

        let cancelled = false;
        setIsLocationsLoading(true);
        setLocationsError(null);

        (async () => {
            try {
                const data = await fetchMenuLocations(apiConfig);

                if (cancelled) {
                    return;
                }

                setLocations(data);
            } catch (error: unknown) {
                if (cancelled) {
                    return;
                }

                setLocationsError(
                    error instanceof Error
                        ? error.message
                        : __('Failed to load menu locations.', TEXT_DOMAIN)
                );
            } finally {
                if (!cancelled) {
                    setIsLocationsLoading(false);
                }
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [apiConfig, enabled, locationsRefreshKey]);

    const editor = useNavigationEditor({
        apiConfig,
        entityId: enabled ? activeEntityId : null,
    });

    // Reset selected item whenever the user opens a different menu so
    // the inspector doesn't keep highlighting an id that's no longer in
    // the tree.
    useEffect(() => {
        setSelectedItemId(null);
    }, [activeEntityId]);

    // Push state into the shell's slot. Same shape D3's hook uses so
    // the top bar's "Save menu" button hits our save dispatcher
    // without any conditional branching.
    useEffect(() => {
        if (!enabled) {
            onStateChange({
                entityId: null,
                entityTitle: '',
                isDirty: false,
                saveStatus: 'idle',
                saveErrorMessage: null,
                lastSavedAt: null,
                save: null,
            });
            return;
        }

        onStateChange({
            entityId:
                editor.entity === null ? null : String(editor.entity.id),
            entityTitle:
                editor.fields.title === ''
                    ? editor.fields.slug
                    : editor.fields.title,
            isDirty: editor.isDirty,
            saveStatus: editor.saveStatus,
            saveErrorMessage: editor.saveErrorMessage,
            lastSavedAt: editor.lastSavedAt,
            save:
                editor.entity === null
                    ? null
                    : async (): Promise<void> => {
                          const updated = await editor.save();

                          if (updated !== null) {
                              // Bump both refresh keys so the navigator
                              // list reflects renames / location moves
                              // and the locations panel updates the
                              // assignment chip.
                              setBrowserRefreshKey((value) => value + 1);
                              setLocationsRefreshKey((value) => value + 1);
                          }
                      },
        });
    }, [
        editor.entity,
        editor.fields.slug,
        editor.fields.title,
        editor.isDirty,
        editor.lastSavedAt,
        editor.save,
        editor.saveErrorMessage,
        editor.saveStatus,
        enabled,
        onStateChange,
    ]);

    const handleAssignLocation = useCallback(
        async (
            locationSlug: string,
            navigationId: number | null
        ): Promise<void> => {
            // Two-step write semantics: the chosen menu gets the new
            // slug; whoever already had the slug is released by the
            // backend. If the user picked "None" we still need to find
            // the current owner and clear it.
            try {
                if (navigationId === null) {
                    // Find the current owner by slug. List response is
                    // already paginated to 50 — V1 menus list is
                    // small, no need to paginate further.
                    const list = await listNavigations(apiConfig, {
                        perPage: 50,
                    });
                    const current = list.data.find(
                        (row) => row.location === locationSlug
                    );

                    if (current !== undefined) {
                        await updateNavigation(apiConfig, current.id, {
                            location: null,
                        });
                    }
                } else {
                    await updateNavigation(apiConfig, navigationId, {
                        location: locationSlug,
                    });
                }

                setBrowserRefreshKey((value) => value + 1);
                setLocationsRefreshKey((value) => value + 1);
            } catch (error: unknown) {
                setLocationsError(
                    error instanceof Error
                        ? error.message
                        : __(
                              'Failed to update menu location.',
                              TEXT_DOMAIN
                          )
                );
            }
        },
        [apiConfig]
    );

    const navigator = (
        <NavigationBrowser
            apiConfig={apiConfig}
            activeEntityId={activeEntityId}
            onOpen={onOpenEntity}
            onRequestCreate={() => setShowCreateDialog(true)}
            refreshKey={browserRefreshKey}
            locations={locations}
            isLocationsLoading={isLocationsLoading}
            locationsError={locationsError}
            onAssignLocation={handleAssignLocation}
        />
    );

    const canvas = useMemo(() => {
        if (activeEntityId === null) {
            return (
                <div
                    className="ap-nav-canvas ap-nav-canvas--empty-shell"
                    data-empty="true"
                    data-testid="ap-nav-canvas-empty-shell"
                >
                    <p className="ap-nav-canvas__empty">
                        {__(
                            'Open a menu from the list to start editing.',
                            TEXT_DOMAIN
                        )}
                    </p>
                </div>
            );
        }

        if (editor.loadStatus === 'loading') {
            return (
                <div
                    className="ap-nav-canvas"
                    data-testid="ap-nav-canvas-loading"
                >
                    <p className="ap-nav-canvas__empty">
                        {__('Loading menu…', TEXT_DOMAIN)}
                    </p>
                </div>
            );
        }

        if (editor.loadStatus === 'error') {
            return (
                <div
                    className="ap-nav-canvas"
                    role="alert"
                    data-testid="ap-nav-canvas-error"
                >
                    <p className="ap-nav-canvas__empty">
                        {editor.loadErrorMessage ??
                            __('Failed to load menu.', TEXT_DOMAIN)}
                    </p>
                </div>
            );
        }

        return (
            <NavigationCanvas
                tree={editor.tree}
                selectedItemId={selectedItemId}
                onSelectItem={setSelectedItemId}
                onTreeChange={editor.setTree}
            />
        );
    }, [
        activeEntityId,
        editor.loadErrorMessage,
        editor.loadStatus,
        editor.setTree,
        editor.tree,
        selectedItemId,
    ]);

    const inspector = (
        <NavigationInspector
            apiConfig={apiConfig}
            fields={editor.fields}
            onFieldsChange={editor.setFields}
            tree={editor.tree}
            onTreeChange={editor.setTree}
            selectedItemId={selectedItemId}
            onSelectItem={setSelectedItemId}
            locations={locations}
            isLocationsLoading={isLocationsLoading}
            locationsError={locationsError}
            validationErrors={editor.validationErrors}
            saveStatus={editor.saveStatus}
            saveErrorMessage={editor.saveErrorMessage}
        />
    );

    const overlay = showCreateDialog ? (
        <CreateMenuDialog
            apiConfig={apiConfig}
            locations={locations}
            onClose={() => setShowCreateDialog(false)}
            onCreated={(record) => {
                setShowCreateDialog(false);
                setBrowserRefreshKey((value) => value + 1);
                setLocationsRefreshKey((value) => value + 1);
                onOpenEntity(String(record.id));
            }}
        />
    ) : null;

    return { navigator, canvas, inspector, overlay };
}
