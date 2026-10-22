import { describe, expect, it } from 'vitest';

import { templateSlugToPostType } from '../template-post-type';

describe('templateSlugToPostType (#993)', () => {
    it.each(['single', 'singular', 'single-post'])('maps %s to the latest post', (slug) => {
        expect(templateSlugToPostType(slug)).toEqual({ postType: 'post', entrySlug: null });
    });

    it('maps single-post-{slug} to that post', () => {
        expect(templateSlugToPostType('single-post-hello-world')).toEqual({
            postType: 'post',
            entrySlug: 'hello-world',
        });
    });

    it('maps single-{type} to the custom content type', () => {
        expect(templateSlugToPostType('single-package')).toEqual({
            postType: 'package',
            entrySlug: null,
        });
        expect(templateSlugToPostType('single-case-study')).toEqual({
            postType: 'case-study',
            entrySlug: null,
        });
    });

    it('maps single-{type}-{slug} to a registered type and that entry', () => {
        const known = ['post', 'page', 'package', 'case', 'case-study'];

        expect(templateSlugToPostType('single-package-starter', known)).toEqual({
            postType: 'package',
            entrySlug: 'starter',
        });
        expect(templateSlugToPostType('single-case-study-acme', known)).toEqual({
            postType: 'case-study',
            entrySlug: 'acme',
        });
        expect(templateSlugToPostType('single-case-study', known)).toEqual({
            postType: 'case-study',
            entrySlug: null,
        });
    });

    it('reads an unregistered single-{rest} as the type itself', () => {
        expect(templateSlugToPostType('single-widget-thing', ['post', 'page'])).toEqual({
            postType: 'widget-thing',
            entrySlug: null,
        });
    });

    it('maps page to the latest page', () => {
        expect(templateSlugToPostType('page')).toEqual({ postType: 'page', entrySlug: null });
    });

    it('maps page-{slug} to that page', () => {
        expect(templateSlugToPostType('page-about')).toEqual({ postType: 'page', entrySlug: 'about' });
    });

    it('normalises whitespace and case', () => {
        expect(templateSlugToPostType('  Single  ')).toEqual({ postType: 'post', entrySlug: null });
    });

    it.each(['index', 'archive', 'archive-post', 'home', 'front-page', '404', 'search', 'custom-landing', ''])(
        'returns null for %j, which does not render a single entry',
        (slug) => {
            expect(templateSlugToPostType(slug)).toBeNull();
        }
    );

    it('rejects post types outside the safe slug set', () => {
        expect(templateSlugToPostType('single-bad/type')).toBeNull();
    });
});
