/**
 * HowTo — editor-side component.
 *
 * Dedicated HowTo block (#759). Stores an ordered list of steps as a
 * `steps` attribute array (each step has a name, text and optional
 * image url + alt) plus block-level `name` and `description`. A lighter
 * shape than nesting inner blocks for the semantically fixed HowTo
 * structure. Users edit each field with `RichText`; the inspector
 * exposes the heading level used for the step names plus a toggle that
 * lets a page turn off JSON-LD emission when HowTo schema is already
 * provided elsewhere (avoids double-emission with a page-level schema
 * block or head manager).
 */

import type { ReactElement } from 'react';
import {
    InspectorControls,
    RichText,
    useBlockProps,
} from '@wordpress/block-editor';
import {
    Button,
    PanelBody,
    SelectControl,
    TextControl,
    ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

const HEADING_LEVELS: ReadonlyArray<2 | 3 | 4 | 5 | 6> = [2, 3, 4, 5, 6];

export interface HowtoStep {
    readonly name: string;
    readonly text: string;
    readonly imageUrl: string;
    readonly imageAlt: string;
}

interface HowtoAttributes {
    readonly name: string;
    readonly description: string;
    readonly steps: ReadonlyArray<HowtoStep>;
    readonly headingLevel: number;
    readonly emitSchema: boolean;
}

interface HowtoEditProps {
    readonly attributes: HowtoAttributes;
    readonly setAttributes: (next: Partial<HowtoAttributes>) => void;
}

function clampHeadingLevel(value: unknown): 2 | 3 | 4 | 5 | 6 {
    const numeric = typeof value === 'number' ? value : Number(value);

    if (!Number.isFinite(numeric)) {
        return 3;
    }

    const rounded = Math.round(numeric);

    if (rounded <= 2) {
        return 2;
    }

    if (rounded >= 6) {
        return 6;
    }

    return rounded as 2 | 3 | 4 | 5 | 6;
}

function emptyStep(): HowtoStep {
    return { name: '', text: '', imageUrl: '', imageAlt: '' };
}

export default function HowtoEdit({
    attributes,
    setAttributes,
}: HowtoEditProps): ReactElement {
    const { name, description, steps, headingLevel, emitSchema } = attributes;

    const blockProps = useBlockProps({ className: 'ap-howto' });

    const level = clampHeadingLevel(headingLevel);
    const StepNameTag = `h${level}` as 'h2' | 'h3' | 'h4' | 'h5' | 'h6';

    const list: ReadonlyArray<HowtoStep> = steps.length > 0 ? steps : [emptyStep()];

    function updateStep(index: number, patch: Partial<HowtoStep>): void {
        const next = list.map((step, i) => (i === index ? { ...step, ...patch } : step));
        setAttributes({ steps: next });
    }

    function addStep(): void {
        // Append to the persisted `steps`, not `list` — when the block
        // is fresh, `list` includes a synthetic starter that has not
        // been saved, so `[...list, blank]` would land two items after
        // the first click.
        setAttributes({ steps: [...steps, emptyStep()] });
    }

    function removeStep(index: number): void {
        // Keep at least one editable step so the block never renders
        // empty.
        if (list.length <= 1) {
            setAttributes({ steps: [emptyStep()] });
            return;
        }

        setAttributes({ steps: list.filter((_, i) => i !== index) });
    }

    function moveStep(index: number, delta: -1 | 1): void {
        const target = index + delta;

        if (target < 0 || target >= list.length) {
            return;
        }

        const next = list.slice();
        const [moved] = next.splice(index, 1);
        next.splice(target, 0, moved);
        setAttributes({ steps: next });
    }

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('HowTo settings', TEXT_DOMAIN)} initialOpen>
                    <SelectControl
                        label={__('Step heading level', TEXT_DOMAIN)}
                        value={String(level)}
                        options={HEADING_LEVELS.map((value) => ({
                            label: `H${value}`,
                            value: String(value),
                        }))}
                        onChange={(value) =>
                            setAttributes({ headingLevel: clampHeadingLevel(value) })
                        }
                        __nextHasNoMarginBottom
                    />
                    <ToggleControl
                        label={__('Emit HowTo schema', TEXT_DOMAIN)}
                        help={__(
                            'Adds HowTo JSON-LD built from the block name, description and ordered steps. Turn off if the page already emits HowTo schema elsewhere — avoid double-emission.',
                            TEXT_DOMAIN
                        )}
                        checked={emitSchema}
                        onChange={(next) => setAttributes({ emitSchema: next })}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <RichText
                    tagName="h2"
                    className="ap-howto__name"
                    value={name}
                    onChange={(value: string) => setAttributes({ name: value })}
                    placeholder={__('HowTo title…', TEXT_DOMAIN)}
                    allowedFormats={['core/bold', 'core/italic']}
                />
                <RichText
                    tagName="p"
                    className="ap-howto__description"
                    value={description}
                    onChange={(value: string) => setAttributes({ description: value })}
                    placeholder={__('Short description (optional)…', TEXT_DOMAIN)}
                />
                <ol className="ap-howto__steps">
                    {list.map((step, index) => (
                        <li key={index} className="ap-howto__step">
                            <RichText
                                tagName={StepNameTag}
                                className="ap-howto__step-name"
                                value={step.name}
                                onChange={(value: string) =>
                                    updateStep(index, { name: value })
                                }
                                placeholder={__('Step name…', TEXT_DOMAIN)}
                                allowedFormats={['core/bold', 'core/italic', 'core/link']}
                            />
                            <RichText
                                tagName="div"
                                className="ap-howto__step-text"
                                value={step.text}
                                onChange={(value: string) =>
                                    updateStep(index, { text: value })
                                }
                                placeholder={__('Step instructions…', TEXT_DOMAIN)}
                            />
                            <TextControl
                                label={__('Step image URL (optional)', TEXT_DOMAIN)}
                                value={step.imageUrl}
                                onChange={(value: string) =>
                                    updateStep(index, { imageUrl: value })
                                }
                                placeholder="https://…"
                                __nextHasNoMarginBottom
                                __next40pxDefaultSize
                            />
                            {step.imageUrl !== '' && (
                                <TextControl
                                    label={__('Step image alt text', TEXT_DOMAIN)}
                                    value={step.imageAlt}
                                    onChange={(value: string) =>
                                        updateStep(index, { imageAlt: value })
                                    }
                                    __nextHasNoMarginBottom
                                    __next40pxDefaultSize
                                />
                            )}
                            <div className="ap-howto__step-controls">
                                <Button
                                    size="small"
                                    variant="tertiary"
                                    onClick={() => moveStep(index, -1)}
                                    disabled={index === 0}
                                >
                                    {__('Move up', TEXT_DOMAIN)}
                                </Button>
                                <Button
                                    size="small"
                                    variant="tertiary"
                                    onClick={() => moveStep(index, 1)}
                                    disabled={index === list.length - 1}
                                >
                                    {__('Move down', TEXT_DOMAIN)}
                                </Button>
                                <Button
                                    size="small"
                                    variant="tertiary"
                                    isDestructive
                                    onClick={() => removeStep(index)}
                                >
                                    {__('Remove', TEXT_DOMAIN)}
                                </Button>
                            </div>
                        </li>
                    ))}
                </ol>
                <Button variant="secondary" onClick={addStep}>
                    {__('Add step', TEXT_DOMAIN)}
                </Button>
            </div>
        </>
    );
}
