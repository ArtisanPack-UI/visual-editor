/**
 * Entity-editing canvas for the site-editor.
 *
 * Renders the active entity's block tree as a `BlockTools` /
 * `WritingFlow` / `BlockList` stack so the user gets the same
 * Gutenberg editor surface they'd see in the post editor. Mirrors the
 * sandbox (`sandbox/sandbox-editor.tsx`) and post editor
 * (`editor/editor-app.tsx`) composition rather than using `BlockCanvas`
 * (iframe) — the site-editor shell renders directly into the main
 * content area, so the iframe is unnecessary and added a frame jump
 * when switching between list and edit views.
 *
 * #436: the `BlockEditorProvider` no longer lives here. It was hoisted
 * into {@see BlockEditorBoundary} so the canvas and the inspector share
 * one `core/block-editor` registry — when the provider was mounted
 * inside this component, the inspector (a sibling) fell outside its
 * scope and never saw block selection. This component now assumes a
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

import {
    ALIGNMENT_OVERRIDE_STYLES,
    DEFAULT_CANVAS_STYLES,
    ROOT_CANVAS_LAYOUT,
} from '../editor-settings';
import { TEXT_DOMAIN } from '../vendor/i18n';

import { CanvasThemeStyles } from './canvas-theme-styles';

import './entity-editor-canvas.css';

export interface EntityEditorCanvasProps {
    /** Identifier for the active entity (used in a11y announcements). */
    entityTitle: string;
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
    /**
     * Site-editor REST base. When supplied, the canvas inlines the
     * active theme's compiled CSS via {@see CanvasThemeStyles}, so the
     * surface matches the public front-end (Keystone #47).
     */
    apiBase?: string;
}

export function EntityEditorCanvas(props: EntityEditorCanvasProps): JSX.Element {
    const { entityTitle, header, isLoading, errorMessage, apiBase } = props;

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
                <div className="editor-styles-wrapper ap-site-editor__entity-canvas-surface">
                    {/*
                     * #418: default canvas stylesheet rendered inline.
                     * Gutenberg's `settings.styles` channel only injects
                     * into the `<Iframe>` canvas; the site editor renders
                     * the block list in the same DOM tree, so the rules
                     * must be inlined here — exactly as the post editor
                     * (`editor/editor-app.tsx`) does. Without it the
                     * canvas (and any inlined `core/template-part` inner
                     * blocks, which render in-tree under this wrapper)
                     * fall back to browser-default serif. The CSS is
                     * scoped under `.editor-styles-wrapper`, so a
                     * theme.json stylesheet appended later wins on
                     * cascade.
                     */}
                    <style>{DEFAULT_CANVAS_STYLES}</style>
                    {/*
                     * Keystone #47: wide/full alignment overrides
                     * applied to direct children of the root layout.
                     * Sits before the theme CSS so the theme can
                     * still tweak alignment behavior if it needs to.
                     */}
                    <style>{ALIGNMENT_OVERRIDE_STYLES}</style>
                    {/*
                     * Keystone #47: the active theme's compiled CSS
                     * (theme.json tokens + hand-authored `style.css`)
                     * gets inlined here, after the package default so
                     * theme rules win on cascade. Falls back to no-op
                     * when no `apiBase` is wired or the fetch returns
                     * empty.
                     */}
                    <CanvasThemeStyles apiBase={apiBase} />
                    <BlockTools>
                        <WritingFlow>
                            <ObserveTyping>
                                <BlockList layout={ROOT_CANVAS_LAYOUT} />
                            </ObserveTyping>
                        </WritingFlow>
                    </BlockTools>
                </div>
            </div>
        </div>
    );
}
