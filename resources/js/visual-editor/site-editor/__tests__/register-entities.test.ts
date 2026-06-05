import { beforeEach, describe, expect, it, vi } from 'vitest';

// `vi.mock` is hoisted, so the mock factory runs before any
// file-level `const` declarations. `vi.hoisted` lets us share
// references between the factory and the test body.
const { addEntities, dispatchSpy } = vi.hoisted(() => ({
    addEntities: vi.fn(),
    dispatchSpy: vi.fn(),
}));

// Partial mock — the shim imports `createReduxStore` from this module
// at load time, so we have to forward the rest of the surface.
vi.mock('@wordpress/data', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@wordpress/data')>();
    return {
        ...actual,
        dispatch: dispatchSpy,
    };
});

dispatchSpy.mockImplementation(() => ({ addEntities }));

import { DEFAULT_ENTITIES } from '../../vendor/core-data-shim';
import {
    SITE_EDITOR_ENTITIES,
    registerSiteEditorEntities,
} from '../register-entities';

beforeEach(() => {
    addEntities.mockClear();
    dispatchSpy.mockClear();
});

describe('SITE_EDITOR_ENTITIES', () => {
    it('exposes exactly the H6 entity descriptor set', () => {
        const names = SITE_EDITOR_ENTITIES.map((entity) => entity.name);

        expect(names).toEqual([
            'wp_template',
            'wp_template_part',
            'wp_navigation',
            'wp_navigation_link',
            'wp_block',
            'globalStyles',
        ]);
    });

    it('points wp_navigation at the H6 /menus surface (not the legacy /navigation)', () => {
        const navigation = SITE_EDITOR_ENTITIES.find(
            (entity) => entity.name === 'wp_navigation'
        );

        expect(navigation?.baseURL).toBe('/menus');
    });

    it('registers wp_navigation_link at the H6 /menu-items surface', () => {
        const navigationLink = SITE_EDITOR_ENTITIES.find(
            (entity) => entity.name === 'wp_navigation_link'
        );

        expect(navigationLink).toBeDefined();
        expect(navigationLink?.baseURL).toBe('/menu-items');
        expect(navigationLink?.kind).toBe('postType');
    });

    it('registers globalStyles under the root kind so the singleton entity wires correctly', () => {
        const globalStyles = SITE_EDITOR_ENTITIES.find(
            (entity) => entity.name === 'globalStyles'
        );

        expect(globalStyles?.kind).toBe('root');
        expect(globalStyles?.baseURL).toBe('/global-styles');
    });

    it('uses `id` as the primary key on every entity', () => {
        for (const entity of SITE_EDITOR_ENTITIES) {
            expect(entity.key).toBe('id');
        }
    });

    it('mirrors the descriptors that ship in DEFAULT_ENTITIES so the two cannot drift', () => {
        for (const entity of SITE_EDITOR_ENTITIES) {
            const def = DEFAULT_ENTITIES.find(
                (candidate) =>
                    candidate.kind === entity.kind &&
                    candidate.name === entity.name
            );

            expect(def).toBeDefined();
            expect(def?.baseURL).toBe(entity.baseURL);
            expect(def?.key).toBe(entity.key);
        }
    });

    it('freezes the descriptor list and each entry against accidental mutation', () => {
        expect(Object.isFrozen(SITE_EDITOR_ENTITIES)).toBe(true);

        for (const entity of SITE_EDITOR_ENTITIES) {
            expect(Object.isFrozen(entity)).toBe(true);
        }
    });
});

describe('registerSiteEditorEntities', () => {
    it('dispatches addEntities with the H6 descriptor list against the core data store', () => {
        registerSiteEditorEntities();

        expect(dispatchSpy).toHaveBeenCalledWith('core');
        expect(addEntities).toHaveBeenCalledTimes(1);
        expect(addEntities).toHaveBeenCalledWith(SITE_EDITOR_ENTITIES);
    });

    it('is idempotent — calling again re-issues the same dispatch without throwing', () => {
        registerSiteEditorEntities();
        registerSiteEditorEntities();

        expect(addEntities).toHaveBeenCalledTimes(2);
        expect(addEntities.mock.calls[0][0]).toBe(SITE_EDITOR_ENTITIES);
        expect(addEntities.mock.calls[1][0]).toBe(SITE_EDITOR_ENTITIES);
    });
});
