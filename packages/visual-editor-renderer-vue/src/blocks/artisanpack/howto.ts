/**
 * Vue renderer for the `artisanpack/howto` block (#759).
 *
 * Mirrors the visible markup from the Blade partial and React renderer
 * so every rendering environment emits identical DOM for a saved HowTo
 * tree. HowTo JSON-LD is emitted only by the Blade renderer.
 */

import { defineComponent, h } from 'vue';

import { attrString, classList } from '../../support/attributes';
import { blockRendererProps } from '../shared';

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

export const HowtoBlock = defineComponent({
    name: 'HowtoBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const level = clampHeadingLevel(props.attributes.headingLevel);
            const steps = normalizeSteps(props.attributes.steps);
            const name = attrString(props.attributes.name);
            const description = attrString(props.attributes.description);
            const className = attrString(props.attributes.className);

            const classes = classList(['ap-howto', className]);

            const stepNameTag = `h${level}`;

            const children: unknown[] = [];

            if (name !== '') {
                children.push(
                    h('h2', {
                        class: 'ap-howto__name',
                        innerHTML: name,
                    }),
                );
            }

            if (description !== '') {
                children.push(
                    h('p', {
                        class: 'ap-howto__description',
                        innerHTML: description,
                    }),
                );
            }

            children.push(
                h(
                    'ol',
                    { class: 'ap-howto__steps' },
                    steps.map((step, index) => {
                        const stepChildren: unknown[] = [
                            h(stepNameTag, {
                                class: 'ap-howto__step-name',
                                innerHTML: step.name,
                            }),
                            h('div', {
                                class: 'ap-howto__step-text',
                                innerHTML: step.text,
                            }),
                        ];

                        if (step.imageUrl.trim() !== '') {
                            stepChildren.push(
                                h('img', {
                                    class: 'ap-howto__step-image',
                                    src: step.imageUrl,
                                    alt: step.imageAlt,
                                }),
                            );
                        }

                        return h(
                            'li',
                            { key: index, class: 'ap-howto__step' },
                            stepChildren,
                        );
                    }),
                ),
            );

            return h('div', { class: classes }, children);
        };
    },
});
