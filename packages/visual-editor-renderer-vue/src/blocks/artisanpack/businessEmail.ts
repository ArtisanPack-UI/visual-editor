/**
 * Vue renderer for the `artisanpack/business-email` block (#761).
 *
 * Mirrors the Blade partial + React renderer.
 */

import { defineComponent, h, type VNode } from 'vue';

import { attrBoolean, attrRecord, attrString, classList } from '../../support/attributes';
import { blockRendererProps } from '../shared';

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export const BusinessEmailBlock = defineComponent({
    name: 'BusinessEmailBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const attributes = props.attributes;
            const info = attrRecord(attributes._resolvedBusinessInfo);
            const email = attrString(info.email).trim();

            const className = attrString(attributes.className);
            const wrapperClasses = classList(['ap-business-email', className]);

            const isValid = email !== '' && EMAIL_PATTERN.test(email);

            const showIcon = attrBoolean(attributes.showIcon, false);
            const explicitLabel = attrString(attributes.label);
            const label = explicitLabel !== '' ? explicitLabel : email;

            if (!isValid) {
                return h('div', { class: wrapperClasses });
            }

            const linkChildren: VNode[] = [];
            if (showIcon) {
                linkChildren.push(
                    h(
                        'span',
                        { class: 'ap-business-email__icon', 'aria-hidden': 'true' },
                        '✉'
                    )
                );
            }
            linkChildren.push(
                h('span', { class: 'ap-business-email__label' }, label)
            );

            return h('div', { class: wrapperClasses }, [
                h(
                    'a',
                    {
                        class: 'ap-business-email__link',
                        href: `mailto:${email}`,
                    },
                    linkChildren
                ),
            ]);
        };
    },
});
