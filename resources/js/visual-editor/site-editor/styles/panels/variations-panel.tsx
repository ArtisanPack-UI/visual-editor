/**
 * Variations panel.
 *
 * Themes can ship named style variations (theme.json
 * `styles.variations`) — bundled presets the site owner swaps in via a
 * picker. Per plan §8 and the design brief, V1 ships the picker UI;
 * full authoring (create a new variation from scratch) is 1.1. Users
 * can, however, "duplicate + tweak" an applied variation because each
 * picker entry just writes its `settings` / `styles` into the user
 * record — subsequent panel edits layer on top as normal.
 */

import { Button, PanelRow } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useMemo } from 'react';

import { TEXT_DOMAIN } from '../../../vendor/i18n';
import type { UseGlobalStylesEditorResult } from '../use-global-styles-editor';
import type { StyleVariation } from '../style-book-canvas';
import { StylePanelSection } from './panel-controls';

export interface VariationsPanelProps {
    editor: UseGlobalStylesEditorResult;
    variations: readonly StyleVariation[];
    activeVariationSlug: string | null;
    onApplyVariation: (slug: string) => void;
}

export function VariationsPanel(
    props: VariationsPanelProps
): JSX.Element {
    const { editor, variations, activeVariationSlug, onApplyVariation } =
        props;

    const hasCustomizations = useMemo(() => editor.isDirty, [editor.isDirty]);

    return (
        <StylePanelSection
            testId="ap-site-editor-style-panel-variations"
            title={__('Style variations', TEXT_DOMAIN)}
            customizedCount={hasCustomizations ? 1 : 0}
            onResetSection={() => editor.resetAll()}
            description={__(
                'Switch between theme-provided style presets. Applying a variation overwrites the current user record with the preset\'s settings and styles — your in-flight edits are discarded.',
                TEXT_DOMAIN
            )}
        >
            <PanelRow>
                {variations.length === 0 ? (
                    <p
                        className="ap-site-editor__style-panel-description"
                        data-testid="ap-site-editor-style-panel-variations-empty"
                    >
                        {__(
                            'The active theme does not provide style variations.',
                            TEXT_DOMAIN
                        )}
                    </p>
                ) : (
                    <ul
                        className="ap-site-editor__style-listing-list"
                        data-testid="ap-site-editor-style-panel-variations-list"
                    >
                        {variations.map((variation) => {
                            const isActive =
                                activeVariationSlug === variation.slug;

                            return (
                                <li
                                    key={variation.slug}
                                    className="ap-site-editor__style-listing-item"
                                >
                                    <Button
                                        variant={
                                            isActive ? 'primary' : 'tertiary'
                                        }
                                        className="ap-site-editor__style-listing-link"
                                        data-active={isActive}
                                        data-testid={`ap-site-editor-style-panel-variation-${variation.slug}`}
                                        onClick={() =>
                                            onApplyVariation(variation.slug)
                                        }
                                    >
                                        <span className="ap-site-editor__style-listing-label">
                                            {variation.title}
                                        </span>
                                        {isActive ? (
                                            <span
                                                className="ap-site-editor__style-panel-customized"
                                                data-testid={`ap-site-editor-style-panel-variation-active-${variation.slug}`}
                                            >
                                                {__('Applied', TEXT_DOMAIN)}
                                            </span>
                                        ) : null}
                                    </Button>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </PanelRow>
        </StylePanelSection>
    );
}
