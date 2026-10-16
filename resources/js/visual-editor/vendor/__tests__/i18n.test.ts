import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { __, _n, getLocaleData, resetLocaleData } from '@wordpress/i18n';

import {
    bootI18n,
    installLocaleData,
    installLocaleDataFromDataset,
    normalizeLocaleData,
    TEXT_DOMAIN,
    __resetI18nForTests,
} from '../i18n';

describe('i18n bootstrap', () => {
    beforeEach(() => {
        __resetI18nForTests();
    });

    it('registers the artisanpack-visual-editor text domain', () => {
        bootI18n();

        const data = getLocaleData(TEXT_DOMAIN);
        expect(data).toBeDefined();
        const header = data?.[''] as
            | { domain?: string; lang?: string }
            | undefined;
        expect(header).toMatchObject({
            domain: TEXT_DOMAIN,
            lang: 'en',
        });
    });

    it('is idempotent across repeat calls', () => {
        bootI18n();
        bootI18n();

        const data = getLocaleData(TEXT_DOMAIN);
        const header = data?.[''] as { domain?: string } | undefined;
        expect(header?.domain).toBe(TEXT_DOMAIN);
    });
});

// Aliased so the `.pot` extractor, which keys on `TEXT_DOMAIN`, skips
// these fixtures.
const FIXTURE_DOMAIN = TEXT_DOMAIN;

describe('locale data (#896)', () => {
    const FRENCH = {
        'translation-revision-date': '2026-10-16 00:00+0000',
        generator: 'WP-CLI/2.11.0',
        domain: 'messages',
        locale_data: {
            messages: {
                '': { domain: 'messages', lang: 'fr', 'plural-forms': 'nplurals=2; plural=(n > 1);' },
                Group: ['Groupe'],
                'single horizontal line\u0004Row': ['Rangée'],
                Mobile: ['Mobile (fr)'],
                Primary: ['Primaire'],
                '%d block': ['%d bloc', '%d blocs'],
            },
        },
    };

    beforeEach(() => {
        __resetI18nForTests();
        resetLocaleData(undefined, TEXT_DOMAIN);
    });

    afterEach(() => {
        resetLocaleData(undefined, TEXT_DOMAIN);
    });

    it('reads a wp i18n make-json file and bare locale data alike', () => {
        const bare = normalizeLocaleData(FRENCH);

        expect(bare).toMatchObject({ Group: ['Groupe'], '': { lang: 'fr' } });
        expect(normalizeLocaleData(bare)).toEqual(bare);
    });

    it('rejects payloads with no translations', () => {
        expect(normalizeLocaleData(null)).toBeNull();
        expect(normalizeLocaleData('Groupe')).toBeNull();
        expect(normalizeLocaleData({ '': { lang: 'fr' } })).toBeNull();
        expect(normalizeLocaleData({ Group: 'Groupe', Stack: [42] })).toBeNull();
    });

    it('translates the text domain and keeps the plural forms', () => {
        expect(installLocaleData(FRENCH)).toBe(true);

        expect(__('Group', FIXTURE_DOMAIN)).toBe('Groupe');
        expect(_n('%d block', '%d blocks', 1, FIXTURE_DOMAIN)).toBe('%d bloc');
        expect(_n('%d block', '%d blocks', 3, FIXTURE_DOMAIN)).toBe('%d blocs');
        expect(getLocaleData(TEXT_DOMAIN)?.['']).toMatchObject({ domain: TEXT_DOMAIN, lang: 'fr' });
    });

    it('survives a later bootI18n call', () => {
        installLocaleData(FRENCH);
        bootI18n();

        expect(__('Group', FIXTURE_DOMAIN)).toBe('Groupe');
        expect(getLocaleData(TEXT_DOMAIN)?.['']).toMatchObject({ lang: 'fr' });
    });

    it('ignores a missing or malformed data-locale-data attribute', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);

        expect(installLocaleDataFromDataset(undefined)).toBe(false);
        expect(installLocaleDataFromDataset('  ')).toBe(false);
        expect(installLocaleDataFromDataset('{not json')).toBe(false);
        expect(installLocaleDataFromDataset(JSON.stringify(FRENCH))).toBe(true);
        expect(__('Group', FIXTURE_DOMAIN)).toBe('Groupe');

        warn.mockRestore();
    });

    it('translates a block variation title evaluated after the data loads', async () => {
        installLocaleData(FRENCH);
        vi.resetModules();

        const { default: variations } = await import('../../blocks/group/variations');

        expect(variations.find((variation) => variation.name === 'group')?.title).toBe('Groupe');
        expect(variations.find((variation) => variation.name === 'row')?.title).toBe('Rangée');
    });

    it('translates a panel label and an editor preset', async () => {
        installLocaleData(FRENCH);
        vi.resetModules();

        const { BreakpointRegistry } = await import('../../responsive/registry');
        const { DEFAULT_PALETTE } = await import('../../editor-settings');

        expect(new BreakpointRegistry().label('mobile')).toBe('Mobile (fr)');
        expect(DEFAULT_PALETTE.find((entry) => entry.slug === 'primary')?.name).toBe('Primaire');
    });
});
