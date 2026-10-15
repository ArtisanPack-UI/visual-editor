/**
 * R-17 / F-11 — every mount path applies the Custom CSS flags and the
 * theme block styles: the `data-*` bootstrap (via `readMountConfig`),
 * `mountEditor()` with an explicit config, and the Vue wrapper.
 */

import { flushPromises, mount as vueMount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';

const { setThemeBlockStyles } = vi.hoisted(() => ({ setThemeBlockStyles: vi.fn() }));

vi.mock('../editor-app', () => ({ EditorApp: (): null => null }));
vi.mock('../../block-style-registry', () => ({ setThemeBlockStyles }));
vi.mock('../editor-libs', () => ({ editorLibs: {} }));
vi.mock('../external-block-registration', () => ({
    registerExternalBlockType: () => undefined,
    registerExternalBlocks: () => undefined,
}));

import {
    canEditBlockCss,
    customCssAllowsUrls,
    setBlockCssAccess,
    setCustomCssUrlAccess,
} from '../../effects/access';
import { mountEditor, readMountConfig } from '../main';
import { VisualEditor } from '../vue';

const BASE = { apiBase: '/visual-editor/api', resource: 'posts', id: '1' };

const STYLES = [{ name: 'core/button', slug: 'outline', label: 'Outline', isDefault: false }];

function mountPoint(attributes: Record<string, string> = {}): HTMLElement {
    const element = document.createElement('div');

    element.dataset.apiBase = BASE.apiBase;
    element.dataset.resource = BASE.resource;
    element.dataset.id = BASE.id;

    for (const [key, value] of Object.entries(attributes)) {
        element.setAttribute(key, value);
    }

    document.body.appendChild(element);

    return element;
}

afterEach(() => {
    setBlockCssAccess(false);
    setCustomCssUrlAccess(true);
    setThemeBlockStyles.mockClear();
    document.body.innerHTML = '';
});

describe('readMountConfig', () => {
    it('reads the Custom CSS flags and block styles', () => {
        const config = readMountConfig(
            mountPoint({
                'data-can-edit-block-css': 'true',
                'data-custom-css-allow-urls': 'false',
                'data-block-styles': JSON.stringify(STYLES),
            })
        );

        expect(config?.canEditBlockCss).toBe(true);
        expect(config?.customCssAllowUrls).toBe(false);
        expect(config?.blockStyles).toEqual(STYLES);
    });

    it('fails closed and keeps URLs on when the attributes are missing', () => {
        const config = readMountConfig(mountPoint());

        expect(config?.canEditBlockCss).toBe(false);
        expect(config?.customCssAllowUrls).toBe(true);
        expect(config).not.toHaveProperty('blockStyles');
        expect(readMountConfig(mountPoint({ 'data-block-styles': '{"x":1}' }))).not.toHaveProperty('blockStyles');
    });
});

describe('mountEditor', () => {
    it('applies an explicit config', async () => {
        const mounted = mountEditor(mountPoint(), {
            ...BASE,
            canEditBlockCss: true,
            customCssAllowUrls: false,
            blockStyles: STYLES,
        });

        expect(canEditBlockCss()).toBe(true);
        expect(customCssAllowsUrls()).toBe(false);

        await mounted.ready;

        expect(setThemeBlockStyles).toHaveBeenCalledWith(STYLES);
        mounted.unmount();
    });

    it('fails closed without the flag and leaves the block styles alone', async () => {
        setBlockCssAccess(true);
        setCustomCssUrlAccess(false);

        const mounted = mountEditor(mountPoint(), { ...BASE });

        expect(canEditBlockCss()).toBe(false);
        expect(customCssAllowsUrls()).toBe(true);

        await mounted.ready;

        expect(setThemeBlockStyles).not.toHaveBeenCalled();
        mounted.unmount();
    });
});

describe('<VisualEditor /> Vue wrapper', () => {
    it('passes the Custom CSS flags and block styles through', async () => {
        const wrapper = vueMount(VisualEditor, {
            props: {
                model: { id: 3 },
                apiBase: BASE.apiBase,
                resource: BASE.resource,
                canEditBlockCss: true,
                customCssAllowUrls: false,
                blockStyles: STYLES,
            },
        });

        await flushPromises();

        expect(canEditBlockCss()).toBe(true);
        expect(customCssAllowsUrls()).toBe(false);
        expect(setThemeBlockStyles).toHaveBeenCalledWith(STYLES);

        wrapper.unmount();
    });

    it('hides Custom CSS unless the host grants it', async () => {
        setBlockCssAccess(true);

        const wrapper = vueMount(VisualEditor, {
            props: { model: { id: 4 }, apiBase: BASE.apiBase, resource: BASE.resource },
        });

        await flushPromises();

        expect(canEditBlockCss()).toBe(false);
        expect(customCssAllowsUrls()).toBe(true);

        wrapper.unmount();
    });
});
