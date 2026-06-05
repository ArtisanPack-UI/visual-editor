/**
 * Create-pattern dialog.
 *
 * Powers two flows:
 *
 *   1. The Patterns canvas "New {synced/unsynced} pattern" button —
 *      creates an empty pattern and opens it in the canvas.
 *   2. The "Convert to pattern" action in either editor canvas —
 *      receives a pre-supplied block tree (the user's current selection)
 *      and lets them name + categorise it before saving.
 *
 * The dialog asks for: name, slug (auto-derived but editable), sync type
 * (radio per design brief §5.4 — synced / unsynced, immutable post-
 * creation per F6 / P9), and categories. The captions on the radios
 * deliberately mirror the brief's wording.
 */

import { __ } from '@wordpress/i18n';
import { serialize, type BlockInstance } from '@wordpress/blocks';
import {
    useCallback,
    useId,
    useMemo,
    useRef,
    useState,
    type FormEvent,
} from 'react';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import type { SiteEditorApiConfig } from '../api-client';
import { normalizeSlugInput } from '../slug';

import {
    createPattern,
    SiteEditorApiError,
    type PatternCreatePayload,
    type PatternRecord,
    type ValidationErrors,
} from './api-client';
import { PatternDialog } from './pattern-dialog';

export type SyncChoice = 'synced' | 'unsynced';

export interface CreatePatternDialogProps {
    apiConfig: SiteEditorApiConfig;
    /** Pre-fill the radio choice — the canvas "New synced" / "New unsynced"
     *  buttons drive this, and the convert flow sends `null` so the user
     *  picks. */
    initialSync: SyncChoice | null;
    /** Pre-fill the block tree (and its serialized raw form) when
     *  converting a selection. `null` for empty-pattern creation. */
    sourceBlocks: readonly unknown[] | null;
    /** Initial name. Empty string for empty-pattern creation. */
    initialName?: string;
    onClose: () => void;
    onCreated: (
        record: PatternRecord,
        info: { sync: SyncChoice }
    ) => void;
}

function deriveSlug(name: string): string {
    return normalizeSlugInput(name);
}

export function CreatePatternDialog(
    props: CreatePatternDialogProps
): JSX.Element {
    const {
        apiConfig,
        initialSync,
        sourceBlocks,
        initialName = '',
        onClose,
        onCreated,
    } = props;

    const [name, setName] = useState<string>(initialName);
    const [slug, setSlug] = useState<string>(deriveSlug(initialName));
    const [slugTouched, setSlugTouched] = useState<boolean>(initialName !== '');
    // Convert flows from a block-selection pass `initialSync={null}` so
    // the user must explicitly pick synced vs. unsynced (sync status is
    // immutable post-creation per F6 / P9). Preserve the null and gate
    // the submit button on a real choice; the empty-pattern flow
    // (`initialSync !== null`) keeps the radios pre-selected.
    const [sync, setSync] = useState<SyncChoice | null>(initialSync);
    const [categoriesInput, setCategoriesInput] = useState<string>('');
    const [submitting, setSubmitting] = useState(false);
    const [submitError, setSubmitError] = useState<string | null>(null);
    const [validationErrors, setValidationErrors] =
        useState<ValidationErrors | null>(null);

    // `submitting` only flips after the next render, so two clicks in
    // the same React batch can both pass the gate and fire
    // `createPattern` twice. Guard with a synchronous ref.
    const isSubmittingRef = useRef(false);

    const descriptionId = useId();
    const nameId = useId();
    const slugId = useId();
    const categoriesId = useId();
    const syncedRadioId = useId();
    const unsyncedRadioId = useId();
    const nameErrorId = useId();
    const slugErrorId = useId();
    const syncErrorId = useId();

    const rawContent = useMemo<string>(() => {
        if (sourceBlocks === null || sourceBlocks.length === 0) {
            return '';
        }

        return serialize(sourceBlocks as BlockInstance[]);
    }, [sourceBlocks]);

    const handleNameChange = useCallback(
        (value: string): void => {
            setName(value);

            if (!slugTouched) {
                setSlug(deriveSlug(value));
            }
        },
        [slugTouched]
    );

    const handleSlugChange = useCallback((value: string): void => {
        setSlug(normalizeSlugInput(value));
        setSlugTouched(true);
    }, []);

    const handleSubmit = useCallback(
        async (event: FormEvent<HTMLFormElement>): Promise<void> => {
            event.preventDefault();

            if (isSubmittingRef.current) {
                return;
            }

            const trimmedName = name.trim();
            const finalSlug = slug.trim();

            if (finalSlug === '') {
                setValidationErrors({
                    slug: [__('Enter a slug to continue.', TEXT_DOMAIN)],
                });
                return;
            }

            if (sync === null) {
                setValidationErrors({
                    sync: [
                        __('Choose synced or unsynced.', TEXT_DOMAIN),
                    ],
                });
                return;
            }

            const finalSync: SyncChoice = sync;

            const payload: PatternCreatePayload = {
                slug: finalSlug,
                title: trimmedName,
                synced: finalSync === 'synced',
                content: {
                    raw: rawContent,
                    blocks: sourceBlocks ?? [],
                },
                categories: categoriesInput
                    .split(',')
                    .map((entry) => entry.trim())
                    .filter((entry) => entry !== ''),
                status: 'publish',
            };

            isSubmittingRef.current = true;
            setSubmitting(true);
            setSubmitError(null);
            setValidationErrors(null);

            try {
                const record = await createPattern(apiConfig, payload);

                onCreated(record, { sync: finalSync });
            } catch (error: unknown) {
                if (error instanceof SiteEditorApiError) {
                    setSubmitError(error.message);
                    setValidationErrors(error.validationErrors);
                } else {
                    setSubmitError(
                        __('Failed to create pattern.', TEXT_DOMAIN)
                    );
                }
            } finally {
                isSubmittingRef.current = false;
                setSubmitting(false);
            }
        },
        [
            apiConfig,
            categoriesInput,
            name,
            onCreated,
            rawContent,
            slug,
            sourceBlocks,
            sync,
        ]
    );

    const dialogTitle =
        sourceBlocks === null
            ? __('Add new pattern', TEXT_DOMAIN)
            : __('Convert selection to pattern', TEXT_DOMAIN);

    const slugError = validationErrors?.slug?.[0] ?? null;
    const titleError = validationErrors?.title?.[0] ?? null;

    return (
        <PatternDialog
            title={dialogTitle}
            onClose={onClose}
            testKey="create"
            descriptionId={descriptionId}
        >
            <p
                id={descriptionId}
                className="ap-pattern-dialog__notice"
                data-testid="ap-pattern-dialog-create-intro"
            >
                {sourceBlocks === null
                    ? __(
                          'Choose a sync type — this cannot be changed once the pattern is created.',
                          TEXT_DOMAIN
                      )
                    : __(
                          'A new pattern will be created from the selection. Sync type is permanent — pick carefully.',
                          TEXT_DOMAIN
                      )}
            </p>
            <form
                className="ap-pattern-dialog__body"
                onSubmit={(event) => void handleSubmit(event)}
            >
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
                        onChange={(event) =>
                            handleNameChange(event.target.value)
                        }
                        data-testid="ap-pattern-dialog-create-name"
                        aria-invalid={Boolean(titleError) || undefined}
                        aria-describedby={
                            titleError !== null ? nameErrorId : undefined
                        }
                    />
                    {titleError !== null ? (
                        <p
                            id={nameErrorId}
                            className="ap-pattern-dialog__error"
                            role="alert"
                        >
                            {titleError}
                        </p>
                    ) : null}
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
                            handleSlugChange(event.target.value)
                        }
                        data-testid="ap-pattern-dialog-create-slug"
                        aria-invalid={Boolean(slugError) || undefined}
                        aria-describedby={
                            slugError !== null ? slugErrorId : undefined
                        }
                    />
                    {slugError !== null ? (
                        <p
                            id={slugErrorId}
                            className="ap-pattern-dialog__error"
                            role="alert"
                        >
                            {slugError}
                        </p>
                    ) : null}
                </div>

                <fieldset
                    className="ap-pattern-dialog__field"
                    aria-describedby={
                        validationErrors?.sync?.[0] !== undefined
                            ? syncErrorId
                            : undefined
                    }
                >
                    <legend className="ap-pattern-dialog__label">
                        {__('Sync type', TEXT_DOMAIN)}
                    </legend>
                    <div className="ap-pattern-dialog__radio-group">
                        <label
                            className="ap-pattern-dialog__radio"
                            data-active={sync === 'synced'}
                        >
                            <span className="ap-pattern-dialog__radio-label">
                                <input
                                    id={syncedRadioId}
                                    type="radio"
                                    name="ap-pattern-sync"
                                    value="synced"
                                    checked={sync === 'synced'}
                                    onChange={() => setSync('synced')}
                                    data-testid="ap-pattern-dialog-create-sync-synced"
                                />
                                {__('Synced', TEXT_DOMAIN)}
                            </span>
                            <p className="ap-pattern-dialog__radio-caption">
                                {__(
                                    'Changes to this pattern will update every insertion.',
                                    TEXT_DOMAIN
                                )}
                            </p>
                        </label>
                        <label
                            className="ap-pattern-dialog__radio"
                            data-active={sync === 'unsynced'}
                        >
                            <span className="ap-pattern-dialog__radio-label">
                                <input
                                    id={unsyncedRadioId}
                                    type="radio"
                                    name="ap-pattern-sync"
                                    value="unsynced"
                                    checked={sync === 'unsynced'}
                                    onChange={() => setSync('unsynced')}
                                    data-testid="ap-pattern-dialog-create-sync-unsynced"
                                />
                                {__('Unsynced', TEXT_DOMAIN)}
                            </span>
                            <p className="ap-pattern-dialog__radio-caption">
                                {__(
                                    'A copy of these blocks will be inserted each time. Changes only affect future insertions.',
                                    TEXT_DOMAIN
                                )}
                            </p>
                        </label>
                    </div>
                    {validationErrors?.sync?.[0] !== undefined ? (
                        <p
                            id={syncErrorId}
                            className="ap-pattern-dialog__error"
                            role="alert"
                            data-testid="ap-pattern-dialog-create-sync-error"
                        >
                            {validationErrors.sync[0]}
                        </p>
                    ) : null}
                </fieldset>

                <div className="ap-pattern-dialog__field">
                    <label
                        className="ap-pattern-dialog__label"
                        htmlFor={categoriesId}
                    >
                        {__('Categories', TEXT_DOMAIN)}
                    </label>
                    <input
                        id={categoriesId}
                        type="text"
                        className="ap-pattern-dialog__input"
                        value={categoriesInput}
                        onChange={(event) =>
                            setCategoriesInput(event.target.value)
                        }
                        placeholder={__(
                            'Comma-separated, e.g. featured, hero',
                            TEXT_DOMAIN
                        )}
                        data-testid="ap-pattern-dialog-create-categories"
                    />
                </div>

                {submitError !== null ? (
                    <p
                        className="ap-pattern-dialog__error"
                        role="alert"
                        data-testid="ap-pattern-dialog-create-error"
                    >
                        {submitError}
                    </p>
                ) : null}

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
                        type="submit"
                        className="ap-pattern-dialog__submit"
                        disabled={submitting || sync === null}
                        data-testid="ap-pattern-dialog-create-submit"
                    >
                        {submitting
                            ? __('Creating…', TEXT_DOMAIN)
                            : __('Create', TEXT_DOMAIN)}
                    </button>
                </footer>
            </form>
        </PatternDialog>
    );
}
