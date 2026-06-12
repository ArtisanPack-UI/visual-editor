/**
 * Single Content — editor-side component (#501).
 *
 * Container block that scopes its inner-block tree to one specific entry.
 * The author picks the entry via a searchable post dropdown backed by the
 * visual editor's `/posts` (or `/{postType}`) REST surface; the canvas
 * previews the inner blocks against whatever post the host has in scope.
 * Server-side `QueryInliner` resolves the entry through the visual
 * editor's `QueryResolverContract` and re-stamps the inner-block tree
 * against the resolved post before the renderers walk it.
 */

import type { ReactElement } from 'react';
import { useEffect, useState } from '@wordpress/element';
import {
    InspectorControls,
    useBlockProps,
    useInnerBlocksProps,
} from '@wordpress/block-editor';
import { ComboboxControl, PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { getContentTypes } from '../../editor/content-type-registry';

interface SingleContentAttributes {
    readonly postId: number;
    readonly postType: string;
}

interface SingleContentEditProps {
    readonly attributes: SingleContentAttributes;
    readonly setAttributes: (next: Partial<SingleContentAttributes>) => void;
}

interface PostSummary {
    readonly id: number;
    readonly label: string;
}

const TEMPLATE: [string, Record<string, unknown>][] = [
    ['artisanpack/post-title', {}],
    ['artisanpack/post-content', {}],
];

const API_BASE = '/visual-editor/api';

function clampPostId(value: number | string | undefined): number {
    const parsed = typeof value === 'number' ? value : Number(value);
    if (!Number.isFinite(parsed) || parsed < 0) {
        return 0;
    }
    return Math.trunc(parsed);
}

const DEFAULT_COLLECTION = 'posts';

// Match the host's registered content-type slugs verbatim — anything
// outside `[a-z0-9_-]` (slashes, dots, path traversal characters)
// would compose into the REST URL we fetch from, so we never let an
// unvetted attribute through. Unknown-but-syntactically-clean slugs
// still pluralize so a host that hasn't published its content-types
// list yet (fallback registry) gets a best-effort fetch instead of
// hard-failing.
const SAFE_SLUG_PATTERN = /^[a-z0-9_-]+$/;

function resolveCollection(rawType: string): string {
    const type = (rawType || 'post').trim().toLowerCase();
    const match = getContentTypes().find((entry) => entry.slug === type);
    if (match !== undefined) {
        return match.plural;
    }

    if (!SAFE_SLUG_PATTERN.test(type)) {
        return DEFAULT_COLLECTION;
    }

    if (type.endsWith('s')) {
        return type;
    }
    if (type.endsWith('y')) {
        return `${type.slice(0, -1)}ies`;
    }
    return `${type}s`;
}

function extractLabel(record: Record<string, unknown>, id: number): string {
    const title = record.title;
    if (typeof title === 'string' && title !== '') {
        return title;
    }
    if (title !== null && typeof title === 'object') {
        const envelope = title as { rendered?: unknown; raw?: unknown };
        if (typeof envelope.rendered === 'string' && envelope.rendered !== '') {
            return envelope.rendered;
        }
        if (typeof envelope.raw === 'string' && envelope.raw !== '') {
            return envelope.raw;
        }
    }
    if (typeof record.slug === 'string' && record.slug !== '') {
        return record.slug;
    }
    return `#${id}`;
}

export default function SingleContentEdit({
    attributes,
    setAttributes,
}: SingleContentEditProps): ReactElement {
    const { postId, postType } = attributes;
    const [search, setSearch] = useState<string>('');
    const [records, setRecords] = useState<ReadonlyArray<PostSummary>>([]);
    const [selectedLabel, setSelectedLabel] = useState<string | null>(null);

    useEffect(() => {
        const collection = resolveCollection(postType);
        const params = new URLSearchParams({ per_page: '50' });
        if (search.trim() !== '') {
            params.set('search', search.trim());
        }

        const controller = new AbortController();
        const url = `${API_BASE}/${collection}?${params.toString()}`;

        void fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((res) => (res.ok ? res.json() : null))
            .then((body) => {
                if (body === null) {
                    setRecords([]);
                    return;
                }
                const raw = Array.isArray(body)
                    ? body
                    : Array.isArray((body as { data?: unknown }).data)
                      ? ((body as { data: unknown[] }).data)
                      : [];
                const next: PostSummary[] = [];
                for (const entry of raw) {
                    if (entry === null || typeof entry !== 'object') {
                        continue;
                    }
                    const record = entry as Record<string, unknown>;
                    const id = clampPostId(record.id as number | string);
                    if (id === 0) {
                        continue;
                    }
                    next.push({ id, label: extractLabel(record, id) });
                }
                setRecords(next);
            })
            .catch((error) => {
                if ((error as { name?: string }).name !== 'AbortError') {
                    setRecords([]);
                }
            });

        return () => controller.abort();
    }, [postType, search]);

    useEffect(() => {
        if (!postId) {
            setSelectedLabel(null);
            return;
        }

        const match = records.find((record) => record.id === postId);
        if (match !== undefined) {
            setSelectedLabel(match.label);
            return;
        }

        const collection = resolveCollection(postType);
        const controller = new AbortController();
        void fetch(`${API_BASE}/${collection}/${postId}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((res) => (res.ok ? res.json() : null))
            .then((body) => {
                if (body === null || typeof body !== 'object') {
                    return;
                }
                const envelope = body as { data?: unknown };
                const record = (envelope.data ?? body) as Record<string, unknown>;
                setSelectedLabel(extractLabel(record, postId));
            })
            .catch(() => {
                /* swallowed — falls back to the id-only label below */
            });

        return () => controller.abort();
    }, [postId, postType, records]);

    const options = records.map((record) => ({
        label: record.label,
        value: String(record.id),
    }));

    if (
        postId &&
        !options.some((option) => option.value === String(postId))
    ) {
        options.unshift({
            label: selectedLabel ?? `#${postId}`,
            value: String(postId),
        });
    }

    const blockProps = useBlockProps({ className: 'ap-single-content' });
    const innerBlocksProps = useInnerBlocksProps(blockProps, {
        template: TEMPLATE,
    });

    return (
        <>
            <InspectorControls>
                <PanelBody
                    title={__('Single content settings', TEXT_DOMAIN)}
                    initialOpen
                >
                    <ComboboxControl
                        label={__('Post', TEXT_DOMAIN)}
                        help={__(
                            'Pick the entry to render. Leave empty to render the host post in scope.',
                            TEXT_DOMAIN
                        )}
                        value={postId ? String(postId) : null}
                        options={options}
                        onChange={(value) =>
                            setAttributes({ postId: clampPostId(value ?? 0) })
                        }
                        onFilterValueChange={(value) => setSearch(value)}
                        __nextHasNoMarginBottom
                    />
                    <TextControl
                        label={__('Post type', TEXT_DOMAIN)}
                        help={__(
                            'Resource slug to query. Defaults to "post".',
                            TEXT_DOMAIN
                        )}
                        value={postType}
                        onChange={(value) =>
                            setAttributes({
                                postType: value.trim() || 'post',
                                postId: 0,
                            })
                        }
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            <div {...innerBlocksProps} />
        </>
    );
}
