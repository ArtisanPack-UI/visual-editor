/**
 * Vue renderer for the `artisanpack/business-phone` block (#761).
 *
 * Mirrors the Blade partial + React renderer.
 */

import { defineComponent, h, type VNode } from 'vue';

import { attrBoolean, attrRecord, attrString, classList } from '../../support/attributes';
import { blockRendererProps } from '../shared';

function telTarget(phone: string): string {
    return phone.replace(/[^0-9+xX,]/g, '');
}

export const BusinessPhoneBlock = defineComponent({
    name: 'BusinessPhoneBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const attributes = props.attributes;
            const info = attrRecord(attributes._resolvedBusinessInfo);
            const phone = attrString(info.phone).trim();

            const className = attrString(attributes.className);
            const wrapperClasses = classList(['ap-business-phone', className]);
            const ariaLabel = attrString(attributes.ariaLabel, 'Business phone');

            const showIcon = attrBoolean(attributes.showIcon, false);
            const explicitLabel = attrString(attributes.label);
            const label = explicitLabel !== '' ? explicitLabel : phone;

            if (phone === '') {
                return h('div', { class: wrapperClasses, 'aria-label': ariaLabel });
            }

            const linkChildren: VNode[] = [];
            if (showIcon) {
                linkChildren.push(
                    h(
                        'span',
                        { class: 'ap-business-phone__icon', 'aria-hidden': 'true' },
                        '☎'
                    )
                );
            }
            linkChildren.push(
                h('span', { class: 'ap-business-phone__label' }, label)
            );

            return h('div', { class: wrapperClasses, 'aria-label': ariaLabel }, [
                h(
                    'a',
                    {
                        class: 'ap-business-phone__link',
                        href: `tel:${telTarget(phone)}`,
                    },
                    linkChildren
                ),
            ]);
        };
    },
});
