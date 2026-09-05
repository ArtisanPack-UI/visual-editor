/**
 * HowTo — saved markup.
 *
 * Persists the frontend DOM shape for the HowTo block (#759). JSON-LD
 * is *not* embedded here; the Blade renderer emits `HowTo` JSON-LD at
 * request time so authors can toggle `emitSchema` without dirtying the
 * saved markup, and so double-emission with a page-level schema block
 * can be avoided per-render.
 */

import type { ReactElement } from 'react';
import { RichText, useBlockProps } from '@wordpress/block-editor';

interface HowtoStep {
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

interface HowtoSaveProps {
    readonly attributes: HowtoAttributes;
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

export default function HowtoSave({ attributes }: HowtoSaveProps): ReactElement {
    const { name, description, steps, headingLevel } = attributes;

    const blockProps = useBlockProps.save({ className: 'ap-howto' });

    const level = clampHeadingLevel(headingLevel);
    const StepNameTag = `h${level}` as 'h2' | 'h3' | 'h4' | 'h5' | 'h6';

    return (
        <div {...blockProps}>
            {name !== '' && (
                <RichText.Content
                    tagName="h2"
                    className="ap-howto__name"
                    value={name}
                />
            )}
            {description !== '' && (
                <RichText.Content
                    tagName="p"
                    className="ap-howto__description"
                    value={description}
                />
            )}
            <ol className="ap-howto__steps">
                {steps.map((step, index) => (
                    <li key={index} className="ap-howto__step">
                        <RichText.Content
                            tagName={StepNameTag}
                            className="ap-howto__step-name"
                            value={step.name}
                        />
                        <RichText.Content
                            tagName="div"
                            className="ap-howto__step-text"
                            value={step.text}
                        />
                        {step.imageUrl !== '' && (
                            <img
                                className="ap-howto__step-image"
                                src={step.imageUrl}
                                alt={step.imageAlt}
                            />
                        )}
                    </li>
                ))}
            </ol>
        </div>
    );
}
