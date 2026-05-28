/**
 * Columns — deprecation chain.
 *
 * Ported from `@wordpress/block-library/src/columns/deprecated.js`
 * (v9.43.0). Three legacy entries preserved under the artisanpack
 * namespace. Inner-block migrations now produce `artisanpack/column`.
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
import type { ReactElement } from 'react';
import clsx from 'clsx';
import { createBlock } from '@wordpress/blocks';
import { InnerBlocks, getColorClassName } from '@wordpress/block-editor';

const COLUMN_BLOCK = 'artisanpack/column';

function getDeprecatedLayoutColumn(originalContent: string): number | undefined {
    let doc = (getDeprecatedLayoutColumn as any).doc as Document | undefined;
    if (!doc) {
        doc = document.implementation.createHTMLDocument('');
        (getDeprecatedLayoutColumn as any).doc = doc;
    }
    doc.body.innerHTML = originalContent;
    const firstChild = doc.body.firstChild as HTMLElement | null;
    if (!firstChild) {
        return undefined;
    }
    for (const classListItem of Array.from(firstChild.classList)) {
        const columnMatch = classListItem.match(/^layout-column-(\d+)$/);
        if (columnMatch) {
            return Number(columnMatch[1]) - 1;
        }
    }
    return undefined;
}

const migrateCustomColors = (attributes: Record<string, any>) => {
    if (!attributes.customTextColor && !attributes.customBackgroundColor) {
        return attributes;
    }
    const style: { color: { text?: string; background?: string } } = {
        color: {},
    };
    if (attributes.customTextColor) {
        style.color.text = attributes.customTextColor;
    }
    if (attributes.customBackgroundColor) {
        style.color.background = attributes.customBackgroundColor;
    }
    const { customTextColor, customBackgroundColor, ...restAttributes } =
        attributes;
    return {
        ...restAttributes,
        style,
        isStackedOnMobile: true,
    };
};

const v1 = {
    attributes: {
        verticalAlignment: { type: 'string' },
        backgroundColor: { type: 'string' },
        customBackgroundColor: { type: 'string' },
        customTextColor: { type: 'string' },
        textColor: { type: 'string' },
    },
    migrate: migrateCustomColors,
    save({ attributes }: { attributes: Record<string, any> }): ReactElement {
        const {
            verticalAlignment,
            backgroundColor,
            customBackgroundColor,
            textColor,
            customTextColor,
        } = attributes;
        const backgroundClass = getColorClassName(
            'background-color',
            backgroundColor
        );
        const textClass = getColorClassName('color', textColor);
        const className = clsx({
            'has-background': backgroundColor || customBackgroundColor,
            'has-text-color': textColor || customTextColor,
            [backgroundClass as string]: backgroundClass,
            [textClass as string]: textClass,
            [`are-vertically-aligned-${verticalAlignment}`]: verticalAlignment,
        });
        const style = {
            backgroundColor: backgroundClass ? undefined : customBackgroundColor,
            color: textClass ? undefined : customTextColor,
        };
        return (
            <div className={className ? className : undefined} style={style}>
                <InnerBlocks.Content />
            </div>
        );
    },
};

const v2 = {
    attributes: {
        columns: { type: 'number', default: 2 },
    },
    isEligible(
        _attributes: Record<string, any>,
        innerBlocks: Array<{ originalContent: string }>
    ): boolean {
        const isFastPassEligible = innerBlocks.some((innerBlock) =>
            /layout-column-\d+/.test(innerBlock.originalContent)
        );
        if (!isFastPassEligible) {
            return false;
        }
        return innerBlocks.some(
            (innerBlock) =>
                getDeprecatedLayoutColumn(innerBlock.originalContent) !==
                undefined
        );
    },
    migrate(
        attributes: Record<string, any>,
        innerBlocks: Array<{ originalContent: string }>
    ): [Record<string, any>, any[]] {
        const columns = innerBlocks.reduce(
            (accumulator: any[][], innerBlock) => {
                const { originalContent } = innerBlock;
                let columnIndex = getDeprecatedLayoutColumn(originalContent);
                if (columnIndex === undefined) {
                    columnIndex = 0;
                }
                if (!accumulator[columnIndex]) {
                    accumulator[columnIndex] = [];
                }
                accumulator[columnIndex].push(innerBlock);
                return accumulator;
            },
            []
        );
        const migratedInnerBlocks = columns.map((columnBlocks: any[]) =>
            createBlock(COLUMN_BLOCK, {}, columnBlocks)
        );
        const { columns: _ignored, ...restAttributes } = attributes;
        return [
            { ...restAttributes, isStackedOnMobile: true },
            migratedInnerBlocks,
        ];
    },
    save({ attributes }: { attributes: Record<string, any> }): ReactElement {
        const { columns } = attributes;
        return (
            <div className={`has-${columns}-columns`}>
                <InnerBlocks.Content />
            </div>
        );
    },
};

const v3 = {
    attributes: {
        columns: { type: 'number', default: 2 },
    },
    migrate(
        attributes: Record<string, any>,
        innerBlocks: any[]
    ): [Record<string, any>, any[]] {
        const { columns: _columns, ...restAttributes } = attributes;
        return [
            { ...restAttributes, isStackedOnMobile: true },
            innerBlocks,
        ];
    },
    save({ attributes }: { attributes: Record<string, any> }): ReactElement {
        const { verticalAlignment, columns } = attributes;
        const wrapperClasses = clsx(`has-${columns}-columns`, {
            [`are-vertically-aligned-${verticalAlignment}`]: verticalAlignment,
        });
        return (
            <div className={wrapperClasses}>
                <InnerBlocks.Content />
            </div>
        );
    },
};

export default [v1, v2, v3];
