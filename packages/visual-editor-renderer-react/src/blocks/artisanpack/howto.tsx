/**
 * React renderer for the `artisanpack/howto` block (#759).
 *
 * Mirrors the visible markup from the Blade partial in
 * `packages/visual-editor-renderer-blade/resources/views/blocks/artisanpack/howto.blade.php`
 * and the edit/save components in `resources/js/visual-editor/blocks/howto/`
 * so all three renderers ship identical DOM. HowTo JSON-LD is emitted
 * only by the Blade renderer — React/Vue callers can layer their own
 * schema story (e.g. head managers) without fighting a duplicate script.
 */

import { attrString, classList } from '../../support/attributes';
import type { BlockRendererProps } from '../../types';

interface HowtoStep {
    name: string;
    text: string;
    imageUrl: string;
    imageAlt: string;
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

function normalizeSteps(value: unknown): ReadonlyArray<HowtoStep> {
    if (!Array.isArray(value)) {
        return [];
    }

    const steps: HowtoStep[] = [];

    for (const entry of value) {
        if (entry === null || typeof entry !== 'object') {
            continue;
        }

        const raw = entry as Record<string, unknown>;
        const name = attrString(raw.name);
        const text = attrString(raw.text);
        const imageUrl = attrString(raw.imageUrl);
        const imageAlt = attrString(raw.imageAlt);

        if (name.trim() === '' && text.trim() === '') {
            continue;
        }

        steps.push({ name, text, imageUrl, imageAlt });
    }

    return steps;
}

export function HowtoBlock({ attributes }: BlockRendererProps): JSX.Element {
    const level = clampHeadingLevel(attributes.headingLevel);
    const steps = normalizeSteps(attributes.steps);
    const name = attrString(attributes.name);
    const description = attrString(attributes.description);
    const className = attrString(attributes.className);

    const classes = classList(['ap-howto', className]);

    const StepNameTag = `h${level}` as 'h2' | 'h3' | 'h4' | 'h5' | 'h6';

    return (
        <div className={classes}>
            {name !== '' && (
                <h2
                    className="ap-howto__name"
                    dangerouslySetInnerHTML={{ __html: name }}
                />
            )}
            {description !== '' && (
                <p
                    className="ap-howto__description"
                    dangerouslySetInnerHTML={{ __html: description }}
                />
            )}
            <ol className="ap-howto__steps">
                {steps.map((step, index) => (
                    <li key={index} className="ap-howto__step">
                        <StepNameTag
                            className="ap-howto__step-name"
                            dangerouslySetInnerHTML={{ __html: step.name }}
                        />
                        <div
                            className="ap-howto__step-text"
                            dangerouslySetInnerHTML={{ __html: step.text }}
                        />
                        {step.imageUrl.trim() !== '' && (
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
