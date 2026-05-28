/**
 * Group — edit component.
 *
 * Ported from `@wordpress/block-library/src/group/edit.js` (v9.43.0).
 * Adds an explicit `wp-block-group` class to `useBlockProps` so the
 * editor canvas matches the front-end. The upstream `HTMLElementControl`
 * is reached through the private blockEditor APIs in core; we replace
 * it with a plain `SelectControl` so the fork does not depend on
 * `lock-unlock` / private APIs.
 */

import type { ReactElement } from 'react';
import { useDispatch, useSelect } from '@wordpress/data';
import {
    InnerBlocks,
    useBlockProps,
    InspectorControls,
    useInnerBlocksProps,
    store as blockEditorStore,
} from '@wordpress/block-editor';
import { useRef } from '@wordpress/element';
import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import GroupPlaceHolder, { useShouldShowPlaceHolder } from './placeholder';

interface GroupAttributes {
    readonly tagName?: string;
    readonly templateLock?: string | boolean;
    readonly allowedBlocks?: string[];
    readonly layout?: { type?: string; [key: string]: unknown };
    readonly style?: unknown;
    readonly backgroundColor?: string;
    readonly textColor?: string;
    readonly fontSize?: string;
}

interface GroupEditControlsProps {
    tagName: string;
    onSelectTagName: (value: string) => void;
}

function GroupEditControls({
    tagName,
    onSelectTagName,
}: GroupEditControlsProps): ReactElement {
    return (
        <InspectorControls group="advanced">
            <SelectControl
                label={__('HTML element')}
                value={tagName}
                onChange={onSelectTagName}
                options={[
                    { label: __('Default (<div>)'), value: 'div' },
                    { label: '<header>', value: 'header' },
                    { label: '<main>', value: 'main' },
                    { label: '<section>', value: 'section' },
                    { label: '<article>', value: 'article' },
                    { label: '<aside>', value: 'aside' },
                    { label: '<footer>', value: 'footer' },
                ]}
                // @ts-expect-error - upstream prop
                __next40pxDefaultSize
            />
        </InspectorControls>
    );
}

export default function GroupEdit({
    attributes,
    name,
    setAttributes,
    clientId,
}: {
    attributes: GroupAttributes;
    name: string;
    setAttributes: (attrs: Partial<GroupAttributes>) => void;
    clientId: string;
}): ReactElement {
    const { hasInnerBlocks, themeSupportsLayout } = useSelect(
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        (select: any) => {
            const { getBlock, getSettings } = select(blockEditorStore);
            const block = getBlock(clientId);
            return {
                hasInnerBlocks: !!(block && block.innerBlocks.length),
                themeSupportsLayout: getSettings()?.supportsLayout,
            };
        },
        [clientId]
    );

    const {
        tagName: TagName = 'div',
        templateLock,
        allowedBlocks,
        layout = {},
    } = attributes;

    const { type = 'default' } = layout;
    const layoutSupportEnabled =
        themeSupportsLayout || type === 'flex' || type === 'grid';

    const ref = useRef<HTMLElement | undefined>(undefined);
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = (useBlockProps as any)({
        ref,
        className: 'wp-block-group',
    });

    const [showPlaceholder, setShowPlaceholder] = useShouldShowPlaceHolder({
        attributes,
        usedLayoutType: type,
        hasInnerBlocks,
    });

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    let renderAppender: any;
    if (showPlaceholder) {
        renderAppender = false;
    } else if (!hasInnerBlocks) {
        renderAppender = InnerBlocks.ButtonBlockAppender;
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const innerBlocksProps = (useInnerBlocksProps as any)(
        layoutSupportEnabled
            ? blockProps
            : { className: 'wp-block-group__inner-container' },
        {
            dropZoneElement: ref.current,
            templateLock,
            allowedBlocks,
            renderAppender,
        }
    );

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const { selectBlock } = useDispatch(blockEditorStore) as any;

    const selectVariation = (nextVariation: {
        attributes: Partial<GroupAttributes>;
    }): void => {
        setAttributes(nextVariation.attributes);
        selectBlock(clientId, -1);
        setShowPlaceholder(false);
    };

    const Tag = TagName as keyof JSX.IntrinsicElements;

    return (
        <>
            <GroupEditControls
                tagName={TagName}
                onSelectTagName={(value) => setAttributes({ tagName: value })}
            />
            {showPlaceholder && (
                <div {...blockProps}>
                    {innerBlocksProps.children}
                    <GroupPlaceHolder
                        name={name}
                        onSelect={selectVariation}
                    />
                </div>
            )}
            {layoutSupportEnabled && !showPlaceholder && (
                <Tag {...innerBlocksProps} />
            )}
            {!layoutSupportEnabled && !showPlaceholder && (
                <Tag {...blockProps}>
                    <div {...innerBlocksProps} />
                </Tag>
            )}
        </>
    );
}
