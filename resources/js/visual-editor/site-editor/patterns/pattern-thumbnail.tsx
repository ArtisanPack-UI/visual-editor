/**
 * Lightweight pattern thumbnail.
 *
 * The design brief calls for "rendered block-tree preview" thumbnails,
 * but spinning up a full block-editor render per card would balloon
 * memory + first-paint time once a few dozen patterns exist (and we
 * haven't shipped a server-side preview endpoint yet — the M6 dynamic-
 * block preview is single-block only). For V1 the thumbnail is a
 * client-side, no-iframe summary: scaled card showing block-tree
 * skeleton (the type names) with the title overlaid. It tells the user
 * what's in the pattern without the cost of an editor instance per
 * card.
 *
 * Replace this component with a server-rendered preview the day a
 * pattern-preview endpoint ships.
 */

import { __ } from '@wordpress/i18n';
import { useMemo } from 'react';

import { TEXT_DOMAIN } from '../../vendor/i18n';

import './pattern-thumbnail.css';

type BlockTreeNode = {
    name?: unknown;
    blockName?: unknown;
    innerBlocks?: unknown;
};

export interface PatternThumbnailProps {
    blocks: readonly unknown[];
    title: string;
}

function readBlockName(node: BlockTreeNode): string {
    if (typeof node.blockName === 'string' && node.blockName !== '') {
        return node.blockName;
    }

    if (typeof node.name === 'string' && node.name !== '') {
        return node.name;
    }

    return 'core/missing';
}

function readInnerBlocks(node: BlockTreeNode): readonly BlockTreeNode[] {
    return Array.isArray(node.innerBlocks)
        ? (node.innerBlocks as readonly BlockTreeNode[])
        : [];
}

function describeBlocks(
    blocks: readonly unknown[],
    depth = 0,
    cap = 8
): { lines: string[]; capped: boolean } {
    const lines: string[] = [];
    let capped = false;

    for (const raw of blocks) {
        if (lines.length >= cap) {
            capped = true;
            break;
        }

        if (raw === null || typeof raw !== 'object') {
            continue;
        }

        const node = raw as BlockTreeNode;
        const indent = '  '.repeat(depth);

        lines.push(`${indent}${readBlockName(node)}`);

        const children = readInnerBlocks(node);

        if (children.length > 0) {
            const child = describeBlocks(children, depth + 1, cap - lines.length);
            lines.push(...child.lines);

            if (child.capped) {
                capped = true;
                break;
            }
        }
    }

    return { lines, capped };
}

export function PatternThumbnail(props: PatternThumbnailProps): JSX.Element {
    const { blocks, title } = props;

    const summary = useMemo(() => describeBlocks(blocks), [blocks]);

    if (blocks.length === 0) {
        return (
            <div
                className="ap-pattern-thumb ap-pattern-thumb--empty"
                data-testid="ap-pattern-thumb-empty"
                aria-hidden="true"
            >
                <span className="ap-pattern-thumb__placeholder">
                    {__('Empty pattern', TEXT_DOMAIN)}
                </span>
            </div>
        );
    }

    return (
        <div
            className="ap-pattern-thumb"
            data-testid="ap-pattern-thumb"
            aria-label={title}
        >
            <ul className="ap-pattern-thumb__tree">
                {summary.lines.map((line, index) => (
                    <li key={`${index}-${line}`}>{line.trim()}</li>
                ))}
                {summary.capped ? (
                    <li className="ap-pattern-thumb__more">
                        {__('…', TEXT_DOMAIN)}
                    </li>
                ) : null}
            </ul>
        </div>
    );
}
