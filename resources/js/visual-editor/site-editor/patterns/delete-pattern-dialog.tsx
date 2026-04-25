/**
 * Delete-pattern confirmation.
 *
 * Per design brief P9 (destructive actions name their effect and require
 * explicit confirmation) the dialog spells out exactly what will happen
 * when the user proceeds. For synced patterns we additionally surface a
 * usage count derived client-side from the templates + parts catalog —
 * deleting a synced pattern with active references *will* leave broken
 * `core/block` markers behind, so the user gets to abort if the count is
 * non-zero.
 */

import { __, _n, sprintf } from '@wordpress/i18n';
import {
    useEffect,
    useId,
    useRef,
    useState,
} from 'react';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import type { SiteEditorApiConfig } from '../api-client';

import {
    deletePattern,
    SiteEditorApiError,
    type PatternRecord,
} from './api-client';
import { PatternDialog } from './pattern-dialog';
import { usePatternUsage, type UsageBreakdown } from './use-pattern-usage';

export interface DeletePatternDialogProps {
    apiConfig: SiteEditorApiConfig;
    pattern: PatternRecord;
    onClose: () => void;
    onDeleted: (pattern: PatternRecord) => void;
}

function patternTitle(pattern: PatternRecord): string {
    const raw = pattern.title?.raw?.trim();

    if (raw !== undefined && raw !== '') {
        return raw;
    }

    const rendered = pattern.title?.rendered?.trim();

    if (rendered !== undefined && rendered !== '') {
        return rendered;
    }

    return pattern.slug;
}

function describeUsage(usage: UsageBreakdown): string {
    if (usage.total === 0) {
        return __(
            'This synced pattern is not currently inserted anywhere.',
            TEXT_DOMAIN
        );
    }

    const segments: string[] = [];

    if (usage.perKind.template > 0) {
        segments.push(
            sprintf(
                /* translators: %s: count of templates. */
                _n(
                    '%s template',
                    '%s templates',
                    usage.perKind.template,
                    TEXT_DOMAIN
                ),
                String(usage.perKind.template)
            )
        );
    }

    if (usage.perKind['template-part'] > 0) {
        segments.push(
            sprintf(
                /* translators: %s: count of template parts. */
                _n(
                    '%s template part',
                    '%s template parts',
                    usage.perKind['template-part'],
                    TEXT_DOMAIN
                ),
                String(usage.perKind['template-part'])
            )
        );
    }

    return sprintf(
        /* translators: %1$s: total references, %2$s: per-kind breakdown. */
        _n(
            'This synced pattern is referenced %1$s time (%2$s). Deleting it will leave broken references behind.',
            'This synced pattern is referenced %1$s times (%2$s). Deleting it will leave broken references behind.',
            usage.total,
            TEXT_DOMAIN
        ),
        String(usage.total),
        segments.join(', ')
    );
}

export function DeletePatternDialog(
    props: DeletePatternDialogProps
): JSX.Element {
    const { apiConfig, pattern, onClose, onDeleted } = props;

    const descriptionId = useId();
    const usage = usePatternUsage({ apiConfig });
    const [submitting, setSubmitting] = useState(false);
    const [submitError, setSubmitError] = useState<string | null>(null);

    // `submitting` only flips after the next render, so two clicks in
    // the same React batch could both pass the gate and dispatch
    // `deletePattern` twice (the second hits 404). Guard with a
    // synchronous ref.
    const isSubmittingRef = useRef(false);

    useEffect(() => {
        if (pattern.synced) {
            void usage.run(pattern.id);
        }

        return () => {
            usage.reset();
        };
        // The hook's `run`/`reset` are stable; we only want to re-run
        // the count when the target pattern's id changes.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [pattern.id, pattern.synced]);

    // Block delete-pattern confirmation until the usage lookup
    // finishes. For synced patterns the count drives the warning copy,
    // and shipping the destructive action before the lookup resolves
    // could let the user delete a heavily-referenced pattern without
    // ever seeing the breakdown. Unsynced patterns skip the count
    // lookup entirely, so no gating is needed for them.
    const usageLookupPending =
        pattern.synced &&
        usage.error === null &&
        usage.usage === null;
    const isDisabled = submitting || usageLookupPending;

    const handleDelete = async (): Promise<void> => {
        if (isDisabled || isSubmittingRef.current) {
            return;
        }

        isSubmittingRef.current = true;
        setSubmitting(true);
        setSubmitError(null);

        try {
            await deletePattern(apiConfig, pattern.id);
            onDeleted(pattern);
        } catch (error: unknown) {
            const message =
                error instanceof SiteEditorApiError
                    ? error.message
                    : __('Failed to delete pattern.', TEXT_DOMAIN);

            setSubmitError(message);
        } finally {
            isSubmittingRef.current = false;
            setSubmitting(false);
        }
    };

    return (
        <PatternDialog
            title={sprintf(
                /* translators: %s: pattern title. */
                __('Delete pattern: %s', TEXT_DOMAIN),
                patternTitle(pattern)
            )}
            onClose={onClose}
            testKey="delete"
            descriptionId={descriptionId}
        >
            <div
                id={descriptionId}
                className="ap-pattern-dialog__body"
                data-testid="ap-pattern-dialog-delete-body"
            >
                <p className="ap-pattern-dialog__notice">
                    {pattern.synced
                        ? __(
                              'This will permanently delete the synced pattern.',
                              TEXT_DOMAIN
                          )
                        : __(
                              'This will permanently delete the pattern. Existing copies that were already inserted in templates or posts are unaffected.',
                              TEXT_DOMAIN
                          )}
                </p>

                {pattern.synced ? (
                    <div
                        className="ap-pattern-dialog__notice"
                        data-testid="ap-pattern-dialog-delete-usage"
                    >
                        {usage.isLoading ? (
                            <p>
                                {__(
                                    'Counting where this pattern is used…',
                                    TEXT_DOMAIN
                                )}
                            </p>
                        ) : usage.error !== null ? (
                            <p>
                                {__(
                                    'Could not count usages; proceed with care.',
                                    TEXT_DOMAIN
                                )}
                            </p>
                        ) : usage.usage !== null ? (
                            <>
                                <p
                                    className="ap-pattern-dialog__usage-summary"
                                    data-testid="ap-pattern-dialog-delete-usage-count"
                                >
                                    {usage.usage.total === 0
                                        ? __('Used in 0 places', TEXT_DOMAIN)
                                        : sprintf(
                                              /* translators: %s: total references. */
                                              _n(
                                                  'Used in %s place',
                                                  'Used in %s places',
                                                  usage.usage.total,
                                                  TEXT_DOMAIN
                                              ),
                                              String(usage.usage.total)
                                          )}
                                </p>
                                <p>{describeUsage(usage.usage)}</p>
                            </>
                        ) : null}
                    </div>
                ) : null}

                {submitError !== null ? (
                    <p
                        className="ap-pattern-dialog__error"
                        role="alert"
                        data-testid="ap-pattern-dialog-delete-error"
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
                    className="ap-pattern-dialog__submit ap-pattern-dialog__submit--danger"
                    onClick={() => void handleDelete()}
                    disabled={isDisabled}
                    data-testid="ap-pattern-dialog-delete-submit"
                >
                    {submitting
                        ? __('Deleting…', TEXT_DOMAIN)
                        : __('Delete pattern', TEXT_DOMAIN)}
                </button>
            </footer>
        </PatternDialog>
    );
}
