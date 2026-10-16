/**
 * #896 — the post editor applies `data-locale-data` / `localeData`
 * translations before it imports the editor chunk, so `__()` calls that
 * run while that chunk's modules evaluate (block variations, presets)
 * come out translated.
 */

import { __, resetLocaleData } from '@wordpress/i18n';
import { afterEach, describe, expect, it, vi } from 'vitest';

const importTime = vi.hoisted(() => ({ title: '' }));

vi.mock('../editor-app', async () => {
    const i18n = await import('@wordpress/i18n');

    // Evaluated when `mountEditor()` imports the chunk.
    const fixtureDomain = 'artisanpack-visual-editor';
    importTime.title = i18n.__('Group', fixtureDomain);

    return { EditorApp: (): null => null };
});
vi.mock('../editor-libs', () => ({ editorLibs: {} }));
vi.mock('../external-block-registration', () => ({
    registerExternalBlockType: () => undefined,
    registerExternalBlocks: () => undefined,
}));

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { mountEditor, readMountConfig } from '../main';

const LOCALE_DATA = { '': { lang: 'de' }, Group: ['Gruppe'] };

function mountPoint(attributes: Record<string, string> = {}): HTMLElement {
    const element = document.createElement('div');

    element.dataset.apiBase = '/visual-editor/api';
    element.dataset.resource = 'posts';
    element.dataset.id = '1';

    for (const [key, value] of Object.entries(attributes)) {
        element.setAttribute(key, value);
    }

    document.body.appendChild(element);

    return element;
}

afterEach(() => {
    resetLocaleData(undefined, TEXT_DOMAIN);
    document.body.innerHTML = '';
});

describe('editor locale data (#896)', () => {
    it('reads data-locale-data into the mount config', () => {
        expect(readMountConfig(mountPoint({ 'data-locale-data': JSON.stringify(LOCALE_DATA) }))?.localeData).toEqual(LOCALE_DATA);
        expect(readMountConfig(mountPoint())).not.toHaveProperty('localeData');
    });

    it('installs the translations before the editor chunk evaluates', async () => {
        const element = mountPoint({ 'data-locale-data': JSON.stringify(LOCALE_DATA) });
        const config = readMountConfig(element);

        expect(config).not.toBeNull();

        const editor = mountEditor(element, config!);
        await editor.ready;

        expect(importTime.title).toBe('Gruppe');
        const fixtureDomain = TEXT_DOMAIN;
        expect(__('Group', fixtureDomain)).toBe('Gruppe');

        editor.unmount();
    });
});
