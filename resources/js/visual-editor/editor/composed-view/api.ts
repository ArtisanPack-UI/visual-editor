/**
 * REST client for the composed-view's applied-template endpoint (#619).
 *
 * The endpoint returns a discriminated union: on hit, the resolved
 * template + its referenced template-parts; on miss, a 404 with a
 * `{ status: 'missing', reason }` body so the client can trigger the
 * fallback flow without a second round-trip.
 *
 * @since 1.1.0
 */

import type { BlockInstance } from '@wordpress/blocks';

import { ApiError } from '../api-client';

export interface AppliedTemplatePart {
    slug: string;
    area: string;
    title: string;
    source: 'db' | 'theme';
    blocks: readonly BlockInstance[];
}

export interface AppliedTemplate {
    status: 'ok';
    slug: string;
    name: string;
    source: 'db' | 'theme';
    blocks: readonly BlockInstance[];
    /** Referenced template-parts keyed by slug. */
    template_parts: Readonly<Record<string, AppliedTemplatePart>>;
}

export type AppliedTemplateMissingReason = 'empty' | 'unknown-slug';

export interface AppliedTemplateMissing {
    status: 'missing';
    reason: AppliedTemplateMissingReason;
    /** Present when reason === 'unknown-slug'. */
    slug?: string;
}

export type AppliedTemplateResult =
    | { status: 'ok'; template: AppliedTemplate }
    | AppliedTemplateMissing;

export interface AppliedTemplateConfig {
    apiBase: string;
    resource: string;
    id: string;
}

function appliedTemplateUrl(config: AppliedTemplateConfig): string {
    const base = config.apiBase.replace(/\/$/, '');

    return `${base}/${encodeURIComponent(config.resource)}/${encodeURIComponent(
        config.id
    )}/applied-template`;
}

/**
 * Fetch the applied template for a resource+id. The endpoint always
 * responds 200 with a discriminated `{status: 'ok' | 'missing', ...}`
 * body — a 200 (vs 404) keeps the browser devtools clean since the
 * missing state is a normal, routine response for any content that
 * hasn't chosen a template. Throws only on real network / auth /
 * unexpected errors.
 */
export async function fetchAppliedTemplate(
    config: AppliedTemplateConfig
): Promise<AppliedTemplateResult> {
    const response = await fetch(appliedTemplateUrl(config), {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    const text = await response.text();
    let body: unknown = null;

    if (text !== '') {
        try {
            body = JSON.parse(text);
        } catch {
            body = text;
        }
    }

    if (!response.ok) {
        throw new ApiError(
            `Applied-template request failed with status ${response.status}`,
            response.status,
            body
        );
    }

    if (isMissingPayload(body)) {
        return body;
    }

    return body as AppliedTemplate;
}

function isMissingPayload(body: unknown): body is AppliedTemplateMissing {
    if (body === null || typeof body !== 'object') {
        return false;
    }

    const record = body as Record<string, unknown>;

    return (
        record.status === 'missing' &&
        (record.reason === 'empty' || record.reason === 'unknown-slug')
    );
}
