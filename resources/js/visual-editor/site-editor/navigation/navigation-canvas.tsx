/**
 * Navigation canvas — the native tree editor.
 *
 * Per design brief §3.8 the Navigation section is the one site-editor
 * surface that ISN'T a block-editor iframe canvas: nav trees are
 * structural data, not block trees, so a tree editor with drag-sort
 * and a typed-target picker is the correct UI.
 *
 * Drag-and-drop uses the dnd-kit "tree" recipe: the nested MenuItem
 * tree is flattened into a single sortable list, and the dragged row's
 * horizontal offset is mapped to a projected depth so the user can
 * drag items INTO and OUT OF submenus by dragging right / left. On
 * commit, the active row's `depth` + `parentLocalId` are updated and
 * then array-moved into place; descendants re-attach via their
 * `parentLocalId` when `buildTreeFromFlat` rebuilds the nested tree.
 */

import { __, sprintf } from '@wordpress/i18n';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
    DndContext,
    type DragEndEvent,
    type DragMoveEvent,
    type DragOverEvent,
    type DragStartEvent,
    KeyboardSensor,
    MouseSensor,
    TouchSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import {
    appendChild,
    buildTreeFromFlat,
    commitProjection,
    effectiveLabel,
    flattenTree,
    getDescendantIds,
    makeMenuItem,
    projectDrop,
    removeMenuItem,
    type FlatMenuItem,
    type MenuItem,
    type ProjectionResult,
} from './menu-tree';

import './navigation-canvas.css';

const INDENT_PX = 24;

export interface NavigationCanvasProps {
    tree: readonly MenuItem[];
    selectedItemId: string | null;
    onSelectItem: (localId: string | null) => void;
    onTreeChange: (next: readonly MenuItem[]) => void;
    /** Render an empty-state message when the menu has no items yet. */
    emptyStateMessage?: string;
}

export function NavigationCanvas(props: NavigationCanvasProps): JSX.Element {
    const {
        tree,
        selectedItemId,
        onSelectItem,
        onTreeChange,
        emptyStateMessage,
    } = props;

    const sensors = useSensors(
        useSensor(MouseSensor, { activationConstraint: { distance: 4 } }),
        useSensor(TouchSensor),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        })
    );

    const flatTree = useMemo(() => flattenTree(tree), [tree]);

    const [activeId, setActiveId] = useState<string | null>(null);
    const [overId, setOverId] = useState<string | null>(null);
    const [dragOffsetX, setDragOffsetX] = useState(0);

    // While dragging, hide the active item's descendants from the
    // sortable list so the dragged subtree visibly travels as a unit
    // (the children re-attach via parentLocalId on commit). This
    // matches the standard dnd-kit tree recipe.
    const visibleFlat = useMemo(() => {
        if (activeId === null) {
            return flatTree;
        }

        const descendants = getDescendantIds(flatTree, activeId);

        return flatTree.filter(
            (entry) => !descendants.has(entry.item.localId)
        );
    }, [activeId, flatTree]);

    const projection: ProjectionResult | null = useMemo(() => {
        if (activeId === null || overId === null) {
            return null;
        }

        return projectDrop(
            visibleFlat,
            activeId,
            overId,
            dragOffsetX,
            INDENT_PX
        );
    }, [activeId, dragOffsetX, overId, visibleFlat]);

    const sortedIds = useMemo(
        () => visibleFlat.map((entry) => entry.item.localId),
        [visibleFlat]
    );

    const handleDragStart = useCallback((event: DragStartEvent): void => {
        setActiveId(String(event.active.id));
        setOverId(String(event.active.id));
        setDragOffsetX(0);
    }, []);

    const handleDragMove = useCallback((event: DragMoveEvent): void => {
        setDragOffsetX(event.delta.x);
    }, []);

    const handleDragOver = useCallback((event: DragOverEvent): void => {
        setOverId(event.over === null ? null : String(event.over.id));
    }, []);

    const reset = useCallback((): void => {
        setActiveId(null);
        setOverId(null);
        setDragOffsetX(0);
    }, []);

    const handleDragEnd = useCallback(
        (event: DragEndEvent): void => {
            const active = String(event.active.id);
            const over = event.over === null ? null : String(event.over.id);

            reset();

            if (over === null || projection === null) {
                return;
            }

            const updated = commitProjection(
                flatTree,
                active,
                over,
                projection
            );

            onTreeChange(buildTreeFromFlat(updated));
        },
        [flatTree, onTreeChange, projection, reset]
    );

    const handleDragCancel = useCallback((): void => {
        reset();
    }, [reset]);

    const handleAddItem = useCallback((): void => {
        const fresh = makeMenuItem({
            type: 'custom',
            url: '',
            autoLabel: __('New menu item', TEXT_DOMAIN),
        });

        onTreeChange([...tree, fresh]);
        onSelectItem(fresh.localId);
    }, [onSelectItem, onTreeChange, tree]);

    const handleAddChild = useCallback(
        (parentId: string): void => {
            const fresh = makeMenuItem({
                type: 'custom',
                url: '',
                autoLabel: __('New menu item', TEXT_DOMAIN),
            });

            onTreeChange(appendChild(tree, parentId, fresh));
            onSelectItem(fresh.localId);
        },
        [onSelectItem, onTreeChange, tree]
    );

    const handleDelete = useCallback(
        (localId: string): void => {
            onTreeChange(removeMenuItem(tree, localId));

            if (selectedItemId === localId) {
                onSelectItem(null);
            }
        },
        [onSelectItem, onTreeChange, selectedItemId, tree]
    );

    if (tree.length === 0) {
        return (
            <div
                className="ap-nav-canvas"
                data-testid="ap-nav-canvas"
                data-empty="true"
            >
                <p className="ap-nav-canvas__empty">
                    {emptyStateMessage ??
                        __(
                            'This menu is empty. Add the first link to get started.',
                            TEXT_DOMAIN
                        )}
                </p>
                <button
                    type="button"
                    className="ap-nav-canvas__add"
                    onClick={handleAddItem}
                    data-testid="ap-nav-canvas-add"
                >
                    {__('Add menu item', TEXT_DOMAIN)}
                </button>
            </div>
        );
    }

    return (
        <div
            className="ap-nav-canvas"
            data-testid="ap-nav-canvas"
            data-empty="false"
        >
            <DndContext
                sensors={sensors}
                onDragStart={handleDragStart}
                onDragMove={handleDragMove}
                onDragOver={handleDragOver}
                onDragEnd={handleDragEnd}
                onDragCancel={handleDragCancel}
            >
                <SortableContext
                    items={sortedIds}
                    strategy={verticalListSortingStrategy}
                >
                    <ul
                        className="ap-nav-canvas__tree"
                        aria-label={__('Menu items', TEXT_DOMAIN)}
                    >
                        {visibleFlat.map((entry) => (
                            <SortableMenuItemRow
                                key={entry.item.localId}
                                flat={entry}
                                projection={
                                    activeId === entry.item.localId
                                        ? projection
                                        : null
                                }
                                indentPx={INDENT_PX}
                                selectedItemId={selectedItemId}
                                onSelect={onSelectItem}
                                onAddChild={handleAddChild}
                                onDelete={handleDelete}
                            />
                        ))}
                    </ul>
                </SortableContext>
            </DndContext>

            <button
                type="button"
                className="ap-nav-canvas__add"
                onClick={handleAddItem}
                data-testid="ap-nav-canvas-add"
            >
                {__('Add menu item', TEXT_DOMAIN)}
            </button>
        </div>
    );
}

interface SortableMenuItemRowProps {
    flat: FlatMenuItem;
    projection: ProjectionResult | null;
    indentPx: number;
    selectedItemId: string | null;
    onSelect: (localId: string | null) => void;
    onAddChild: (parentId: string) => void;
    onDelete: (localId: string) => void;
}

function SortableMenuItemRow(props: SortableMenuItemRowProps): JSX.Element {
    const {
        flat,
        projection,
        indentPx,
        selectedItemId,
        onSelect,
        onAddChild,
        onDelete,
    } = props;

    const sortable = useSortable({ id: flat.item.localId });

    const style = {
        transform: CSS.Translate.toString(sortable.transform),
        transition: sortable.transition,
    };

    // Live depth follows the projection while a drag is in progress
    // so the user sees the row indent / outdent under the cursor.
    const renderDepth =
        sortable.isDragging && projection !== null
            ? projection.depth
            : flat.depth;

    const renderFlat: FlatMenuItem = {
        ...flat,
        depth: renderDepth,
    };

    return (
        <li
            ref={sortable.setNodeRef}
            style={style}
            className="ap-nav-canvas__item"
            data-testid={`ap-nav-canvas-item-${flat.item.localId}`}
            data-dragging={sortable.isDragging}
        >
            <MenuItemRowBody
                flat={renderFlat}
                indentPx={indentPx}
                selectedItemId={selectedItemId}
                onSelect={onSelect}
                onAddChild={onAddChild}
                onDelete={onDelete}
                dragHandleProps={{
                    ...sortable.attributes,
                    ...sortable.listeners,
                }}
            />
        </li>
    );
}

interface RowBodyProps {
    flat: FlatMenuItem;
    indentPx: number;
    selectedItemId: string | null;
    onSelect: (localId: string | null) => void;
    onAddChild: (parentId: string) => void;
    onDelete: (localId: string) => void;
    dragHandleProps?: Record<string, unknown>;
}

function MenuItemRowBody(props: RowBodyProps): JSX.Element {
    const {
        flat,
        indentPx,
        selectedItemId,
        onSelect,
        onAddChild,
        onDelete,
        dragHandleProps,
    } = props;

    const item = flat.item;
    const [confirmingDelete, setConfirmingDelete] = useState(false);

    // Auto-revert the confirm state after a short window. Without this
    // a user who clicks Delete once and walks away would find the row
    // armed for a one-click destruction whenever they came back.
    useEffect(() => {
        if (!confirmingDelete) {
            return undefined;
        }

        const handle = window.setTimeout(() => {
            setConfirmingDelete(false);
        }, 4000);

        return () => window.clearTimeout(handle);
    }, [confirmingDelete]);

    const isSelected = selectedItemId === item.localId;
    const label = effectiveLabel(item);
    const visibleLabel =
        label === '' ? __('(Untitled item)', TEXT_DOMAIN) : label;

    const handleDeleteClick = useCallback((): void => {
        if (!confirmingDelete) {
            setConfirmingDelete(true);
            return;
        }

        onDelete(item.localId);
    }, [confirmingDelete, item.localId, onDelete]);

    const handleSelect = useCallback((): void => {
        onSelect(item.localId);
    }, [item.localId, onSelect]);

    const targetIcon = typeIcon(item.type);

    return (
        <div
            className="ap-nav-canvas__row"
            data-selected={isSelected}
            data-depth={flat.depth}
            style={{ paddingLeft: flat.depth * indentPx }}
        >
            {dragHandleProps !== undefined ? (
                <button
                    type="button"
                    className="ap-nav-canvas__drag-handle"
                    aria-label={sprintf(
                        /* translators: %s: menu item label. */
                        __('Drag to reorder %s', TEXT_DOMAIN),
                        visibleLabel
                    )}
                    data-testid={`ap-nav-canvas-drag-${item.localId}`}
                    {...dragHandleProps}
                >
                    <span aria-hidden="true">{'⋮⋮'}</span>
                </button>
            ) : null}
            <button
                type="button"
                className="ap-nav-canvas__row-main"
                onClick={handleSelect}
                aria-pressed={isSelected}
                data-testid={`ap-nav-canvas-row-${item.localId}`}
            >
                <span className="ap-nav-canvas__type" aria-hidden="true">
                    {targetIcon}
                </span>
                <span className="ap-nav-canvas__label">{visibleLabel}</span>
                <span className="ap-nav-canvas__type-label">
                    {typeLabel(item.type)}
                </span>
            </button>
            <span className="ap-nav-canvas__row-actions">
                <button
                    type="button"
                    className="ap-nav-canvas__row-action"
                    onClick={() => onAddChild(item.localId)}
                    aria-label={sprintf(
                        /* translators: %s: parent label. */
                        __('Add child of %s', TEXT_DOMAIN),
                        visibleLabel
                    )}
                    data-testid={`ap-nav-canvas-add-child-${item.localId}`}
                >
                    {__('Add child', TEXT_DOMAIN)}
                </button>
                <button
                    type="button"
                    className="ap-nav-canvas__row-action ap-nav-canvas__row-action--danger"
                    onClick={handleDeleteClick}
                    aria-label={sprintf(
                        /* translators: %s: menu item label. */
                        __('Delete %s', TEXT_DOMAIN),
                        visibleLabel
                    )}
                    data-confirming={confirmingDelete}
                    data-testid={`ap-nav-canvas-delete-${item.localId}`}
                >
                    {confirmingDelete
                        ? __('Confirm delete', TEXT_DOMAIN)
                        : __('Delete', TEXT_DOMAIN)}
                </button>
            </span>
        </div>
    );
}

function typeIcon(type: MenuItem['type']): string {
    if (type === 'page') {
        return '▤';
    }
    if (type === 'post') {
        return '✎';
    }
    if (type === 'taxonomy') {
        return '🏷';
    }

    return '↗';
}

function typeLabel(type: MenuItem['type']): string {
    if (type === 'page') {
        return __('Page', TEXT_DOMAIN);
    }
    if (type === 'post') {
        return __('Post', TEXT_DOMAIN);
    }
    if (type === 'taxonomy') {
        return __('Taxonomy', TEXT_DOMAIN);
    }

    return __('Custom URL', TEXT_DOMAIN);
}
