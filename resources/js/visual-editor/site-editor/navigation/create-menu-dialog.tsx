/**
 * "Add new menu" dialog.
 *
 * Captures slug + name + (optional) location at create time and
 * dispatches the C4 store endpoint. Shares the
 * `.ap-site-editor__dialog-*` chrome with the templates / parts
 * create dialog so the dialog widgets render identically — only the
 * inner form is nav-specific.
 */

import { __ } from '@wordpress/i18n';
import {
    useCallback,
    useEffect,
    useId,
    useMemo,
    useRef,
    useState,
    type FormEvent,
} from 'react';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { SiteEditorApiError, type SiteEditorApiConfig } from '../api-client';
import { normalizeSlugInput } from '../slug';
import {
    createNavigation,
    type MenuLocation,
    type NavigationRecord,
} from './api-client';
import {
    extractValidationErrors,
} from './api-client';

export interface CreateMenuDialogProps {
    apiConfig: SiteEditorApiConfig;
    locations: readonly MenuLocation[];
    onClose: () => void;
    onCreated: (entity: NavigationRecord) => void;
}

export function CreateMenuDialog(props: CreateMenuDialogProps): JSX.Element {
    const { apiConfig, locations, onClose, onCreated } = props;

    const slugId = useId();
    const titleId = useId();
    const locationId = useId();
    const titleErrorId = useId();
    const slugErrorId = useId();
    const locationErrorId = useId();

    const [slug, setSlug] = useState('');
    const [title, setTitle] = useState('');
    const [location, setLocation] = useState<string>('');
    const [submitting, setSubmitting] = useState(false);
    const [generalError, setGeneralError] = useState<string | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const titleRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        titleRef.current?.focus();
    }, []);

    const handleClose = useCallback((): void => {
        if (submitting) {
            return;
        }

        onClose();
    }, [onClose, submitting]);

    useEffect(() => {
        const handleKey = (event: KeyboardEvent): void => {
            if (event.key === 'Escape') {
                handleClose();
            }
        };

        window.addEventListener('keydown', handleKey);

        return () => window.removeEventListener('keydown', handleKey);
    }, [handleClose]);

    const handleSubmit = async (event: FormEvent): Promise<void> => {
        event.preventDefault();

        const trimmedSlug = slug.trim();
        const trimmedTitle = title.trim();

        if (trimmedSlug === '') {
            setErrors({
                slug: __('Slug is required.', TEXT_DOMAIN),
            });
            return;
        }

        setSubmitting(true);
        setGeneralError(null);
        setErrors({});

        try {
            const created = await createNavigation(apiConfig, {
                slug: trimmedSlug,
                title: trimmedTitle,
                location: location === '' ? null : location,
                content: { raw: '', blocks: [] },
            });

            onCreated(created);
        } catch (error: unknown) {
            if (error instanceof SiteEditorApiError) {
                const validation = extractValidationErrors(error.body);

                if (validation !== null) {
                    const flat: Record<string, string> = {};

                    for (const [field, messages] of Object.entries(validation)) {
                        const first = messages[0];

                        if (first !== undefined) {
                            flat[field] = first;
                        }
                    }

                    setErrors(flat);
                } else {
                    setGeneralError(error.message);
                }
            } else if (error instanceof Error) {
                setGeneralError(error.message);
            } else {
                setGeneralError(__('Failed to create menu.', TEXT_DOMAIN));
            }
        } finally {
            setSubmitting(false);
        }
    };

    const titleLabel = useMemo(
        () => __('Menu name', TEXT_DOMAIN),
        []
    );

    return (
        <div
            className="ap-site-editor__dialog-scrim"
            data-testid="ap-create-menu-backdrop"
            onClick={(event) => {
                if (event.target === event.currentTarget) {
                    handleClose();
                }
            }}
        >
            <div
                className="ap-site-editor__dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby={titleId}
            >
                <header className="ap-site-editor__dialog-header">
                    <h2 id={titleId} className="ap-site-editor__dialog-title">
                        {__('Add new menu', TEXT_DOMAIN)}
                    </h2>
                    <button
                        type="button"
                        className="ap-site-editor__dialog-close"
                        onClick={handleClose}
                        aria-label={__('Close', TEXT_DOMAIN)}
                        data-testid="ap-create-menu-close"
                    >
                        {'×'}
                    </button>
                </header>

                <form
                    onSubmit={(event) => void handleSubmit(event)}
                    className="ap-site-editor__dialog-form"
                >
                    <div className="ap-site-editor__dialog-body">
                        <div className="ap-site-editor__dialog-field">
                            <label
                                className="ap-site-editor__dialog-label"
                                htmlFor={`${titleId}-input`}
                            >
                                {titleLabel}
                            </label>
                            <input
                                ref={titleRef}
                                id={`${titleId}-input`}
                                type="text"
                                className="ap-site-editor__dialog-input"
                                value={title}
                                onChange={(event) =>
                                    setTitle(event.target.value)
                                }
                                data-testid="ap-create-menu-title"
                                aria-invalid={
                                    Boolean(errors.title) || undefined
                                }
                                aria-describedby={
                                    errors.title !== undefined
                                        ? titleErrorId
                                        : undefined
                                }
                            />
                            {errors.title !== undefined ? (
                                <p
                                    id={titleErrorId}
                                    className="ap-site-editor__dialog-field-error"
                                    role="alert"
                                >
                                    {errors.title}
                                </p>
                            ) : null}
                        </div>

                        <div className="ap-site-editor__dialog-field">
                            <label
                                className="ap-site-editor__dialog-label"
                                htmlFor={slugId}
                            >
                                {__('Slug', TEXT_DOMAIN)}
                            </label>
                            <input
                                id={slugId}
                                type="text"
                                className="ap-site-editor__dialog-input"
                                value={slug}
                                onChange={(event) =>
                                    setSlug(
                                        normalizeSlugInput(event.target.value)
                                    )
                                }
                                placeholder={__('e.g. primary', TEXT_DOMAIN)}
                                data-testid="ap-create-menu-slug"
                                aria-invalid={
                                    Boolean(errors.slug) || undefined
                                }
                                aria-describedby={
                                    errors.slug !== undefined
                                        ? slugErrorId
                                        : undefined
                                }
                            />
                            {errors.slug !== undefined ? (
                                <p
                                    id={slugErrorId}
                                    className="ap-site-editor__dialog-field-error"
                                    role="alert"
                                >
                                    {errors.slug}
                                </p>
                            ) : null}
                        </div>

                        <div className="ap-site-editor__dialog-field">
                            <label
                                className="ap-site-editor__dialog-label"
                                htmlFor={locationId}
                            >
                                {__('Location (optional)', TEXT_DOMAIN)}
                            </label>
                            <select
                                id={locationId}
                                className="ap-site-editor__dialog-select"
                                value={location}
                                onChange={(event) =>
                                    setLocation(event.target.value)
                                }
                                data-testid="ap-create-menu-location"
                                aria-invalid={
                                    Boolean(errors.location) || undefined
                                }
                                aria-describedby={
                                    errors.location !== undefined
                                        ? locationErrorId
                                        : undefined
                                }
                            >
                                <option value="">
                                    {__('— No location —', TEXT_DOMAIN)}
                                </option>
                                {locations.map((entry) => (
                                    <option
                                        key={entry.slug}
                                        value={entry.slug}
                                    >
                                        {entry.label}
                                    </option>
                                ))}
                            </select>
                            {errors.location !== undefined ? (
                                <p
                                    id={locationErrorId}
                                    className="ap-site-editor__dialog-field-error"
                                    role="alert"
                                >
                                    {errors.location}
                                </p>
                            ) : null}
                        </div>

                        {generalError !== null ? (
                            <p
                                className="ap-site-editor__dialog-error"
                                role="alert"
                                data-testid="ap-create-menu-error"
                            >
                                {generalError}
                            </p>
                        ) : null}
                    </div>

                    <footer className="ap-site-editor__dialog-footer">
                        <button
                            type="button"
                            onClick={handleClose}
                            className="ap-site-editor__dialog-cancel"
                            disabled={submitting}
                            data-testid="ap-create-menu-cancel"
                        >
                            {__('Cancel', TEXT_DOMAIN)}
                        </button>
                        <button
                            type="submit"
                            className="ap-site-editor__dialog-submit"
                            disabled={submitting}
                            data-testid="ap-create-menu-submit"
                        >
                            {submitting
                                ? __('Creating…', TEXT_DOMAIN)
                                : __('Create menu', TEXT_DOMAIN)}
                        </button>
                    </footer>
                </form>
            </div>
        </div>
    );
}
