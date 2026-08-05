import { describe, expect, it } from 'vitest';

import { ApiError } from '../../api-client';
import { composedFallbackNotice, selectComposedTemplate } from '../fallback';
import { splitTemplateAroundContentSlot } from '../split';
import type { AppliedTemplate } from '../api';
import type { AppliedTemplateState } from '../use-applied-template';

const unknownSlug: AppliedTemplateState = {
    status: 'missing',
    missing: { status: 'missing', reason: 'unknown-slug', slug: 'landing-xl' },
};

const emptyTemplate: AppliedTemplateState = {
    status: 'missing',
    missing: { status: 'missing', reason: 'empty' },
};

const networkError: AppliedTemplateState = {
    status: 'error',
    error: new ApiError(
        'Applied-template request failed with status 500',
        500,
        null
    ),
};

const resolved: AppliedTemplateState = {
    status: 'ok',
    template: {
        status: 'ok',
        slug: 'single-post',
        name: 'Single Post',
        source: 'db',
        blocks: [],
        template_parts: {},
    } as AppliedTemplate,
};

describe('selectComposedTemplate', () => {
    it('passes a resolved template straight through', () => {
        const selection = selectComposedTemplate(resolved);

        expect(selection).not.toBeNull();
        expect(selection?.name).toBe('Single Post');
        expect(selection?.slug).toBe('single-post');
        expect(selection?.fallbackReason).toBeNull();
    });

    it.each([
        ['an unresolvable slug', unknownSlug, 'unknown-slug'],
        ['an empty template field', emptyTemplate, 'empty'],
        ['a network error', networkError, 'error'],
    ])('routes %s to the default template', (_label, state, reason) => {
        const selection = selectComposedTemplate(state);

        expect(selection).not.toBeNull();
        expect(selection?.fallbackReason).toBe(reason);
        expect(selection?.slug).toBeNull();
        expect(selection?.name).toBe('Default template');
        expect(selection?.blocks.map((block) => block.name)).toEqual([
            'artisanpack/post-title',
            'artisanpack/post-featured-image',
            'artisanpack/post-content',
        ]);
    });

    it('splits the default template around its content slot', () => {
        const selection = selectComposedTemplate(emptyTemplate);
        const split = splitTemplateAroundContentSlot(
            selection?.blocks ?? [],
            selection?.name ?? ''
        );

        expect(split.slotFound).toBe(true);
        expect(split.header.map((block) => block.name)).toEqual([
            'artisanpack/post-title',
            'artisanpack/post-featured-image',
        ]);
        expect(split.footer).toEqual([]);
    });

    it('hands out a fresh block tree per call', () => {
        const first = selectComposedTemplate(emptyTemplate);
        const second = selectComposedTemplate(emptyTemplate);

        expect(first?.blocks[0]).not.toBe(second?.blocks[0]);
    });

    it.each([
        ['idle', { status: 'idle' } as AppliedTemplateState],
        ['loading', { status: 'loading' } as AppliedTemplateState],
    ])('composes nothing while %s', (_label, state) => {
        expect(selectComposedTemplate(state)).toBeNull();
    });
});

describe('composedFallbackNotice', () => {
    it('names the unavailable template for an unresolvable slug', () => {
        expect(composedFallbackNotice(unknownSlug)).toEqual({
            tone: 'warning',
            message:
                'The template “landing-xl” is unavailable — previewing on the default template.',
        });
    });

    it('uses the no-template copy for an empty template field', () => {
        expect(composedFallbackNotice(emptyTemplate)).toEqual({
            tone: 'warning',
            message:
                'No template is set for this content — previewing on the default template.',
        });
    });

    it('falls back to the no-template copy when the miss carries no slug', () => {
        const notice = composedFallbackNotice({
            status: 'missing',
            missing: { status: 'missing', reason: 'unknown-slug' },
        });

        expect(notice?.message).toBe(
            'No template is set for this content — previewing on the default template.'
        );
    });

    it('reads a network error as an error, not a routine miss', () => {
        expect(composedFallbackNotice(networkError)).toEqual({
            tone: 'error',
            message:
                'The template could not be loaded — previewing on the default template.',
        });
    });

    it('says nothing when a real template resolved', () => {
        expect(composedFallbackNotice(resolved)).toBeNull();
        expect(composedFallbackNotice({ status: 'loading' })).toBeNull();
        expect(composedFallbackNotice({ status: 'idle' })).toBeNull();
    });
});
