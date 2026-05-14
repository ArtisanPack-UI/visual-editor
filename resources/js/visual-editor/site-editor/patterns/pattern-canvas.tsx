/**
 * Pattern editor canvas.
 *
 * Identical block-editor stack to `EntityEditorCanvas` (templates /
 * parts) — patterns share the `BlockTools` + `WritingFlow` +
 * `BlockList` composition. The header chrome is patterns-specific: a
 * sync-status badge and the canonical "Editing: {title}" announcement
 * so users always know which pattern they're working on (P1 — every
 * screen names the entity).
 *
 * #436: the `BlockEditorProvider` no longer lives here. It was hoisted
 * into {@see BlockEditorBoundary} so the canvas and the inspector share
 * one `core/block-editor` registry. This component now assumes a
 * provider above it and renders only the canvas surface.
 */

import {
    BlockList,
    BlockTools,
    ObserveTyping,
    WritingFlow,
} from '@wordpress/block-editor';
import { __, sprintf } from '@wordpress/i18n';
import { useMemo, type ReactNode } from 'react';

import { TEXT_DOMAIN } from '../../vendor/i18n';

import './pattern-canvas.css';

export interface PatternCanvasProps {
    title: string;
    synced: boolean;
    /** Optional dirty / saved chrome rendered above the canvas. */
    header?: ReactNode;
    isLoading?: boolean;
    errorMessage?: string | null;
}

export function PatternCanvas(props: PatternCanvasProps): JSX.Element {
    const { title, synced, header, isLoading, errorMessage } = props;

    const announcement = useMemo(
        () =>
            sprintf(
                /* translators: %s: pattern title. */
                __('Editing pattern: %s', TEXT_DOMAIN),
                title
            ),
        [title]
    );

    if (isLoading === true) {
        return (
            <div
                className="ap-pattern-canvas ap-pattern-canvas--loading"
                role="status"
                aria-live="polite"
                data-testid="ap-pattern-canvas-loading"
            >
                {__('Loading pattern…', TEXT_DOMAIN)}
            </div>
        );
    }

    if (errorMessage !== null && errorMessage !== undefined) {
        return (
            <div
                className="ap-pattern-canvas ap-pattern-canvas--error"
                role="alert"
                data-testid="ap-pattern-canvas-error"
            >
                {errorMessage}
            </div>
        );
    }

    return (
        <div
            className="ap-pattern-canvas"
            data-testid="ap-pattern-canvas"
            data-synced={synced}
        >
            <span
                role="status"
                aria-live="polite"
                className="ap-pattern-canvas__sr-only"
                data-testid="ap-pattern-canvas-announce"
            >
                {announcement}
            </span>
            <div className="ap-pattern-canvas__chrome">
                <span
                    className="ap-pattern-canvas__sync-badge"
                    data-synced={synced}
                    data-testid="ap-pattern-canvas-sync-badge"
                >
                    {synced
                        ? __('Synced pattern', TEXT_DOMAIN)
                        : __('Unsynced pattern', TEXT_DOMAIN)}
                </span>
                {header}
            </div>
            <div className="ap-pattern-canvas__body">
                <div className="editor-styles-wrapper ap-pattern-canvas__surface">
                    <BlockTools>
                        <WritingFlow>
                            <ObserveTyping>
                                <BlockList />
                            </ObserveTyping>
                        </WritingFlow>
                    </BlockTools>
                </div>
            </div>
        </div>
    );
}
