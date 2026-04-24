/**
 * Styles section orchestrator.
 *
 * Parallel to D2's `useEntityEditorViews` but shaped around global
 * styles' singleton record. The site-editor shell plugs the navigator /
 * canvas / inspector outputs into its existing slots — the shell
 * doesn't need to know the Styles section is special; it gets the same
 * `{ canvas, inspector }` contract D2's editor hook returns plus the
 * navigator tree the left rail renders.
 *
 * The hook also pushes status into the shell's `entityState` slot so the
 * top-bar Save button wires to the global-styles save without any
 * special-casing — the shell already knows how to render "Save global
 * styles" for the Styles section (see `sections.tsx`).
 */

import { __ } from '@wordpress/i18n';
import {
    useCallback,
    useEffect,
    useMemo,
    useState,
    type ReactElement,
} from 'react';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import type { SiteEditorApiConfig } from '../api-client';
import type { EntityEditorState } from '../entity-editor';
import {
    StyleBookCanvas,
    type StyleVariation,
} from './style-book-canvas';
import { StylesInspector } from './styles-inspector';
import {
    StylesNavigator,
    type StyleBlock,
} from './styles-navigator';
import {
    DEFAULT_NAVIGATOR_STATE,
    type StylesNavigatorState,
} from './styles-navigator-tree';
import {
    useGlobalStylesEditor,
    type UseGlobalStylesEditorResult,
} from './use-global-styles-editor';
import { useRegisteredBlocks } from './use-registered-blocks';

export interface UseStylesSectionViewsOptions {
    apiConfig: SiteEditorApiConfig;
    enabled: boolean;
    onStateChange: (state: EntityEditorState) => void;
}

export interface StylesSectionViews {
    navigator: ReactElement;
    canvas: ReactElement;
    inspector: ReactElement;
}

function readVariations(
    base: Record<string, unknown> | null
): readonly StyleVariation[] {
    if (base === null) {
        return [];
    }

    const candidates: unknown[] = [];

    const direct = (base as Record<string, unknown>)['variations'];

    if (Array.isArray(direct)) {
        candidates.push(...direct);
    }

    const styles = (base as Record<string, unknown>)['styles'];

    if (styles !== null && typeof styles === 'object') {
        const nested = (styles as Record<string, unknown>)['variations'];

        if (Array.isArray(nested)) {
            candidates.push(...nested);
        }
    }

    const results: StyleVariation[] = [];

    for (const entry of candidates) {
        if (entry === null || typeof entry !== 'object') {
            continue;
        }

        const row = entry as Record<string, unknown>;
        const slug = typeof row.slug === 'string' ? row.slug : null;
        const title = typeof row.title === 'string' ? row.title : slug;

        if (slug === null || title === null) {
            continue;
        }

        const settings =
            row.settings !== null && typeof row.settings === 'object'
                ? (row.settings as Record<string, unknown>)
                : undefined;
        const styles =
            row.styles !== null && typeof row.styles === 'object'
                ? (row.styles as Record<string, unknown>)
                : undefined;

        results.push({ slug, title, settings, styles });
    }

    return results;
}

/**
 * Deterministic serializer — recursively sorts object keys before
 * stringifying so two semantically equal payloads compare equal
 * regardless of whichever order PHP's `json_encode` + theme-json
 * fixtures happened to emit their keys. Falls back to raw
 * `JSON.stringify` for non-objects (numbers / strings / null / arrays).
 */
function stableStringify(value: unknown): string {
    if (value === null || typeof value !== 'object') {
        return JSON.stringify(value);
    }

    if (Array.isArray(value)) {
        return `[${value.map((entry) => stableStringify(entry)).join(',')}]`;
    }

    const entries = Object.entries(value as Record<string, unknown>).sort(
        ([a], [b]) => a.localeCompare(b)
    );

    return `{${entries
        .map(([k, v]) => `${JSON.stringify(k)}:${stableStringify(v)}`)
        .join(',')}}`;
}

function selectActiveVariation(
    editor: UseGlobalStylesEditorResult,
    variations: readonly StyleVariation[],
    activeSlug: string | null
): string | null {
    // The user explicitly applied a variation in this session.
    if (activeSlug !== null) {
        return activeSlug;
    }

    // If the user record's settings/styles match a variation wholesale,
    // surface that variation as the "applied" one. For V1 we detect a
    // strict match by deterministic (key-sorted) stringify so picker
    // state is predictable regardless of the order PHP serialized the
    // payload — any manual edit (which won't exactly equal a preset)
    // flips back to "customized / no variation selected".
    if (editor.record === null || variations.length === 0) {
        return null;
    }

    const userSignature = stableStringify({
        settings: editor.record.settings ?? {},
        styles: editor.record.styles ?? {},
    });

    for (const variation of variations) {
        const signature = stableStringify({
            settings: variation.settings ?? {},
            styles: variation.styles ?? {},
        });

        if (signature === userSignature) {
            return variation.slug;
        }
    }

    return null;
}

export function useStylesSectionViews(
    options: UseStylesSectionViewsOptions
): StylesSectionViews {
    const { apiConfig, enabled, onStateChange } = options;

    const editor = useGlobalStylesEditor({ apiConfig, enabled });
    const blocksState = useRegisteredBlocks(apiConfig, enabled);

    const [navigatorState, setNavigatorState] = useState<StylesNavigatorState>(
        { ...DEFAULT_NAVIGATOR_STATE }
    );
    const [activeVariationSlug, setActiveVariationSlug] = useState<
        string | null
    >(null);

    const variations = useMemo(
        () =>
            readVariations(
                (editor.base as unknown as Record<string, unknown> | null)
            ),
        [editor.base]
    );

    const effectiveVariationSlug = useMemo(
        () =>
            selectActiveVariation(editor, variations, activeVariationSlug),
        [activeVariationSlug, editor, variations]
    );

    // Wrap `editor.save` so the `EntityEditorState` slot receives a
    // Promise<void>-returning callback (the shell doesn't care about
    // the saved record). Depend only on the `editor.save` primitive —
    // `editor` itself is a fresh object each render, so including it
    // as a dep would remake this callback every render and cascade
    // through the onStateChange useEffect as an update-every-tick loop.
    const save = useCallback(async (): Promise<void> => {
        await editor.save();
    }, [editor.save]);

    // Mirror the state contract D2's entity-editor hook uses so the shell
    // top bar can wire Save / dirty / error uniformly.
    useEffect(() => {
        if (!enabled) {
            onStateChange({
                entityId: null,
                entityTitle: '',
                isDirty: false,
                saveStatus: 'idle',
                saveErrorMessage: null,
                lastSavedAt: null,
                save: null,
            });
            return;
        }

        onStateChange({
            entityId: editor.id === null ? null : String(editor.id),
            entityTitle: __('Global styles', TEXT_DOMAIN),
            isDirty: editor.isDirty,
            saveStatus: editor.saveStatus,
            saveErrorMessage: editor.saveErrorMessage,
            lastSavedAt: editor.lastSavedAt,
            save: editor.id === null ? null : save,
        });
    }, [
        editor.id,
        editor.isDirty,
        editor.lastSavedAt,
        editor.saveErrorMessage,
        editor.saveStatus,
        enabled,
        onStateChange,
        save,
    ]);

    const handleApplyVariation = useCallback(
        (slug: string): void => {
            const variation = variations.find(
                (entry) => entry.slug === slug
            );

            if (variation === undefined) {
                return;
            }

            editor.replaceSubtree(
                'settings',
                [],
                variation.settings ?? {}
            );
            editor.replaceSubtree('styles', [], variation.styles ?? {});
            setActiveVariationSlug(slug);
        },
        [editor, variations]
    );

    const navigator = (
        <StylesNavigator
            state={navigatorState}
            onSelect={setNavigatorState}
            blocks={blocksState.blocks}
            blocksError={blocksState.error}
            isLoadingBlocks={blocksState.isLoading}
        />
    );

    const canvas = (
        <StyleBookCanvas
            draft={editor.draft}
            variations={variations}
            activeVariationSlug={effectiveVariationSlug}
            onSelectVariation={handleApplyVariation}
            loadError={
                editor.loadStatus === 'error'
                    ? editor.loadErrorMessage ??
                      __('Failed to load global styles.', TEXT_DOMAIN)
                    : null
            }
        />
    );

    const inspector = (
        <StylesInspector
            editor={editor}
            state={navigatorState}
            onNavigatorChange={setNavigatorState}
            validationErrors={editor.validationErrors}
            blocks={blocksState.blocks}
            variations={variations}
            activeVariationSlug={effectiveVariationSlug}
            onApplyVariation={handleApplyVariation}
            saveStatus={editor.saveStatus}
            saveErrorMessage={editor.saveErrorMessage}
        />
    );

    return { navigator, canvas, inspector };
}

export { DEFAULT_NAVIGATOR_STATE } from './styles-navigator-tree';
export type { StyleBlock };
