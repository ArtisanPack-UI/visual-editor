/**
 * Current-entry identity + title/excerpt sync (#992).
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, renderHook } from '@testing-library/react';
import { dispatch, select } from '@wordpress/data';

import {
    __resetCoreDataShimConfig,
    configureCoreDataShim,
} from '../../vendor/core-data-shim';
import { refreshContentTypes } from '../content-type-registry';
import {
    customEntryPostTypeForResource,
    seedCurrentEntryRecord,
    useEntryFieldMirror,
    type CurrentEntryIdentity,
} from '../current-entry';

type CoreDispatch = Record<string, (...args: unknown[]) => unknown>;
const coreDispatch = (): CoreDispatch => dispatch('core') as CoreDispatch;

async function resetStore(): Promise<void> {
    configureCoreDataShim({
        apiBase: '/_drain_',
        fetcher: async () => new Response(null, { status: 503 }),
    });

    await new Promise((resolve) => setTimeout(resolve, 50));

    coreDispatch().reset();
    coreDispatch().invalidateResolutionForStore();
}

beforeEach(async () => {
    await resetStore();
    __resetCoreDataShimConfig();
    configureCoreDataShim({
        apiBase: '/visual-editor/api',
        fetcher: async () => new Response(null, { status: 404 }),
    });
});

afterEach(async () => {
    document.body.innerHTML = '';
    refreshContentTypes();
    await resetStore();
    __resetCoreDataShimConfig();
});

describe('customEntryPostTypeForResource', () => {
    it('leaves posts/pages to the server-backed entity', () => {
        expect(customEntryPostTypeForResource('posts')).toBeNull();
        expect(customEntryPostTypeForResource('pages')).toBeNull();
    });

    it('prefers the host post-type override', () => {
        expect(customEntryPostTypeForResource('packages', ' Package ')).toBe('package');
    });

    it('uses the singular slug of the matching content type', () => {
        document.body.innerHTML = `<div data-ap-visual-editor data-content-types='${JSON.stringify([
            { slug: 'package', plural: 'packages', label: 'Package' },
        ])}'></div>`;
        refreshContentTypes();

        expect(customEntryPostTypeForResource('packages')).toBe('package');
    });

    it('falls back to the resource slug', () => {
        refreshContentTypes();

        expect(customEntryPostTypeForResource('recipes')).toBe('recipes');
    });

    it('rejects unsafe names and names reserved by built-in entities', () => {
        expect(customEntryPostTypeForResource('bad/slug')).toBeNull();
        expect(customEntryPostTypeForResource('things', 'attachment')).toBeNull();
        expect(customEntryPostTypeForResource('things', 'post')).toBeNull();
        expect(customEntryPostTypeForResource('')).toBeNull();
    });
});

describe('useEntryFieldMirror — two-way title sync', () => {
    const identity: CurrentEntryIdentity = { kind: 'postType', name: 'package', id: 5 };

    it('pushes a block edit to the document fields once', () => {
        seedCurrentEntryRecord(identity, { title: 'Old', excerpt: 'Blurb' });
        const onChange = vi.fn();

        const { rerender } = renderHook(
            ({ title }) => useEntryFieldMirror(identity, { title, excerpt: 'Blurb' }, onChange),
            { initialProps: { title: 'Old' } }
        );

        // The Post Title block stages its edit through `useEntityProp`.
        act(() => {
            coreDispatch().editEntityRecord('postType', 'package', 5, { title: 'New' });
        });

        expect(onChange).toHaveBeenCalledTimes(1);
        expect(onChange).toHaveBeenCalledWith({ title: 'New' });

        // Once the field holds the mirrored value nothing re-fires.
        rerender({ title: 'New' });
        expect(onChange).toHaveBeenCalledTimes(1);
    });

    it('does not echo a document-field edit staged on the record', () => {
        seedCurrentEntryRecord(identity, { title: 'Old', excerpt: '' });
        const onChange = vi.fn();

        const { result } = renderHook(() =>
            useEntryFieldMirror(identity, { title: 'Old', excerpt: '' }, onChange)
        );

        // `handleTitleChange` notes the value and stages it on the
        // record; the store update can commit before the field's own
        // state update does, so the local value here is still stale.
        act(() => {
            result.current('title', 'Typed in the field');
            coreDispatch().editEntityRecord('postType', 'package', 5, { title: 'Typed in the field' });
        });

        expect(onChange).not.toHaveBeenCalled();

        const core = select('core') as unknown as {
            getEditedEntityRecord: (k: string, n: string, id: number) => { title?: string } | null;
        };
        // …and the block reads the field's value off the edited record.
        expect(core.getEditedEntityRecord('postType', 'package', 5)?.title).toBe('Typed in the field');

        // A later block edit still mirrors.
        act(() => {
            coreDispatch().editEntityRecord('postType', 'package', 5, { title: 'From the block' });
        });

        expect(onChange).toHaveBeenCalledWith({ title: 'From the block' });
    });

    it('batches title and excerpt edits staged together', () => {
        seedCurrentEntryRecord(identity, { title: 'T', excerpt: 'E' });
        const onChange = vi.fn();

        renderHook(() => useEntryFieldMirror(identity, { title: 'T', excerpt: 'E' }, onChange));

        act(() => {
            coreDispatch().editEntityRecord('postType', 'package', 5, { title: 'T2', excerpt: 'E2' });
        });

        expect(onChange).toHaveBeenCalledWith({ title: 'T2', excerpt: 'E2' });
    });

    it('is inert without an entry identity', () => {
        const onChange = vi.fn();

        renderHook(() => useEntryFieldMirror(null, { title: '', excerpt: '' }, onChange));

        expect(onChange).not.toHaveBeenCalled();
    });
});
