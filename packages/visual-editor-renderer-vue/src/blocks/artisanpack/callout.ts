/**
 * Vue renderer for the `artisanpack/callout` reference block.
 *
 * Mirrors the Blade partial and the React renderer so every rendering
 * environment emits identical markup for the same saved block tree.
 */

import { defineComponent, h } from 'vue';

import { attrString, classList } from '../../support/attributes';
import { blockRendererProps } from '../shared';

type CalloutSeverity = 'info' | 'success' | 'warning' | 'error';
type CalloutIconName = 'info' | 'check' | 'warning' | 'error' | 'lightbulb';

const VALID_SEVERITIES: ReadonlyArray<CalloutSeverity> = [
    'info',
    'success',
    'warning',
    'error',
];

const VALID_ICONS: ReadonlyArray<CalloutIconName> = [
    'info',
    'check',
    'warning',
    'error',
    'lightbulb',
];

const ICON_PATHS: Readonly<Record<CalloutIconName, string>> = {
    info: 'M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm1 15h-2v-6h2Zm0-8h-2V7h2Z',
    check: 'M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm-1.5 14.5-4-4 1.4-1.4 2.6 2.6 6.6-6.6L17.5 8.5Z',
    warning: 'M12 2 1 21h22Zm1 15h-2v-2h2Zm0-4h-2V9h2Z',
    error: 'M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm5 13.6L15.6 17 12 13.4 8.4 17 7 15.6 10.6 12 7 8.4 8.4 7 12 10.6 15.6 7 17 8.4 13.4 12Z',
    lightbulb: 'M9 21h6v-1H9Zm3-19a7 7 0 0 0-4 12.74V17h8v-2.26A7 7 0 0 0 12 2Zm1 12h-2v-2h2Z',
};

function normalizeSeverity(value: unknown): CalloutSeverity {
    const raw = attrString(value, 'info');
    return (VALID_SEVERITIES as ReadonlyArray<string>).includes(raw)
        ? (raw as CalloutSeverity)
        : 'info';
}

function normalizeIcon(value: unknown): CalloutIconName {
    const raw = attrString(value, 'info');
    return (VALID_ICONS as ReadonlyArray<string>).includes(raw)
        ? (raw as CalloutIconName)
        : 'info';
}

export const CalloutBlock = defineComponent({
    name: 'CalloutBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const severity = normalizeSeverity(props.attributes.severity);
            const icon = normalizeIcon(props.attributes.icon);
            const content = attrString(props.attributes.content);
            const className = attrString(props.attributes.className);

            const classes = classList([
                'ap-callout',
                `ap-callout--${severity}`,
                className,
            ]);

            return h(
                'div',
                { class: classes, 'data-severity': severity },
                [
                    h('span', { class: 'ap-callout__icon', 'aria-hidden': 'true' }, [
                        h(
                            'svg',
                            {
                                xmlns: 'http://www.w3.org/2000/svg',
                                viewBox: '0 0 24 24',
                                'aria-hidden': 'true',
                                focusable: 'false',
                            },
                            [h('path', { d: ICON_PATHS[icon] })],
                        ),
                    ]),
                    h('div', {
                        class: 'ap-callout__body',
                        innerHTML: content,
                    }),
                ],
            );
        };
    },
});
