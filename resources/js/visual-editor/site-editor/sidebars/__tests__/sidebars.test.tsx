/**
 * H6 minimal inspector sidebars — render tests (#431).
 *
 * Each sidebar uses `useEntityRecord` from the core-data shim. The
 * shim itself is heavy to boot (Redux store + selectors), so these
 * tests mock the hook directly and assert the rendered output for
 * the three states each sidebar handles: loading, empty, and loaded.
 */

import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

import type {
    GlobalStylesSidebarRecord,
    MenuSidebarRecord,
    PatternSidebarRecord,
    TemplatePartSidebarRecord,
    TemplateSidebarRecord,
} from '../types';

const { useEntityRecord } = vi.hoisted(() => ({
    useEntityRecord: vi.fn(),
}));

vi.mock('../../../vendor/core-data-shim', () => ({
    useEntityRecord,
}));

import {
    GlobalStylesSidebar,
    MenuSidebar,
    PatternSidebar,
    TemplatePartSidebar,
    TemplateSidebar,
} from '..';

function mockRecord<T>(record: T | null, overrides: Partial<{ hasResolved: boolean; isResolving: boolean }> = {}): void {
    useEntityRecord.mockReturnValue({
        record,
        editedRecord: record,
        hasEdits: false,
        hasResolved: overrides.hasResolved ?? record !== null,
        isResolving: overrides.isResolving ?? false,
        edit: vi.fn(),
        save: vi.fn(),
    });
}

describe('TemplateSidebar', () => {
    it('renders the loading frame while the entity is resolving', () => {
        mockRecord(null, { hasResolved: false, isResolving: true });

        render(<TemplateSidebar id={1} />);

        expect(screen.getByTestId('ap-template-sidebar-loading')).toBeInTheDocument();
    });

    it('renders the empty frame when no record matches', () => {
        mockRecord(null, { hasResolved: true });

        render(<TemplateSidebar id={1} />);

        expect(screen.getByTestId('ap-template-sidebar-empty')).toBeInTheDocument();
    });

    it('renders the document fields when the template resolves', () => {
        const template: TemplateSidebarRecord = {
            id: 1,
            slug: 'single',
            type: 'wp_template',
            source: 'db',
            origin: 'theme',
            title: { rendered: 'Single Post', raw: 'Single Post' },
            description: '',
            content: { raw: '', blocks: [] },
            status: 'publish',
            theme: 'digital-shopfront',
            has_theme_file: true,
            is_custom: false,
        };
        mockRecord(template);

        render(<TemplateSidebar id={1} />);

        expect(screen.getByTestId('ap-template-sidebar')).toBeInTheDocument();
        expect(screen.getByText('Single Post')).toBeInTheDocument();
        expect(screen.getByText('single')).toBeInTheDocument();
        expect(screen.getByText('digital-shopfront')).toBeInTheDocument();
        expect(screen.getByText('db')).toBeInTheDocument();
        expect(
            screen.getByText(/overrides a theme file/i)
        ).toBeInTheDocument();
    });

    it('hides the override note when the template has no theme file backing', () => {
        const template: TemplateSidebarRecord = {
            id: 2,
            slug: 'custom',
            type: 'wp_template',
            source: 'db',
            origin: null,
            title: { rendered: 'Custom', raw: 'Custom' },
            description: '',
            content: { raw: '', blocks: [] },
            status: 'publish',
            theme: 'digital-shopfront',
            has_theme_file: false,
            is_custom: true,
        };
        mockRecord(template);

        render(<TemplateSidebar id={2} />);

        expect(
            screen.queryByText(/overrides a theme file/i)
        ).not.toBeInTheDocument();
    });
});

describe('TemplatePartSidebar', () => {
    it('surfaces the area field alongside the template fields', () => {
        // Pick a slug + area that don't share text so the assertions
        // for each field can target them individually.
        const part: TemplatePartSidebarRecord = {
            id: 5,
            slug: 'site-header',
            type: 'wp_template_part',
            area: 'header',
            source: 'theme',
            origin: 'theme',
            title: { rendered: 'Site Header', raw: 'Site Header' },
            description: '',
            content: { raw: '', blocks: [] },
            status: 'publish',
            theme: 'digital-shopfront',
            has_theme_file: true,
            is_custom: false,
        };
        mockRecord(part);

        render(<TemplatePartSidebar id={5} />);

        expect(screen.getByTestId('ap-template-part-sidebar')).toBeInTheDocument();
        expect(screen.getByText('site-header')).toBeInTheDocument();
        expect(screen.getByText('header')).toBeInTheDocument();
    });
});

describe('PatternSidebar', () => {
    it('renders title, source, sync status, and category chips', () => {
        const pattern: PatternSidebarRecord = {
            id: 'user/cta',
            slug: 'user/cta',
            type: 'wp_block',
            status: 'publish',
            title: { rendered: 'CTA', raw: 'CTA' },
            content: { raw: '', blocks: [] },
            source: 'user',
            synced: true,
            categories: ['hero', 'pricing'],
            block_types: [],
        };
        mockRecord(pattern);

        render(<PatternSidebar id="user/cta" />);

        expect(screen.getByTestId('ap-pattern-sidebar')).toBeInTheDocument();
        expect(screen.getByText('CTA')).toBeInTheDocument();
        expect(screen.getByText('user')).toBeInTheDocument();
        expect(screen.getByText('Synced')).toBeInTheDocument();
        expect(screen.getByText('hero')).toBeInTheDocument();
        expect(screen.getByText('pricing')).toBeInTheDocument();
    });

    it('shows the empty-categories hint when none are assigned', () => {
        const pattern: PatternSidebarRecord = {
            id: 'theme/hero',
            slug: 'hero',
            type: 'wp_block',
            status: 'publish',
            title: { rendered: 'Hero', raw: 'Hero' },
            content: { raw: '', blocks: [] },
            source: 'theme',
            synced: false,
            categories: [],
            block_types: [],
        };
        mockRecord(pattern);

        render(<PatternSidebar id="hero" />);

        expect(screen.getByText('No categories assigned.')).toBeInTheDocument();
        expect(screen.getByText('Unsynced')).toBeInTheDocument();
    });
});

describe('GlobalStylesSidebar', () => {
    it('renders theme + variation count + sentinel-id status note', () => {
        const styles: GlobalStylesSidebarRecord = {
            id: '__base__',
            theme: 'digital-shopfront',
            settings: {},
            styles: {},
            variations: [{ title: 'Dark' }, { title: 'High Contrast' }],
        };
        mockRecord(styles);

        render(<GlobalStylesSidebar id="__base__" />);

        expect(screen.getByTestId('ap-global-styles-sidebar')).toBeInTheDocument();
        expect(screen.getByText('digital-shopfront')).toBeInTheDocument();
        expect(screen.getByText('2 declared')).toBeInTheDocument();
        expect(
            screen.getByText(/Theme defaults are authoritative/i)
        ).toBeInTheDocument();
    });

    it('reports user-customized when the id is numeric', () => {
        const styles: GlobalStylesSidebarRecord = {
            id: 42,
            theme: 'digital-shopfront',
            settings: {},
            styles: {},
            variations: [],
        };
        mockRecord(styles);

        render(<GlobalStylesSidebar id={42} />);

        expect(screen.getByText('User-customized.')).toBeInTheDocument();
    });
});

describe('MenuSidebar', () => {
    it('renders name, slug, theme, and auto-add flag', () => {
        const menu: MenuSidebarRecord = {
            id: 7,
            slug: 'primary',
            theme: 'digital-shopfront',
            name: 'Primary Navigation',
            description: '',
            type: 'wp_navigation',
            status: 'publish',
            title: { rendered: 'Primary Navigation', raw: 'Primary Navigation' },
            auto_add_pages: true,
        };
        mockRecord(menu);

        render(<MenuSidebar id={7} />);

        expect(screen.getByTestId('ap-menu-sidebar')).toBeInTheDocument();
        expect(screen.getByText('Primary Navigation')).toBeInTheDocument();
        expect(screen.getByText('Yes')).toBeInTheDocument();
    });
});
