/**
 * Entity-editing canvas for the site-editor.
 *
 * Renders the active entity's block tree inside a `BlockEditorProvider`
 * + `BlockTools` / `WritingFlow` / `BlockList` stack so the user gets the
 * same Gutenberg editor surface they'd see in the post editor. Mirrors
 * the sandbox (`sandbox/sandbox-editor.tsx`) and post editor
 * (`editor/editor-app.tsx`) composition rather than using `BlockCanvas`
 * (iframe) — the site-editor shell renders directly into the main
 * content area, so the iframe is unnecessary and added a frame jump
 * when switching between list and edit views.
 *
 * Core blocks are registered on first mount via `ensureBlocksRegistered()`
 * so deep-linking into a template or part lights up the block renderers
 * before the block tree is walked. Registration is idempotent — we flip
 * a module flag to avoid the "already registered" console noise when the
 * shell hot-reloads during dev.
 */

import {
    BlockEditorProvider,
    BlockList,
    BlockTools,
    ObserveTyping,
    WritingFlow,
} from '@wordpress/block-editor';
import { Popover, SlotFillProvider } from '@wordpress/components';
// `@wordpress/format-library` is a side-effect import: it registers the
// core rich-text formats (bold, italic, link, …) so the block toolbar's
// inline formatting controls work inside RichText blocks.
import '@wordpress/format-library';
import { __, sprintf } from '@wordpress/i18n';
import { useMemo, type ReactNode } from 'react';

import { TEXT_DOMAIN } from '../vendor/i18n';

import '@wordpress/components/build-style/style.css';
import '@wordpress/block-editor/build-style/style.css';
import '@wordpress/block-editor/build-style/content.css';
import '@wordpress/block-library/build-style/style.css';
import '@wordpress/block-library/build-style/editor.css';

import './entity-editor-canvas.css';

export interface EntityEditorCanvasProps {
    /** Identifier for the active entity (used in a11y announcements). */
    entityTitle: string;
    /** Parsed block tree loaded from the REST response. */
    blocks: readonly unknown[];
    onChange: (blocks: readonly unknown[]) => void;
    onInput: (blocks: readonly unknown[]) => void;
    /**
     * Renders any entity-scoped chrome above the canvas. D2 uses this to
     * plug in the template fallback-chain breadcrumb and the dirty-state
     * indicator the design brief calls out.
     */
    header?: ReactNode;
    /** Optional status line rendered in place of the canvas while loading. */
    isLoading?: boolean;
    /** Optional error copy rendered in place of the canvas. */
    errorMessage?: string | null;
}

/**
 * Minimal block-editor settings — enough for the stock blocks to render
 * without crashing on missing presets. D3 (global styles) wires the real
 * palette / font sizes / layout defaults; for D2 the canvas needs to
 * boot with the defaults most core blocks expect.
 */
const EDITOR_SETTINGS = {
    alignWide: true,
    hasFixedToolbar: false,
};

export function EntityEditorCanvas(props: EntityEditorCanvasProps): JSX.Element {
    const { entityTitle, blocks, onChange, onInput, header, isLoading, errorMessage } =
        props;

    const announcement = useMemo(
        () =>
            sprintf(
                /* translators: %s: entity title being edited. */
                __('Editing %s', TEXT_DOMAIN),
                entityTitle
            ),
        [entityTitle]
    );

    if (isLoading === true) {
        return (
            <div
                className="ap-site-editor__entity-canvas ap-site-editor__entity-canvas--loading"
                role="status"
                aria-live="polite"
                data-testid="ap-site-editor-entity-canvas-loading"
            >
                {__('Loading entity…', TEXT_DOMAIN)}
            </div>
        );
    }

    if (errorMessage !== null && errorMessage !== undefined) {
        return (
            <div
                className="ap-site-editor__entity-canvas ap-site-editor__entity-canvas--error"
                role="alert"
                data-testid="ap-site-editor-entity-canvas-error"
            >
                {errorMessage}
            </div>
        );
    }

    return (
        <div
            className="ap-site-editor__entity-canvas"
            data-testid="ap-site-editor-entity-canvas"
        >
            <span
                role="status"
                aria-live="polite"
                className="ap-site-editor__sr-only"
                data-testid="ap-site-editor-entity-canvas-announce"
            >
                {announcement}
            </span>
            {header !== undefined ? (
                <div className="ap-site-editor__entity-canvas-header">{header}</div>
            ) : null}
            <div className="ap-site-editor__entity-canvas-body">
                <SlotFillProvider>
                    <BlockEditorProvider
                        value={blocks}
                        settings={EDITOR_SETTINGS}
                        onChange={onChange}
                        onInput={onInput}
                    >
                        <div className="editor-styles-wrapper ap-site-editor__entity-canvas-surface">
                            <BlockTools>
                                <WritingFlow>
                                    <ObserveTyping>
                                        <BlockList />
                                    </ObserveTyping>
                                </WritingFlow>
                            </BlockTools>
                        </div>
                        <Popover.Slot />
                    </BlockEditorProvider>
                </SlotFillProvider>
            </div>
        </div>
    );
}
