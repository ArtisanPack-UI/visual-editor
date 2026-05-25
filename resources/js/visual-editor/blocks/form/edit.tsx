/**
 * Form — editor-side render.
 *
 * Shows a Form picker in the InspectorControls sidebar populated from
 * `/api/v1/forms`, plus an in-canvas placeholder so the author can see
 * which form is selected (or that no form is selected yet). The actual
 * public-side rendering is server-driven by `FormBlock::render()`.
 */

import { useEffect, useState, type ReactElement } from 'react';
import {
    InspectorControls,
    useBlockProps,
} from '@wordpress/block-editor';
import { Placeholder, PanelBody, SelectControl, Spinner } from '@wordpress/components';
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
}

interface PaginatedForms {
    readonly data: ApiForm[];
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
                const csrf = readMetaCsrfToken();
                const xsrf = readXsrfCookie();
                const headers: Record<string, string> = {
                    Accept: 'application/json',
                };
                if (csrf) headers['X-CSRF-TOKEN'] = csrf;
                if (xsrf) headers['X-XSRF-TOKEN'] = xsrf;

                const response = await fetch('/api/v1/forms?per_page=100', {
                    headers,
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

    const selected = forms?.find((f) => f.id === formId) ?? null;

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
                {selected ? (
                    <Placeholder
                        icon={<FormInserterIcon />}
                        label={selected.name}
                        instructions={
                            selected.is_active
                                ? __('This form will render on the public site at publish time.', TEXT_DOMAIN)
                                : __('This form is inactive and will not render on the public site.', TEXT_DOMAIN)
                        }
                    />
                ) : (
                    <Placeholder
                        icon={<FormInserterIcon />}
                        label={__('Form', TEXT_DOMAIN)}
                        instructions={
                            error
                                ? error
                                : __('Choose a form in the block sidebar.', TEXT_DOMAIN)
                        }
                    />
                )}
            </div>
        </>
    );
}
