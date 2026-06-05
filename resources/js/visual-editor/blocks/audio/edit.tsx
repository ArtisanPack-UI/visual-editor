/**
 * Audio — editor-side render.
 *
 * Ported from `@wordpress/block-library/src/audio/edit.js` (v9.43.0).
 * Behaviour parity is the goal for everything the saved markup depends on:
 * the `src`/`id`/`caption`/`autoplay`/`loop`/`preload` attributes round-trip
 * losslessly across editor sessions. Intentional divergences (documented in
 * `upstream-state.json` under `knownDivergences`):
 *
 *   - The blob-URL upload path (`useUploadMediaFromBlobURL`) and the audio
 *     URL → embed-block upgrade path (`createUpgradedEmbedBlock`) are
 *     omitted. The fork uses `MediaPlaceholder` for both file selection
 *     and URL entry; the editor caller is responsible for resolving any
 *     URL → embed conversion via post-paste filters if needed.
 *   - The custom `Caption` component from
 *     `@wordpress/block-library/src/utils/caption` is replaced by an
 *     inline `RichText` figcaption so the fork doesn't depend on
 *     block-library internals not exposed in the package's `exports`.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import {
    Disabled,
    SelectControl,
    ToggleControl,
    __experimentalToolsPanel as ToolsPanel,
    __experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import {
    BlockControls,
    BlockIcon,
    InspectorControls,
    MediaPlaceholder,
    MediaReplaceFlow,
    RichText,
    useBlockProps,
    useBlockEditingMode,
} from '@wordpress/block-editor';
import { __, _x } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { audio as icon } from '@wordpress/icons';
import { store as noticesStore } from '@wordpress/notices';

const ALLOWED_MEDIA_TYPES = ['audio'] as const;

interface AudioAttributes {
    readonly id?: number;
    readonly src?: string;
    readonly caption?: string;
    readonly autoplay?: boolean;
    readonly loop?: boolean;
    readonly preload?: string;
    readonly blob?: string;
}

interface MediaItem {
    readonly id?: number;
    readonly url?: string;
    readonly caption?: string;
}

interface AudioEditProps {
    readonly attributes: AudioAttributes;
    readonly className?: string;
    readonly setAttributes: (next: Partial<AudioAttributes>) => void;
    readonly onReplace?: (block: unknown) => void;
    readonly isSelected: boolean;
    readonly insertBlocksAfter?: (block: unknown) => void;
}

function getAutoplayHelp(checked: boolean): string | null {
    return checked
        ? __('Autoplay may cause usability issues for some users.')
        : null;
}

export default function AudioEdit({
    attributes,
    className,
    setAttributes,
    isSelected: isSingleSelected,
}: AudioEditProps): ReactElement {
    const { id, autoplay, loop, preload, src, caption } = attributes;
    const blockEditingMode = useBlockEditingMode();
    const hasNonContentControls = blockEditingMode === 'default';

    const { createErrorNotice } = useDispatch(noticesStore);

    function toggleAttribute(
        attribute: keyof AudioAttributes
    ): (value: boolean) => void {
        return (newValue: boolean) => {
            setAttributes({ [attribute]: newValue } as Partial<AudioAttributes>);
        };
    }

    function onSelectURL(newSrc: string): void {
        if (newSrc !== src) {
            setAttributes({ src: newSrc, id: undefined, blob: undefined });
        }
    }

    function onUploadError(message: string): void {
        createErrorNotice(message, { type: 'snackbar' });
    }

    function onSelectAudio(media: MediaItem | undefined): void {
        if (!media || !media.url) {
            setAttributes({
                src: undefined,
                id: undefined,
                caption: undefined,
                blob: undefined,
            });
            return;
        }

        setAttributes({
            blob: undefined,
            src: media.url,
            id: media.id,
            caption: media.caption,
        });
    }

    const classes = clsx(className);
    const blockProps = useBlockProps({ className: classes });

    if (!src) {
        return (
            <div {...blockProps}>
                <MediaPlaceholder
                    icon={<BlockIcon icon={icon} />}
                    onSelect={onSelectAudio}
                    onSelectURL={onSelectURL}
                    accept="audio/*"
                    allowedTypes={ALLOWED_MEDIA_TYPES as unknown as string[]}
                    value={attributes}
                    onError={onUploadError}
                />
            </div>
        );
    }

    return (
        <>
            {isSingleSelected && (
                <BlockControls group="other">
                    <MediaReplaceFlow
                        mediaId={id}
                        mediaURL={src}
                        allowedTypes={ALLOWED_MEDIA_TYPES as unknown as string[]}
                        accept="audio/*"
                        onSelect={onSelectAudio}
                        onSelectURL={onSelectURL}
                        onError={onUploadError}
                        onReset={() => onSelectAudio(undefined)}
                        variant="toolbar"
                    />
                </BlockControls>
            )}
            <InspectorControls>
                <ToolsPanel
                    label={__('Settings')}
                    resetAll={() => {
                        setAttributes({
                            autoplay: false,
                            loop: false,
                            preload: undefined,
                        });
                    }}
                >
                    <ToolsPanelItem
                        label={__('Autoplay')}
                        isShownByDefault
                        hasValue={() => !!autoplay}
                        onDeselect={() => setAttributes({ autoplay: false })}
                    >
                        <ToggleControl
                            label={__('Autoplay')}
                            onChange={toggleAttribute('autoplay')}
                            checked={!!autoplay}
                            help={getAutoplayHelp}
                        />
                    </ToolsPanelItem>
                    <ToolsPanelItem
                        label={__('Loop')}
                        isShownByDefault
                        hasValue={() => !!loop}
                        onDeselect={() => setAttributes({ loop: false })}
                    >
                        <ToggleControl
                            label={__('Loop')}
                            onChange={toggleAttribute('loop')}
                            checked={!!loop}
                        />
                    </ToolsPanelItem>
                    <ToolsPanelItem
                        label={__('Preload')}
                        isShownByDefault
                        hasValue={() => !!preload}
                        onDeselect={() => setAttributes({ preload: undefined })}
                    >
                        <SelectControl
                            __next40pxDefaultSize
                            label={_x('Preload', 'noun; Audio block parameter')}
                            value={preload || ''}
                            onChange={(value: string) =>
                                setAttributes({
                                    preload: value || undefined,
                                })
                            }
                            options={[
                                { value: '', label: __('Browser default') },
                                { value: 'auto', label: __('Auto') },
                                { value: 'metadata', label: __('Metadata') },
                                {
                                    value: 'none',
                                    label: _x('None', 'Preload value'),
                                },
                            ]}
                        />
                    </ToolsPanelItem>
                </ToolsPanel>
            </InspectorControls>
            <figure {...blockProps}>
                <Disabled isDisabled={!isSingleSelected}>
                    <audio controls="controls" src={src} />
                </Disabled>
                {(isSingleSelected && hasNonContentControls) ||
                !RichText.isEmpty(caption ?? '') ? (
                    <RichText
                        identifier="caption"
                        tagName="figcaption"
                        aria-label={__('Audio caption text')}
                        placeholder={__('Add caption')}
                        value={caption ?? ''}
                        onChange={(value: string) =>
                            setAttributes({ caption: value })
                        }
                        inlineToolbar
                    />
                ) : null}
            </figure>
        </>
    );
}
