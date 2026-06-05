/**
 * Cover — block toolbar controls.
 *
 * Ported from `@wordpress/block-library/src/cover/edit/block-controls.js`
 * (v9.43.0). Upstream destructures `cleanEmptyObject` from
 * `unlock(blockEditorPrivateApis)`; the fork inlines a recursive
 * `cleanEmptyObject` so it does not depend on block-library internals
 * (documented under `knownDivergences`).
 */

import type { ReactElement } from 'react';
import { useState } from '@wordpress/element';

import {
    BlockControls,
    MediaReplaceFlow,
    // eslint-disable-next-line camelcase
    __experimentalBlockAlignmentMatrixControl as BlockAlignmentMatrixControl,
    // eslint-disable-next-line camelcase
    __experimentalBlockFullHeightAligmentControl as FullHeightAlignmentControl,
} from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { MenuItem } from '@wordpress/components';
import { link } from '@wordpress/icons';

import { ALLOWED_MEDIA_TYPES, EMBED_VIDEO_BACKGROUND_TYPE } from '../shared';
import EmbedVideoUrlInput from './embed-video-url-input';

function cleanEmptyObject<T>(object: T): T | undefined {
    if (
        object === null ||
        object === undefined ||
        typeof object !== 'object' ||
        Array.isArray(object)
    ) {
        return object;
    }

    const cleaned = Object.entries(object as Record<string, unknown>)
        .map(([key, value]) => [key, cleanEmptyObject(value)] as const)
        .filter(([, value]) => value !== undefined);

    if (cleaned.length === 0) {
        return undefined;
    }

    return Object.fromEntries(cleaned) as unknown as T;
}

interface CoverAttributes {
    contentPosition?: string;
    id?: number;
    useFeaturedImage?: boolean;
    minHeight?: number;
    minHeightUnit?: string;
    backgroundType?: string;
    style?: {
        dimensions?: {
            aspectRatio?: string;
        };
    } & Record<string, unknown>;
    [key: string]: unknown;
}

interface CoverBlockControlsProps {
    attributes: CoverAttributes;
    setAttributes: (next: Partial<CoverAttributes>) => void;
    onSelectMedia: (media: unknown) => void;
    currentSettings: {
        hasInnerBlocks?: boolean;
        url?: string;
        [key: string]: unknown;
    };
    toggleUseFeaturedImage: () => void;
    onClearMedia: () => void;
    onSelectEmbedUrl: (url: string) => void;
    blockEditingMode: string;
}

export default function CoverBlockControls({
    attributes,
    setAttributes,
    onSelectMedia,
    currentSettings,
    toggleUseFeaturedImage,
    onClearMedia,
    onSelectEmbedUrl,
    blockEditingMode,
}: CoverBlockControlsProps): ReactElement {
    const {
        contentPosition,
        id,
        useFeaturedImage,
        minHeight,
        minHeightUnit,
        backgroundType,
    } = attributes;
    const { hasInnerBlocks, url } = currentSettings;

    const [prevMinHeightValue, setPrevMinHeightValue] = useState<
        number | undefined
    >(minHeight);
    const [prevMinHeightUnit, setPrevMinHeightUnit] = useState<
        string | undefined
    >(minHeightUnit);
    const [isEmbedUrlInputOpen, setIsEmbedUrlInputOpen] =
        useState<boolean>(false);

    const isMinFullHeight =
        minHeightUnit === 'vh' &&
        minHeight === 100 &&
        !attributes?.style?.dimensions?.aspectRatio;
    const isContentOnlyMode = blockEditingMode === 'contentOnly';

    const toggleMinFullHeight = (): void => {
        if (isMinFullHeight) {
            if (prevMinHeightUnit === 'vh' && prevMinHeightValue === 100) {
                setAttributes({
                    minHeight: undefined,
                    minHeightUnit: undefined,
                });
                return;
            }

            setAttributes({
                minHeight: prevMinHeightValue,
                minHeightUnit: prevMinHeightUnit,
            });
            return;
        }

        setPrevMinHeightValue(minHeight);
        setPrevMinHeightUnit(minHeightUnit);

        setAttributes({
            minHeight: 100,
            minHeightUnit: 'vh',
            style: cleanEmptyObject({
                ...attributes?.style,
                dimensions: {
                    ...attributes?.style?.dimensions,
                    aspectRatio: undefined,
                },
            }),
        });
    };

    return (
        <>
            {!isContentOnlyMode && (
                <BlockControls group="block">
                    <BlockAlignmentMatrixControl
                        label={__('Change content position')}
                        value={contentPosition}
                        onChange={(nextPosition: string) =>
                            setAttributes({ contentPosition: nextPosition })
                        }
                        isDisabled={!hasInnerBlocks}
                    />
                    <FullHeightAlignmentControl
                        isActive={isMinFullHeight}
                        onToggle={toggleMinFullHeight}
                        isDisabled={!hasInnerBlocks}
                    />
                </BlockControls>
            )}
            <BlockControls group="other">
                <MediaReplaceFlow
                    mediaId={id}
                    mediaURL={url}
                    allowedTypes={ALLOWED_MEDIA_TYPES as unknown as string[]}
                    onSelect={onSelectMedia}
                    onToggleFeaturedImage={toggleUseFeaturedImage}
                    useFeaturedImage={useFeaturedImage}
                    name={!url ? __('Add media') : __('Replace')}
                    onReset={onClearMedia}
                    variant="toolbar"
                >
                    {({ onClose }: { onClose: () => void }) => (
                        <MenuItem
                            icon={link}
                            onClick={() => {
                                setIsEmbedUrlInputOpen(true);
                                onClose();
                            }}
                        >
                            {__('Embed video from URL')}
                        </MenuItem>
                    )}
                </MediaReplaceFlow>
            </BlockControls>
            {isEmbedUrlInputOpen && (
                <EmbedVideoUrlInput
                    onSubmit={(embedUrl: string) => {
                        onSelectEmbedUrl(embedUrl);
                    }}
                    onClose={() => setIsEmbedUrlInputOpen(false)}
                    initialUrl={
                        backgroundType === EMBED_VIDEO_BACKGROUND_TYPE
                            ? url ?? ''
                            : ''
                    }
                />
            )}
        </>
    );
}
