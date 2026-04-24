/**
 * Site-editor canvas frame.
 *
 * The shell-level wrapper around the iframe canvas D2–D5 will render
 * entity content into. For D1 (#368) the canvas only needs to:
 *   1. Mount a real `BlockEditorProvider` + `BlockCanvas` so the iframe
 *      is created and isolates editor styles from admin chrome (per the
 *      issue's acceptance criteria + macro brief §3.2);
 *   2. Show an explicit empty state when no entity is selected so the
 *      shell isn't a void of grey.
 *
 * The empty state copy is intentionally generic — D2–D5 replace this
 * outlet entirely once they own real entity rendering.
 */

import type { ReactNode } from 'react';
import { useMemo } from 'react';
import {
    BlockCanvas,
    BlockEditorProvider,
} from '@wordpress/block-editor';
import { Popover, SlotFillProvider } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { BlockInstance } from '@wordpress/blocks';

import { TEXT_DOMAIN } from '../vendor/i18n';

import './canvas-frame.css';

export interface CanvasFrameProps {
    /**
     * Caption shown in the empty state — typically the active section's
     * mode label so the user immediately knows which scope they are in.
     */
    sectionLabel: string;
    /**
     * `true` once D2–D5 hand the shell an entity to edit. D1 always
     * passes `false` so the empty state renders.
     */
    hasEntity?: boolean;
    /**
     * Optional override for the iframe contents. Reserved for D2–D5;
     * when omitted, the shell draws its own empty-state placeholder
     * inside the iframe.
     */
    children?: ReactNode;
}

const EMPTY_BLOCKS: BlockInstance[] = [];

const EMPTY_SETTINGS = Object.freeze({});

export function CanvasFrame(props: CanvasFrameProps): JSX.Element {
    const { sectionLabel, hasEntity = false, children } = props;

    const emptyState = useMemo(
        () => (
            <div
                className="ap-site-editor__canvas-empty"
                role="status"
                aria-live="polite"
                data-testid="ap-site-editor-canvas-empty"
            >
                <p className="ap-site-editor__canvas-empty-title">
                    {sectionLabel}
                </p>
                <p className="ap-site-editor__canvas-empty-body">
                    {__(
                        'Select an entity from the navigator to start editing.',
                        TEXT_DOMAIN
                    )}
                </p>
            </div>
        ),
        [sectionLabel]
    );

    return (
        <div
            className="ap-site-editor__canvas"
            data-has-entity={hasEntity}
            data-testid="ap-site-editor-canvas"
        >
            <SlotFillProvider>
                <BlockEditorProvider
                    value={EMPTY_BLOCKS}
                    settings={EMPTY_SETTINGS}
                    onChange={() => undefined}
                    onInput={() => undefined}
                >
                    <BlockCanvas height="100%">
                        {hasEntity ? children : emptyState}
                    </BlockCanvas>
                    <Popover.Slot />
                </BlockEditorProvider>
            </SlotFillProvider>
        </div>
    );
}
