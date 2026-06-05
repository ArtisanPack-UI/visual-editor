/**
 * Separator — edit component.
 *
 * Ported from `@wordpress/block-library/src/separator/edit.js` (v9.43.0).
 * Adds an explicit `wp-block-separator` class to `useBlockProps` so the
 * editor canvas matches the front-end without depending on the
 * `__experimentalSelector` internals.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import {
    getColorClassName,
    InspectorControls,
    useBlockProps,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalUseColorProps as useColorProps,
} from '@wordpress/block-editor';
import { HorizontalRule, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import useDeprecatedOpacity from './use-deprecated-opacity';

interface SeparatorAttributes {
    readonly backgroundColor?: string;
    readonly opacity?: string;
    readonly style?: { color?: { background?: string } };
    readonly tagName?: 'hr' | 'div';
}

interface HtmlElementControlProps {
    tagName: string | undefined;
    setAttributes: (attrs: { tagName: 'hr' | 'div' }) => void;
}

function HtmlElementControl({
    tagName,
    setAttributes,
}: HtmlElementControlProps): ReactElement {
    return (
        <SelectControl
            label={__('HTML element')}
            value={tagName ?? 'hr'}
            onChange={(newValue: string) =>
                setAttributes({ tagName: newValue as 'hr' | 'div' })
            }
            options={[
                { label: __('Default (<hr>)'), value: 'hr' },
                { label: '<div>', value: 'div' },
            ]}
            help={
                tagName === 'hr'
                    ? __(
                            'Only select <hr> if the separator conveys important information and should be announced by screen readers.'
                      )
                    : __(
                            'The <div> element should only be used if the block is a design element with no semantic meaning.'
                      )
            }
            // @ts-expect-error - upstream prop
            __next40pxDefaultSize
        />
    );
}

export default function SeparatorEdit({
    attributes,
    setAttributes,
}: {
    attributes: SeparatorAttributes;
    setAttributes: (attrs: Partial<SeparatorAttributes>) => void;
}): ReactElement {
    const { backgroundColor, opacity, style, tagName } = attributes;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const colorProps = (useColorProps as any)(attributes);
    const currentColor = colorProps?.style?.backgroundColor;
    const hasCustomColor = !!style?.color?.background;

    useDeprecatedOpacity(
        opacity,
        currentColor,
        setAttributes as (attrs: { opacity: string }) => void
    );

    const colorClass = getColorClassName('color', backgroundColor);

    const className = clsx(
        'wp-block-separator',
        {
            'has-text-color': backgroundColor || currentColor,
            [colorClass as string]: colorClass,
            'has-css-opacity': opacity === 'css',
            'has-alpha-channel-opacity': opacity === 'alpha-channel',
        },
        colorProps.className
    );

    const styles = {
        color: currentColor,
        backgroundColor: currentColor,
    };
    const Wrapper = (tagName === 'hr' || !tagName ? HorizontalRule : tagName) as
        | typeof HorizontalRule
        | 'div';

    return (
        <>
            <InspectorControls group="advanced">
                <HtmlElementControl
                    tagName={tagName}
                    setAttributes={
                        setAttributes as (attrs: {
                            tagName: 'hr' | 'div';
                        }) => void
                    }
                />
            </InspectorControls>
            {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
            <Wrapper
                {...(useBlockProps({
                    className,
                    style: hasCustomColor ? styles : undefined,
                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                }) as any)}
            />
        </>
    );
}
