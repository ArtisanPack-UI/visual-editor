import { describe, expect, it, vi } from 'vitest';

// Stub the dynamic shell import so importing `../main` doesn't try to
// resolve `@wordpress/block-editor` under jsdom.
vi.mock('../site-editor-app', () => ({
    SiteEditorApp: (): JSX.Element => (
        <div data-testid="ap-site-editor-stub" />
    ),
}));

// `main.tsx` statically wires the runtime block-registration seam (#766),
// which imports the real `@wordpress/*` editor libraries (`@wordpress/blocks`
// trips Node's strict JSON import). Stub the two seam modules so importing
// `../main` stays off that graph.
vi.mock('../../editor/editor-libs', () => ({ editorLibs: {} }));
vi.mock('../../editor/external-block-registration', () => ({
    registerExternalBlockType: () => undefined,
    registerExternalBlocks: () => undefined,
}));

import {
    registerArtisanpackMediaBridge,
    registerMediaBridge,
} from '../main';

describe('site-editor media-bridge surface (#677)', () => {
    it('re-exports registerMediaBridge and registerArtisanpackMediaBridge', () => {
        expect(typeof registerMediaBridge).toBe('function');
        expect(typeof registerArtisanpackMediaBridge).toBe('function');
    });

    it('installs the bridge registration API on window.ApSiteEditor', () => {
        expect(window.ApSiteEditor).toBeDefined();
        expect(typeof window.ApSiteEditor?.boot).toBe('function');
        expect(typeof window.ApSiteEditor?.registerMediaBridge).toBe(
            'function'
        );
        expect(
            typeof window.ApSiteEditor?.registerArtisanpackMediaBridge
        ).toBe('function');
    });

    it('exposes the boot function on window.ApSiteEditorBoot', () => {
        expect(typeof window.ApSiteEditorBoot).toBe('function');
    });
});
