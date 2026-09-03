/**
 * Table of Contents — editor-side component.
 *
 * The canvas subscribes to `core/block-editor`'s live block tree and
 * derives the same `{level, text, anchor}` rows the server-side
 * `TocResolver` stamps at render time (#760). Anchors are generated
 * with the same slug algorithm as the heading block, so the preview
 * matches the frontend for a document without any author-set anchors.
 * When the editor tree has no headings the preview shows a placeholder
 * that also describes the min/max heading levels that will appear.
 */

import type { ReactElement } from 'react';
import {
    InspectorControls,
    RichText,
    useBlockProps,
} from '@wordpress/block-editor';
import {
    PanelBody,
    SelectControl,
    ToggleControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { deriveHeadingItems, filterItemsByLevel, type HeadingItem } from './derive-items';

const HEADING_LEVELS: ReadonlyArray<1 | 2 | 3 | 4 | 5 | 6> = [1, 2, 3, 4, 5, 6];
const LABEL_HEADING_LEVELS: ReadonlyArray<2 | 3 | 4 | 5 | 6> = [2, 3, 4, 5, 6];

interface TocAttributes {
    readonly heading: string;
    readonly headingLevel: number;
    readonly minLevel: number;
    readonly maxLevel: number;
    readonly ordered: boolean;
}

interface TocEditProps {
    readonly attributes: TocAttributes;
    readonly setAttributes: (next: Partial<TocAttributes>) => void;
}

interface HeadingNode extends HeadingItem {
    readonly children: HeadingNode[];
}

function clampLevel(value: unknown, fallback: 1 | 2 | 3 | 4 | 5 | 6): 1 | 2 | 3 | 4 | 5 | 6 {
    const numeric = typeof value === 'number' ? value : Number(value);

    if (!Number.isFinite(numeric)) {
        return fallback;
    }

    const rounded = Math.round(numeric);

    if (rounded <= 1) {
        return 1;
    }

    if (rounded >= 6) {
        return 6;
    }

    return rounded as 1 | 2 | 3 | 4 | 5 | 6;
}

function clampLabelLevel(value: unknown): 2 | 3 | 4 | 5 | 6 {
    const level = clampLevel(value, 2);

    return level === 1 ? 2 : (level as 2 | 3 | 4 | 5 | 6);
}

/**
 * Fold a flat, document-ordered heading list into a nested tree keyed
 * by depth. Mirrors the Blade partial's `buildTree` so preview and
 * frontend structure match.
 */
function buildTree(flat: ReadonlyArray<HeadingItem>): HeadingNode[] {
    const root: HeadingNode[] = [];
    const stack: HeadingNode[][] = [root];
    const levels: number[] = [0];

    for (const entry of flat) {
        while (levels.length > 1 && levels[levels.length - 1] >= entry.level) {
            stack.pop();
            levels.pop();
        }

        const node: HeadingNode = { ...entry, children: [] };
        const parent = stack[stack.length - 1];
        parent.push(node);
        stack.push(node.children);
        levels.push(entry.level);
    }

    return root;
}

function renderTree(nodes: ReadonlyArray<HeadingNode>, ordered: boolean): ReactElement {
    const ListTag = ordered ? 'ol' : 'ul';

    return (
        <ListTag className="ap-toc__list">
            {nodes.map((node, index) => (
                <li key={`${node.anchor}-${index}`} className="ap-toc__item">
                    <a
                        className="ap-toc__link"
                        href={`#${node.anchor}`}
                        onClick={(event) => event.preventDefault()}
                    >
                        {node.text}
                    </a>
                    {node.children.length > 0 && renderTree(node.children, ordered)}
                </li>
            ))}
        </ListTag>
    );
}

export default function TocEdit({ attributes, setAttributes }: TocEditProps): ReactElement {
    const { heading, headingLevel, minLevel, maxLevel, ordered } = attributes;

    const blockProps = useBlockProps({ className: 'ap-toc' });

    const min = clampLevel(minLevel, 2);
    const max = clampLevel(maxLevel, 6);
    const displayMin = min > max ? max : min;
    const displayMax = min > max ? min : max;

    const headingTag = `h${clampLabelLevel(headingLevel)}` as 'h2' | 'h3' | 'h4' | 'h5' | 'h6';

    const allItems = useSelect(
        (select) => {
            // `getBlocks()` with no arguments returns the top-level blocks of
            // the current editor. The store handles change notifications, so
            // `useSelect` re-runs and re-renders this component whenever any
            // heading changes in the tree.
            const store = select('core/block-editor') as unknown as {
                getBlocks: () => Array<{
                    name: string;
                    attributes: Record<string, unknown>;
                    innerBlocks?: unknown[];
                }>;
            };

            try {
                return deriveHeadingItems(store.getBlocks() as never);
            } catch {
                return [] as ReadonlyArray<HeadingItem>;
            }
        },
        []
    );

    const filtered = filterItemsByLevel(allItems, displayMin, displayMax);
    const tree = buildTree(filtered);

    const placeholder = displayMin === displayMax
        ? sprintf( __( 'No H%d headings on this page yet — add one to see it here.', TEXT_DOMAIN ), displayMin )
        : sprintf( __( 'No H%1$d–H%2$d headings on this page yet — add one to see it here.', TEXT_DOMAIN ), displayMin, displayMax );

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Table of contents', TEXT_DOMAIN)} initialOpen>
                    <SelectControl
                        label={__('Minimum heading level', TEXT_DOMAIN)}
                        value={String(min)}
                        options={HEADING_LEVELS.map((value) => ({
                            label: `H${value}`,
                            value: String(value),
                        }))}
                        onChange={(value) =>
                            setAttributes({ minLevel: clampLevel(value, 2) })
                        }
                        __nextHasNoMarginBottom
                    />
                    <SelectControl
                        label={__('Maximum heading level', TEXT_DOMAIN)}
                        value={String(max)}
                        options={HEADING_LEVELS.map((value) => ({
                            label: `H${value}`,
                            value: String(value),
                        }))}
                        onChange={(value) =>
                            setAttributes({ maxLevel: clampLevel(value, 6) })
                        }
                        __nextHasNoMarginBottom
                    />
                    <ToggleControl
                        label={__('Numbered list', TEXT_DOMAIN)}
                        help={__(
                            'Render the entries as a numbered (ordered) list instead of a bulleted (unordered) list.',
                            TEXT_DOMAIN
                        )}
                        checked={ordered}
                        onChange={(next) => setAttributes({ ordered: next })}
                        __nextHasNoMarginBottom
                    />
                    <SelectControl
                        label={__('Label heading level', TEXT_DOMAIN)}
                        value={String(clampLabelLevel(headingLevel))}
                        options={LABEL_HEADING_LEVELS.map((value) => ({
                            label: `H${value}`,
                            value: String(value),
                        }))}
                        onChange={(value) =>
                            setAttributes({ headingLevel: clampLabelLevel(value) })
                        }
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            <nav {...blockProps} aria-label={__('Table of contents', TEXT_DOMAIN)}>
                <RichText
                    tagName={headingTag}
                    className="ap-toc__heading"
                    value={heading}
                    onChange={(value: string) => setAttributes({ heading: value })}
                    placeholder={__('Optional label (e.g. "On this page")…', TEXT_DOMAIN)}
                    allowedFormats={['core/bold', 'core/italic']}
                />
                {tree.length === 0
                    ? <p className="ap-toc__placeholder">{placeholder}</p>
                    : renderTree(tree, ordered)}
            </nav>
        </>
    );
}
