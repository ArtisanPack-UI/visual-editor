/**
 * "Convert to unsynced copy" confirmation.
 *
 * Replaces Gutenberg's "Detach" affordance per design brief F6 / P9.
 * The dialog explicitly names what will happen:
 *   - a NEW unsynced pattern is created carrying the current block tree
 *   - the original synced pattern is left untouched (and so are existing
 *     insertions of it)
 *   - sync status of the new pattern is permanent — it cannot be flipped
 *     back to synced later
 *
 * The action calls `createPattern` with `synced: false` and the source
 * pattern's serialized block tree.
 */

import { __, sprintf } from '@wordpress/i18n';
import { serialize, type BlockInstance } from '@wordpress/blocks';
import { useCallback, useId, useRef, useState } from 'react';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import type { SiteEditorApiConfig } from '../api-client';
import { normalizeSlugInput } from '../slug';

import {
    createPattern,
    SiteEditorApiError,
    type PatternRecord,
} from './api-client';
import { PatternDialog } from './pattern-dialog';

export interface ConvertToUnsyncedDialogProps {
    apiConfig: SiteEditorApiConfig;
    /** Source synced pattern — the dialog reads its title + content. */
    source: PatternRecord;
    /**
     * Optional override for the working block tree (e.g. unsaved
     * canvas state). Falls back to the saved record content when
     * absent.
     */
    workingBlocks?: readonly unknown[] | null;
    onClose: () => void;
    onCreated: (record: PatternRecord) => void;
}

function deriveCopyName(sourceTitle: string): string {
    return sprintf(
        /* translators: %s: original pattern name. */
        __('Copy of %s', TEXT_DOMAIN),
        sourceTitle
    );
}

function deriveCopySlug(sourceSlug: string): string {
    return normalizeSlugInput(`${sourceSlug}-copy`);
}

export function ConvertToUnsyncedDialog(
    props: ConvertToUnsyncedDialogProps
): JSX.Element {
    const { apiConfig, source, workingBlocks, onClose, onCreated } = props;

    const sourceTitle =
        source.title?.raw?.trim() ||
        source.title?.rendered?.trim() ||
        source.slug;

    const [name, setName] = useState<string>(deriveCopyName(sourceTitle));
    const [slug, setSlug] = useState<string>(deriveCopySlug(source.slug));
    const [submitting, setSubmitting] = useState(false);
    const [submitError, setSubmitError] = useState<string | null>(null);

    // `submitting` only flips after a re-render, so two clicks landing
    // in the same React batch can both pass the `if (submitting)` check
    // and fire `createPattern` twice. Guard with a synchronous ref so
    // the second click bails immediately.
    const isSubmittingRef = useRef(false);

    const descriptionId = useId();
    const nameId = useId();
    const slugId = useId();

    const handleConvert = useCallback(async (): Promise<void> => {
        if (isSubmittingRef.current) {
            return;
        }

        const finalSlug = slug.trim();
        const finalName = name.trim();

        if (finalSlug === '') {
            setSubmitError(__('Enter a slug to continue.', TEXT_DOMAIN));
            return;
        }

        const blocks =
            workingBlocks !== undefined && workingBlocks !== null
                ? workingBlocks
                : source.content.blocks;
        const raw =
            workingBlocks !== undefined && workingBlocks !== null
                ? serialize(workingBlocks as BlockInstance[])
                : source.content.raw;

        isSubmittingRef.current = true;
        setSubmitting(true);
        setSubmitError(null);

        try {
            const record = await createPattern(apiConfig, {
                slug: finalSlug,
                title: finalName,
                synced: false,
                content: { raw, blocks },
                categories: [...source.categories],
                status: source.status,
            });

            onCreated(record);
        } catch (error: unknown) {
            if (error instanceof SiteEditorApiError) {
                setSubmitError(error.message);
            } else {
                setSubmitError(
                    __('Failed to create unsynced copy.', TEXT_DOMAIN)
                );
            }
        } finally {
            isSubmittingRef.current = false;
            setSubmitting(false);
        }
    }, [apiConfig, name, onCreated, slug, source, workingBlocks]);

    return (
        <PatternDialog
            title={__('Convert to unsynced copy', TEXT_DOMAIN)}
            onClose={onClose}
            testKey="convert"
            descriptionId={descriptionId}
        >
            <div
                id={descriptionId}
                className="ap-pattern-dialog__body"
                data-testid="ap-pattern-dialog-convert-body"
            >
                <p
                    className="ap-pattern-dialog__notice"
                    data-testid="ap-pattern-dialog-convert-warning"
                >
                    {__(
                        'A new unsynced pattern will be created with this content. The original synced pattern is left as-is, and every place that already references it keeps doing so. Sync status is permanent: this new pattern cannot be flipped back to synced later.',
                        TEXT_DOMAIN
                    )}
                </p>

                <div className="ap-pattern-dialog__field">
                    <label
                        className="ap-pattern-dialog__label"
                        htmlFor={nameId}
                    >
                        {__('Name', TEXT_DOMAIN)}
                    </label>
                    <input
                        id={nameId}
                        type="text"
                        className="ap-pattern-dialog__input"
                        value={name}
                        onChange={(event) => setName(event.target.value)}
                        data-testid="ap-pattern-dialog-convert-name"
                        autoFocus
                    />
                </div>
                <div className="ap-pattern-dialog__field">
                    <label
                        className="ap-pattern-dialog__label"
                        htmlFor={slugId}
                    >
                        {__('Slug', TEXT_DOMAIN)}
                    </label>
                    <input
                        id={slugId}
                        type="text"
                        className="ap-pattern-dialog__input"
                        value={slug}
                        onChange={(event) =>
                            setSlug(normalizeSlugInput(event.target.value))
                        }
                        data-testid="ap-pattern-dialog-convert-slug"
                    />
                </div>

                {submitError !== null ? (
                    <p
                        className="ap-pattern-dialog__error"
                        role="alert"
                        data-testid="ap-pattern-dialog-convert-error"
                    >
                        {submitError}
                    </p>
                ) : null}
            </div>
            <footer className="ap-pattern-dialog__footer">
                <button
                    type="button"
                    className="ap-pattern-dialog__cancel"
                    onClick={onClose}
                    disabled={submitting}
                >
                    {__('Cancel', TEXT_DOMAIN)}
                </button>
                <button
                    type="button"
                    className="ap-pattern-dialog__submit"
                    onClick={() => void handleConvert()}
                    disabled={submitting}
                    data-testid="ap-pattern-dialog-convert-submit"
                >
                    {submitting
                        ? __('Creating copy…', TEXT_DOMAIN)
                        : __('Create unsynced copy', TEXT_DOMAIN)}
                </button>
            </footer>
        </PatternDialog>
    );
}
