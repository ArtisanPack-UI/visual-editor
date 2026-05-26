/**
 * Form — editor-side render.
 *
 * Shows a Form picker in the InspectorControls sidebar populated from
 * `/api/v1/forms`, plus a static preview of the selected form's field
 * layout fetched from `/api/v1/forms/{id}/render`. The preview is
 * non-interactive on purpose — the React `FormRenderer` lives in the
 * consumer (e.g. Keystone) and isn't available inside the editor
 * iframe; this view is enough for authors to see "yes, that's the
 * right form" without us coupling the visual-editor package to the
 * forms package's runtime.
 */

import { useEffect, useState, type ReactElement } from 'react';
import {
    InspectorControls,
    useBlockProps,
} from '@wordpress/block-editor';
import { Button, Placeholder, PanelBody, SelectControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

import FormInserterIcon from './inserter-icon';

interface FormAttributes {
    readonly formId: number;
}

interface FormEditProps {
    readonly attributes: FormAttributes;
    readonly setAttributes: (next: Partial<FormAttributes>) => void;
}

interface ApiForm {
    readonly id: number;
    readonly name: string;
    readonly slug: string;
    readonly is_active: boolean;
    readonly submit_button_text?: string;
}

interface PaginatedForms {
    readonly data: ApiForm[];
}

interface FormField {
    readonly id: number;
    readonly name: string;
    readonly label: string | null;
    readonly type: string;
    readonly placeholder: string | null;
    readonly help_text: string | null;
    readonly is_required: boolean;
    readonly default_value: string | null;
    readonly options?: ReadonlyArray<{ label: string; value: string }>;
    readonly field_config?: { options?: ReadonlyArray<{ label: string; value: string }> } | null;
}

interface FormDetailResponse {
    readonly data: {
        readonly id: number;
        readonly name: string;
        readonly slug: string;
        readonly submit_button_text: string;
        readonly fields?: FormField[];
    };
}

function readMetaCsrfToken(): string | null {
    if ('undefined' === typeof document) {
        return null;
    }
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta?.getAttribute('content') ?? null;
}

function readXsrfCookie(): string | null {
    if ('undefined' === typeof document) {
        return null;
    }
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : null;
}

function buildHeaders(): Record<string, string> {
    const headers: Record<string, string> = { Accept: 'application/json' };
    const csrf = readMetaCsrfToken();
    const xsrf = readXsrfCookie();
    if (csrf) headers['X-CSRF-TOKEN'] = csrf;
    if (xsrf) headers['X-XSRF-TOKEN'] = xsrf;
    return headers;
}

const TEXTAREA_TYPES = new Set(['textarea', 'paragraph']);
const CHOICE_TYPES = new Set(['select', 'radio', 'checkbox', 'checkbox_group', 'select_multiple']);
const LAYOUT_TYPES = new Set(['heading', 'paragraph_layout', 'divider', 'html']);

function FieldPreview({ field }: { readonly field: FormField }): ReactElement {
    const label = field.label ?? field.name;
    const inputId = `form-block-preview-${field.id}`;
    const options = field.field_config?.options ?? field.options ?? [];

    if ('divider' === field.type) {
        return <hr aria-hidden="true" style={{ margin: '12px 0', borderColor: 'rgba(0,0,0,0.1)' }} />;
    }

    if ('heading' === field.type) {
        return <h3 style={{ margin: '8px 0', fontWeight: 600 }}>{label}</h3>;
    }

    if (LAYOUT_TYPES.has(field.type)) {
        return <p style={{ margin: '8px 0', color: 'rgba(0,0,0,0.6)' }}>{label}</p>;
    }

    const labelEl = (
        <label htmlFor={inputId} style={{ display: 'block', fontWeight: 500, fontSize: 13, marginBottom: 4 }}>
            {label}
            {field.is_required && (
                <span aria-hidden="true" style={{ color: '#cf2e2e', marginLeft: 4 }}>*</span>
            )}
        </label>
    );

    const inputStyle: React.CSSProperties = {
        width: '100%',
        padding: '6px 8px',
        border: '1px solid rgba(0,0,0,0.15)',
        borderRadius: 4,
        background: 'rgba(0,0,0,0.02)',
        font: 'inherit',
        color: 'inherit',
    };

    let control: ReactElement;

    if (TEXTAREA_TYPES.has(field.type)) {
        control = (
            <textarea
                id={inputId}
                rows={3}
                placeholder={field.placeholder ?? ''}
                defaultValue={field.default_value ?? ''}
                style={inputStyle}
                disabled
            />
        );
    } else if (CHOICE_TYPES.has(field.type) && options.length > 0) {
        if ('radio' === field.type) {
            control = (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                    {options.map((opt) => (
                        <label key={opt.value} style={{ display: 'flex', gap: 6, fontSize: 13 }}>
                            <input type="radio" name={`${inputId}-${field.name}`} value={opt.value} disabled />
                            {opt.label}
                        </label>
                    ))}
                </div>
            );
        } else if ('checkbox_group' === field.type || 'checkbox' === field.type) {
            control = (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                    {options.map((opt) => (
                        <label key={opt.value} style={{ display: 'flex', gap: 6, fontSize: 13 }}>
                            <input type="checkbox" value={opt.value} disabled />
                            {opt.label}
                        </label>
                    ))}
                </div>
            );
        } else {
            control = (
                <select id={inputId} style={inputStyle} disabled defaultValue="">
                    <option value="">{field.placeholder ?? '—'}</option>
                    {options.map((opt) => (
                        <option key={opt.value} value={opt.value}>
                            {opt.label}
                        </option>
                    ))}
                </select>
            );
        }
    } else if ('file' === field.type) {
        control = <input id={inputId} type="file" disabled style={{ ...inputStyle, padding: 4 }} />;
    } else {
        const htmlType =
            'email' === field.type
                ? 'email'
                : 'number' === field.type
                ? 'number'
                : 'date' === field.type
                ? 'date'
                : 'time' === field.type
                ? 'time'
                : 'url' === field.type
                ? 'url'
                : 'phone' === field.type
                ? 'tel'
                : 'text';
        control = (
            <input
                id={inputId}
                type={htmlType}
                placeholder={field.placeholder ?? ''}
                defaultValue={field.default_value ?? ''}
                style={inputStyle}
                disabled
            />
        );
    }

    return (
        <div style={{ marginBottom: 12 }}>
            {labelEl}
            {control}
            {field.help_text && (
                <p style={{ margin: '4px 0 0', fontSize: 11, color: 'rgba(0,0,0,0.55)' }}>
                    {field.help_text}
                </p>
            )}
        </div>
    );
}

function FormPreview({ formId }: { readonly formId: number }): ReactElement {
    const [detail, setDetail] = useState<FormDetailResponse['data'] | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;
        setDetail(null);
        setError(null);

        async function load() {
            try {
                const response = await fetch(`/api/v1/forms/${formId}/render`, {
                    headers: buildHeaders(),
                    credentials: 'include',
                });
                if (404 === response.status) {
                    // The package's /render endpoint 404s for both deleted
                    // and inactive forms (see FormBlock::render, which
                    // distinguishes the two server-side). The editor only
                    // sees a status code here, so the message stays neutral
                    // — "unavailable" covers both states without misleading
                    // the author into hunting for an activate toggle on a
                    // form that no longer exists.
                    throw new Error(
                        __(
                            'This form is unavailable — it may have been deleted or deactivated. Pick a different form in the block sidebar.',
                            TEXT_DOMAIN,
                        ),
                    );
                }
                if (!response.ok) {
                    throw new Error(`Failed to load form preview (status ${response.status}).`);
                }
                const json = (await response.json()) as FormDetailResponse;
                if (!cancelled) {
                    setDetail(json.data);
                }
            } catch (err) {
                if (!cancelled) {
                    setError(err instanceof Error ? err.message : 'Failed to load form preview.');
                }
            }
        }

        void load();

        return () => {
            cancelled = true;
        };
    }, [formId]);

    if (error) {
        return (
            <Placeholder
                icon={<FormInserterIcon />}
                label={__('Form', TEXT_DOMAIN)}
                instructions={error}
            />
        );
    }

    if (null === detail) {
        return (
            <Placeholder
                icon={<FormInserterIcon />}
                label={__('Form', TEXT_DOMAIN)}
                instructions={__('Loading preview…', TEXT_DOMAIN)}
            >
                <Spinner />
            </Placeholder>
        );
    }

    const fields = detail.fields ?? [];

    return (
        <div
            style={{
                border: '1px dashed rgba(0,0,0,0.15)',
                borderRadius: 6,
                padding: 16,
                background: 'rgba(0,0,0,0.015)',
            }}
        >
            <p
                style={{
                    margin: '0 0 12px',
                    fontSize: 11,
                    textTransform: 'uppercase',
                    letterSpacing: 0.5,
                    color: 'rgba(0,0,0,0.55)',
                }}
            >
                {__('Form preview', TEXT_DOMAIN)} · {detail.name}
            </p>
            {0 === fields.length ? (
                <p style={{ margin: 0, fontSize: 13, color: 'rgba(0,0,0,0.55)' }}>
                    {__('This form has no fields yet. Add some from the Forms admin.', TEXT_DOMAIN)}
                </p>
            ) : (
                <>
                    {fields.map((field) => (
                        <FieldPreview key={field.id} field={field} />
                    ))}
                    <button
                        type="button"
                        disabled
                        style={{
                            marginTop: 4,
                            padding: '8px 16px',
                            background: '#2271b1',
                            color: 'white',
                            border: 'none',
                            borderRadius: 4,
                            opacity: 0.7,
                            cursor: 'not-allowed',
                        }}
                    >
                        {detail.submit_button_text || __('Submit', TEXT_DOMAIN)}
                    </button>
                </>
            )}
        </div>
    );
}

export default function FormEdit({
    attributes,
    setAttributes,
}: FormEditProps): ReactElement {
    const { formId } = attributes;
    const [forms, setForms] = useState<ApiForm[] | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            try {
                const response = await fetch('/api/v1/forms?per_page=100', {
                    headers: buildHeaders(),
                    credentials: 'include',
                });
                if (!response.ok) {
                    throw new Error(`Failed to load forms (status ${response.status}).`);
                }
                const json = (await response.json()) as PaginatedForms;
                if (!cancelled) {
                    setForms(json.data ?? []);
                }
            } catch (err) {
                if (!cancelled) {
                    setError(err instanceof Error ? err.message : 'Failed to load forms.');
                }
            }
        }

        void load();

        return () => {
            cancelled = true;
        };
    }, []);

    const options = [
        { label: __('— Select a form —', TEXT_DOMAIN), value: '0' },
        ...(forms ?? []).map((f) => ({
            label: f.is_active ? f.name : `${f.name} (inactive)`,
            value: String(f.id),
        })),
    ];

    const blockProps = useBlockProps({
        className: 'wp-block-artisanpack-form',
    });

    // Stale selection: a previous editor session picked a form that's
    // since become unavailable. We can't infer that purely from the
    // first page of `/api/v1/forms?per_page=100` — on sites with more
    // than 100 forms a perfectly valid selection can land on another
    // page. So when the selected id isn't in the loaded list, confirm
    // with a targeted `/render` HEAD: a 404 means the form is truly
    // gone (or inactive) and we show the reset path; anything else
    // means it just paginated off-screen and we let `<FormPreview>`
    // load it normally.
    const [isStaleSelection, setIsStaleSelection] = useState(false);

    useEffect(() => {
        if (formId <= 0 || null === forms) {
            setIsStaleSelection(false);
            return;
        }
        if (forms.some((form) => form.id === formId)) {
            setIsStaleSelection(false);
            return;
        }

        let cancelled = false;
        async function confirm() {
            try {
                const response = await fetch(`/api/v1/forms/${formId}/render`, {
                    method: 'HEAD',
                    headers: buildHeaders(),
                    credentials: 'include',
                });
                if (!cancelled) {
                    setIsStaleSelection(404 === response.status);
                }
            } catch {
                if (!cancelled) {
                    setIsStaleSelection(false);
                }
            }
        }
        void confirm();

        return () => {
            cancelled = true;
        };
    }, [formId, forms]);

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Form settings', TEXT_DOMAIN)} initialOpen>
                    {null === forms && !error ? (
                        <Spinner />
                    ) : (
                        <SelectControl
                            label={__('Form', TEXT_DOMAIN)}
                            value={String(formId)}
                            options={options}
                            onChange={(value) =>
                                setAttributes({ formId: Number.parseInt(value, 10) || 0 })
                            }
                            help={error ?? __('Pick a form from the artisanpack-ui/forms package.', TEXT_DOMAIN)}
                            __nextHasNoMarginBottom
                        />
                    )}
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                {isStaleSelection ? (
                    <Placeholder
                        icon={<FormInserterIcon />}
                        label={__('Form', TEXT_DOMAIN)}
                        instructions={__(
                            'The selected form is no longer available. It may have been deleted. Reset the selection to pick a different form.',
                            TEXT_DOMAIN,
                        )}
                    >
                        <Button
                            variant="secondary"
                            onClick={() => setAttributes({ formId: 0 })}
                        >
                            {__('Reset selection', TEXT_DOMAIN)}
                        </Button>
                    </Placeholder>
                ) : formId > 0 ? (
                    <FormPreview formId={formId} />
                ) : (
                    <Placeholder
                        icon={<FormInserterIcon />}
                        label={__('Form', TEXT_DOMAIN)}
                        instructions={
                            error ?? __('Choose a form in the block sidebar.', TEXT_DOMAIN)
                        }
                    />
                )}
            </div>
        </>
    );
}
