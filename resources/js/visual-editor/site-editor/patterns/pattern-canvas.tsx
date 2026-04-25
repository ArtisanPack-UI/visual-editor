/**
 * Pattern editor canvas.
 *
 * Identical block-editor stack to `EntityEditorCanvas` (templates /
 * parts) — patterns share the BlockEditorProvider + BlockTools +
 * WritingFlow + BlockList composition. The header chrome is patterns-
 * specific: a sync-status badge and the canonical "Editing: {title}"
 * announcement so users always know which pattern they're working on
 * (P1 — every screen names the entity).
 */

import {
    BlockEditorProvider,
    BlockList,
    BlockTools,
    ObserveTyping,
    WritingFlow,
} from '@wordpress/block-editor';
import { Popover, SlotFillProvider } from '@wordpress/components';
// `@wordpress/format-library` registers the inline rich-text formats
// (bold / italic / link / …) the toolbar surfaces. Side-effect import.
import '@wordpress/format-library';
import { __, sprintf } from '@wordpress/i18n';
import { useMemo, type ReactNode } from 'react';

import { TEXT_DOMAIN } from '../../vendor/i18n';

import { ConvertToPatternControl } from '../../editor/convert-to-pattern-control';

import '@wordpress/components/build-style/style.css';
import '@wordpress/block-editor/build-style/style.css';
import '@wordpress/block-editor/build-style/content.css';
import '@wordpress/block-library/build-style/style.css';
import '@wordpress/block-library/build-style/editor.css';

import './pattern-canvas.css';

export interface PatternCanvasProps {
    title: string;
    synced: boolean;
    blocks: readonly unknown[];
    onChange: (blocks: readonly unknown[]) => void;
    onInput: (blocks: readonly unknown[]) => void;
    /** Optional dirty / saved chrome rendered above the canvas. */
    header?: ReactNode;
    isLoading?: boolean;
    errorMessage?: string | null;
    /** API base used by "Convert to pattern" inside the canvas. */
    apiBase?: string;
}

const EDITOR_SETTINGS = {
    alignWide: true,
    hasFixedToolbar: false,
};

export function PatternCanvas(props: PatternCanvasProps): JSX.Element {
    const {
        title,
        synced,
        blocks,
        onChange,
        onInput,
        header,
        isLoading,
        errorMessage,
        apiBase,
    } = props;

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
                <SlotFillProvider>
                    <BlockEditorProvider
                        value={blocks}
                        settings={EDITOR_SETTINGS}
                        onChange={onChange}
                        onInput={onInput}
                    >
                        <div className="editor-styles-wrapper ap-pattern-canvas__surface">
                            <BlockTools>
                                <WritingFlow>
                                    <ObserveTyping>
                                        <BlockList />
                                    </ObserveTyping>
                                </WritingFlow>
                            </BlockTools>
                        </div>
                        <Popover.Slot />
                        {apiBase !== undefined && apiBase !== '' ? (
                            <ConvertToPatternControl apiBase={apiBase} />
                        ) : null}
                    </BlockEditorProvider>
                </SlotFillProvider>
            </div>
        </div>
    );
}
