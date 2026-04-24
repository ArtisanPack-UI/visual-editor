/**
 * Create-new dialog for templates and template parts.
 *
 * Implements the D0 §3.4 / §3.5 create-new flow: for templates, a slug
 * picker gated by the fallback chain (P7 — fallback chains are visible
 * to users); for parts, an area selector. Same dialog shell either way —
 * the caller supplies kind-specific fields via the `fields` prop.
 *
 * Focus-traps inside the dialog while open per WAI-ARIA dialog pattern:
 *   - Esc closes.
 *   - Initial focus goes to the first interactive control.
 *   - Tab/Shift+Tab wrap within the dialog.
 */

import { __ } from '@wordpress/i18n';
import {
    useCallback,
    useEffect,
    useId,
    useRef,
    useState,
    type FormEvent,
    type KeyboardEvent as ReactKeyboardEvent,
    type ReactNode,
} from 'react';

import { TEXT_DOMAIN } from '../vendor/i18n';

import {
    createEntity,
    SiteEditorApiError,
    type CreatePayload,
    type EntityKind,
    type EntityRecord,
    type SiteEditorApiConfig,
    type ValidationErrors,
} from './api-client';

import './create-entity-dialog.css';

export interface CreateEntityDialogProps<K extends EntityKind> {
    apiConfig: SiteEditorApiConfig;
    kind: K;
    title: string;
    /** Fields rendered between the title field and the footer actions. */
    renderFields: (api: {
        validationErrors: ValidationErrors | null;
    }) => ReactNode;
    /**
     * Builds the create payload from the dialog form state. Called at
     * submit time. Returning `null` blocks submission (e.g. the caller
     * surfaces its own validation error to the user).
     */
    buildPayload: () => CreatePayload<K> | null;
    onClose: () => void;
    onCreated: (entity: EntityRecord<K>) => void;
}

function makeFocusables(container: HTMLElement): HTMLElement[] {
    return Array.from(
        container.querySelectorAll<HTMLElement>(
            'button,[href],input,select,textarea,[tabindex]:not([tabindex="-1"])'
        )
    ).filter((element) => !element.hasAttribute('disabled'));
}

export function CreateEntityDialog<K extends EntityKind>(
    props: CreateEntityDialogProps<K>
): JSX.Element {
    const { apiConfig, kind, title, renderFields, buildPayload, onClose, onCreated } =
        props;

    const dialogRef = useRef<HTMLDivElement | null>(null);
    const previousFocusRef = useRef<HTMLElement | null>(null);
    const titleId = useId();

    const [submitting, setSubmitting] = useState(false);
    const [submitError, setSubmitError] = useState<string | null>(null);
    const [validationErrors, setValidationErrors] = useState<ValidationErrors | null>(
        null
    );

    useEffect(() => {
        if (typeof document === 'undefined') {
            return;
        }

        previousFocusRef.current =
            document.activeElement instanceof HTMLElement
                ? document.activeElement
                : null;

        const dialog = dialogRef.current;

        if (dialog !== null) {
            const focusables = makeFocusables(dialog);
            focusables[0]?.focus();
        }

        return () => {
            previousFocusRef.current?.focus();
        };
    }, []);

    const handleKeyDown = useCallback(
        (event: ReactKeyboardEvent<HTMLDivElement>): void => {
            if (event.key === 'Escape') {
                event.preventDefault();
                onClose();
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            const dialog = dialogRef.current;

            if (dialog === null) {
                return;
            }

            const focusables = makeFocusables(dialog);

            if (focusables.length === 0) {
                return;
            }

            const first = focusables[0];
            const last = focusables[focusables.length - 1];

            if (first === undefined || last === undefined) {
                return;
            }

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },
        [onClose]
    );

    const handleSubmit = useCallback(
        async (event: FormEvent<HTMLFormElement>): Promise<void> => {
            event.preventDefault();

            if (submitting) {
                return;
            }

            const payload = buildPayload();

            if (payload === null) {
                return;
            }

            setSubmitting(true);
            setSubmitError(null);
            setValidationErrors(null);

            try {
                const entity = await createEntity(apiConfig, kind, payload);

                onCreated(entity);
            } catch (error: unknown) {
                if (error instanceof SiteEditorApiError) {
                    setSubmitError(error.message);
                    setValidationErrors(error.validationErrors);
                } else {
                    setSubmitError(__('Failed to create.', TEXT_DOMAIN));
                }
            } finally {
                setSubmitting(false);
            }
        },
        [apiConfig, buildPayload, kind, onCreated, submitting]
    );

    return (
        <div
            className="ap-site-editor__dialog-scrim"
            data-testid={`ap-site-editor-create-dialog-${kind}`}
            onClick={(event) => {
                if (event.target === event.currentTarget) {
                    onClose();
                }
            }}
        >
            <div
                ref={dialogRef}
                className="ap-site-editor__dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby={titleId}
                onKeyDown={handleKeyDown}
            >
                <header className="ap-site-editor__dialog-header">
                    <h2 id={titleId} className="ap-site-editor__dialog-title">
                        {title}
                    </h2>
                    <button
                        type="button"
                        className="ap-site-editor__dialog-close"
                        aria-label={__('Close dialog', TEXT_DOMAIN)}
                        onClick={onClose}
                        data-testid={`ap-site-editor-create-dialog-close-${kind}`}
                    >
                        {'×'}
                    </button>
                </header>
                <form
                    className="ap-site-editor__dialog-form"
                    onSubmit={(event) => void handleSubmit(event)}
                >
                    <div className="ap-site-editor__dialog-body">
                        {renderFields({ validationErrors })}
                        {submitError !== null ? (
                            <p
                                className="ap-site-editor__dialog-error"
                                role="alert"
                                data-testid={`ap-site-editor-create-dialog-error-${kind}`}
                            >
                                {submitError}
                            </p>
                        ) : null}
                    </div>
                    <footer className="ap-site-editor__dialog-footer">
                        <button
                            type="button"
                            className="ap-site-editor__dialog-cancel"
                            onClick={onClose}
                            disabled={submitting}
                        >
                            {__('Cancel', TEXT_DOMAIN)}
                        </button>
                        <button
                            type="submit"
                            className="ap-site-editor__dialog-submit"
                            disabled={submitting}
                            data-testid={`ap-site-editor-create-dialog-submit-${kind}`}
                        >
                            {submitting
                                ? __('Creating…', TEXT_DOMAIN)
                                : __('Create', TEXT_DOMAIN)}
                        </button>
                    </footer>
                </form>
            </div>
        </div>
    );
}
