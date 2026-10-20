/**
 * Heading — editor-side render.
 *
 * Ported from `@wordpress/block-library/src/heading/edit.js` (v9.43.0).
 * Behaviour parity: anchor auto-generation tied to the table-of-contents
 * setting, content/anchor side-effect, identical RichText wiring.
 */

import type { ReactElement } from 'react';
import { __ } from '@wordpress/i18n';
import { TEXT_DOMAIN } from '../../vendor/i18n';
import { useEffect } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { safeHTML } from '@wordpress/dom';
import {
    RichText,
    useBlockProps,
    store as blockEditorStore,
} from '@wordpress/block-editor';

import { useBoundAttribute } from '../../bindings/bound-attributes-context';
import { generateAnchor, setAnchor } from './autogenerate-anchors';

interface HeadingAttributes {
    readonly content: string;
    readonly level: number;
    readonly placeholder?: string;
    readonly anchor?: string;
}

interface HeadingEditProps {
    readonly attributes: HeadingAttributes;
    readonly setAttributes: (next: Partial<HeadingAttributes>) => void;
    readonly mergeBlocks?: (forward?: boolean) => void;
    readonly onReplace?: (blocks: unknown[]) => void;
    readonly clientId: string;
    readonly style?: Record<string, unknown>;
}

interface BlockEditorSelect {
    getGlobalBlockCount: (blockName?: string) => number;
    getSettings: () => Record<string, unknown> & { generateAnchors?: boolean };
}

// Clamp the level attribute to a valid heading range. Mirrors `save.tsx`
// so the editor canvas and the saved markup agree on the tag for any
// out-of-range legacy attribute (level=0, level=7, …).
function clampHeadingLevel(level: number | undefined): 1 | 2 | 3 | 4 | 5 | 6 {
    const numeric = Number(level);
    if (!Number.isFinite(numeric)) {
        return 2;
    }
    return Math.max(1, Math.min(6, Math.trunc(numeric))) as 1 | 2 | 3 | 4 | 5 | 6;
}

export default function HeadingEdit(props: HeadingEditProps): ReactElement {
    const { attributes, setAttributes, mergeBlocks, onReplace, style, clientId } = props;
    const { content, level, placeholder, anchor } = attributes;
    const safeLevel = clampHeadingLevel(level);
    const tagName = `h${safeLevel}` as 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6';
    const blockProps = useBlockProps({ style });
    const contentBinding = useBoundAttribute('content');

    const { canGenerateAnchors } = useSelect((select) => {
        const store = select(blockEditorStore) as unknown as BlockEditorSelect;
        const settings = store.getSettings();
        return {
            canGenerateAnchors:
                !!settings.generateAnchors ||
                store.getGlobalBlockCount('core/table-of-contents') > 0,
        };
    }, []) as { canGenerateAnchors: boolean };

    const dispatchStore = useDispatch(blockEditorStore) as unknown as {
        __unstableMarkNextChangeAsNotPersistent: () => void;
    };
    const { __unstableMarkNextChangeAsNotPersistent } = dispatchStore;

    useEffect(() => {
        if (!canGenerateAnchors) {
            return;
        }

        if (!anchor && content) {
            __unstableMarkNextChangeAsNotPersistent();
            setAttributes({
                anchor: generateAnchor(clientId, content) ?? undefined,
            });
        }
        setAnchor(clientId, anchor ?? null);

        return () => setAnchor(clientId, null);
    }, [anchor, content, clientId, canGenerateAnchors]);

    const onContentChange = (value: string): void => {
        const newAttrs: Partial<HeadingAttributes> = { content: value };
        if (
            canGenerateAnchors &&
            (!anchor ||
                !value ||
                generateAnchor(clientId, content) === anchor)
        ) {
            newAttrs.anchor = generateAnchor(clientId, value) ?? undefined;
        }
        setAttributes(newAttrs);
    };

    // Content bound to a data source is overlaid with the resolved value,
    // so an editable RichText would discard every keystroke. Render it
    // read-only instead; it unlocks once the binding is disconnected (#995).
    // A bound heading renders its resolved value read-only (#995). The
    // tag is rendered directly rather than through `RichText.Content`,
    // a function component that would drop `blockProps.ref`. Binding
    // sources return stored values unsanitised, so scripts and event
    // handlers are stripped before the markup reaches the canvas.
    if (contentBinding !== null) {
        const BoundTag = tagName as 'h2';

        return (
            <BoundTag
                {...blockProps}
                aria-readonly="true"
                dangerouslySetInnerHTML={{ __html: safeHTML(content ?? '') }}
            />
        );
    }

    return (
        <RichText
            identifier="content"
            tagName={tagName}
            value={content}
            onChange={onContentChange}
            onMerge={mergeBlocks}
            onReplace={onReplace}
            onRemove={() => onReplace?.([])}
            placeholder={placeholder || __('Heading', TEXT_DOMAIN)}
            {...blockProps}
        />
    );
}
