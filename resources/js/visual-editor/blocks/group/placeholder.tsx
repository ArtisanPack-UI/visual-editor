/**
 * Group — variation placeholder.
 *
 * Ported from `@wordpress/block-library/src/group/placeholder.js`
 * (v9.43.0). Preserves the "select a layout" placeholder and the
 * variation icons.
 */

import type { ReactElement } from 'react';
import { useSelect } from '@wordpress/data';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { store as blocksStore } from '@wordpress/blocks';
// eslint-disable-next-line @typescript-eslint/no-explicit-any
import { Button, Placeholder } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';

type IconName = 'group' | 'group-row' | 'group-stack';

const getGroupPlaceholderIcons = (
    name: IconName = 'group'
): ReactElement | undefined => {
    const icons: Record<IconName, ReactElement> = {
        group: (
            <svg
                xmlns="http://www.w3.org/2000/svg"
                width="48"
                height="48"
                viewBox="0 0 48 48"
            >
                <path d="M0 10a2 2 0 0 1 2-2h44a2 2 0 0 1 2 2v28a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V10Z" />
            </svg>
        ),
        'group-row': (
            <svg
                xmlns="http://www.w3.org/2000/svg"
                width="48"
                height="48"
                viewBox="0 0 48 48"
            >
                <path d="M0 10a2 2 0 0 1 2-2h19a2 2 0 0 1 2 2v28a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V10Zm25 0a2 2 0 0 1 2-2h19a2 2 0 0 1 2 2v28a2 2 0 0 1-2 2H27a2 2 0 0 1-2-2V10Z" />
            </svg>
        ),
        'group-stack': (
            <svg
                xmlns="http://www.w3.org/2000/svg"
                width="48"
                height="48"
                viewBox="0 0 48 48"
            >
                <path d="M0 10a2 2 0 0 1 2-2h44a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V10Zm0 17a2 2 0 0 1 2-2h44a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V27Z" />
            </svg>
        ),
    };
    return icons[name];
};

interface GroupPlaceholderAttributes {
    readonly style?: unknown;
    readonly backgroundColor?: string;
    readonly textColor?: string;
    readonly fontSize?: string;
}

export function useShouldShowPlaceHolder({
    attributes = {},
    usedLayoutType = '',
    hasInnerBlocks = false,
}: {
    attributes?: GroupPlaceholderAttributes;
    usedLayoutType?: string;
    hasInnerBlocks?: boolean;
}): [boolean, (v: boolean) => void] {
    const { style, backgroundColor, textColor, fontSize } = attributes;
    const [showPlaceholder, setShowPlaceholder] = useState<boolean>(
        !hasInnerBlocks &&
            !backgroundColor &&
            !fontSize &&
            !textColor &&
            !style &&
            usedLayoutType !== 'flex' &&
            usedLayoutType !== 'grid'
    );

    useEffect(() => {
        if (
            !!hasInnerBlocks ||
            !!backgroundColor ||
            !!fontSize ||
            !!textColor ||
            !!style ||
            usedLayoutType === 'flex' ||
            usedLayoutType === 'grid'
        ) {
            setShowPlaceholder(false);
        }
    }, [
        backgroundColor,
        fontSize,
        textColor,
        style,
        usedLayoutType,
        hasInnerBlocks,
    ]);

    return [showPlaceholder, setShowPlaceholder];
}

interface Variation {
    name: string;
    title: string;
    description: string;
    attributes: unknown;
}

export default function GroupPlaceHolder({
    name,
    onSelect,
}: {
    name: string;
    onSelect: (variation: Variation) => void;
}): ReactElement {
    const variations = useSelect(
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        (select: any) =>
            (select(blocksStore).getBlockVariations(name, 'block') ??
                []) as Variation[],
        [name]
    );
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = (useBlockProps as any)({
        className: 'wp-block-group__placeholder',
    });

    useEffect(() => {
        if (variations && variations.length === 1) {
            onSelect(variations[0]);
        }
    }, [onSelect, variations]);

    return (
        <div {...blockProps}>
            <Placeholder
                // @ts-expect-error - upstream prop
                instructions={__('Group blocks together. Select a layout:')}
            >
                <ul
                    role="list"
                    className="wp-block-group-placeholder__variations"
                    aria-label={__('Block variations')}
                >
                    {variations.map((variation) => (
                        <li key={variation.name}>
                            <Button
                                // @ts-expect-error - upstream prop
                                __next40pxDefaultSize
                                variant="tertiary"
                                icon={getGroupPlaceholderIcons(
                                    variation.name as IconName
                                )}
                                iconSize={48}
                                onClick={() => onSelect(variation)}
                                className="wp-block-group-placeholder__variation-button"
                                label={`${variation.title}: ${variation.description}`}
                            />
                        </li>
                    ))}
                </ul>
            </Placeholder>
        </div>
    );
}
