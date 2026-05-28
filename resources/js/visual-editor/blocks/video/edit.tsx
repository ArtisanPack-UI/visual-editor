/**
 * Video — editor-side render.
 *
 * Ported from `@wordpress/block-library/src/video/edit.js` (v9.43.0).
 * Behaviour parity is the goal for everything the saved markup depends on:
 * the `src`/`id`/`caption`/`autoplay`/`controls`/`loop`/`muted`/`poster`/
 * `preload`/`playsInline`/`tracks` attributes round-trip losslessly across
 * editor sessions. Intentional divergences (documented in
 * `upstream-state.json` under `knownDivergences`):
 *
 *   - The blob-URL upload path (`useUploadMediaFromBlobURL`) and the video
 *     URL → embed-block upgrade path (`createUpgradedEmbedBlock`) are
 *     omitted. The fork uses `MediaPlaceholder` for both file selection
 *     and URL entry; the editor caller is responsible for resolving any
 *     URL → embed conversion via post-paste filters if needed.
 *   - The custom `Caption` component from
 *     `@wordpress/block-library/src/utils/caption` is replaced by an
 *     inline `RichText` figcaption.
 *   - The `PosterImage` helper from `@wordpress/block-library/src/utils`
 *     is replaced with an inline `MediaUpload` for the poster field.
 *   - The `TracksEditor` toolbar dropdown is omitted (it depends on the
 *     block-library `lock-unlock` private API). The `tracks` attribute
 *     itself still round-trips through save/edit; tracks must be edited
 *     by deserializing markup or by raw attribute editing for now.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import { useRef, useEffect, useState } from '@wordpress/element';
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
    MediaUpload,
    MediaUploadCheck,
    RichText,
    useBlockProps,
    useBlockEditingMode,
} from '@wordpress/block-editor';
import { __, _x } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { video as icon } from '@wordpress/icons';
import { store as noticesStore } from '@wordpress/notices';

import Tracks, { type VideoTrack } from './tracks';

const ALLOWED_MEDIA_TYPES = ['video'] as const;

interface VideoAttributes {
    readonly id?: number;
    readonly src?: string;
    readonly caption?: string;
    readonly autoplay?: boolean;
    readonly controls?: boolean;
    readonly loop?: boolean;
    readonly muted?: boolean;
    readonly poster?: string;
    readonly preload?: string;
    readonly playsInline?: boolean;
    readonly blob?: string;
    readonly tracks?: readonly VideoTrack[];
}

interface MediaItem {
    readonly id?: number;
    readonly url?: string;
    readonly caption?: string;
    readonly image?: { readonly src?: string };
    readonly icon?: string;
}

interface VideoEditProps {
    readonly attributes: VideoAttributes;
    readonly className?: string;
    readonly setAttributes: (next: Partial<VideoAttributes>) => void;
    readonly onReplace?: (block: unknown) => void;
    readonly isSelected: boolean;
    readonly insertBlocksAfter?: (block: unknown) => void;
}

function getAutoplayHelp(checked: boolean): string | null {
    return checked
        ? __('Autoplay may cause usability issues for some users.')
        : null;
}

export default function VideoEdit({
    attributes,
    className,
    setAttributes,
    isSelected: isSingleSelected,
}: VideoEditProps): ReactElement {
    const videoPlayer = useRef<HTMLVideoElement | null>(null);
    const {
        id,
        controls,
        poster,
        src,
        tracks,
        caption,
        autoplay,
        loop,
        muted,
        playsInline,
        preload,
    } = attributes;
    const [temporaryURL, setTemporaryURL] = useState<string | undefined>(
        attributes.blob
    );
    const blockEditingMode = useBlockEditingMode();
    const hasNonContentControls = blockEditingMode === 'default';

    useEffect(() => {
        // Placeholder may be rendered.
        if (videoPlayer.current) {
            videoPlayer.current.load();
        }
    }, [poster]);

    const { createErrorNotice } = useDispatch(noticesStore);

    function toggleAttribute(
        attribute: keyof VideoAttributes
    ): (value: boolean) => void {
        return (newValue: boolean) => {
            // Mirror upstream: toggling autoplay sets muted + playsInline.
            if (attribute === 'autoplay') {
                setAttributes({
                    autoplay: newValue,
                    muted: newValue,
                    playsInline: newValue,
                } as Partial<VideoAttributes>);
                return;
            }
            setAttributes({
                [attribute]: newValue,
            } as Partial<VideoAttributes>);
        };
    }

    function onSelectVideo(media: MediaItem | undefined): void {
        if (!media || !media.url) {
            setAttributes({
                src: undefined,
                id: undefined,
                poster: undefined,
                caption: undefined,
                blob: undefined,
            });
            setTemporaryURL(undefined);
            return;
        }

        setAttributes({
            blob: undefined,
            src: media.url,
            id: media.id,
            poster:
                media.image?.src !== media.icon ? media.image?.src : undefined,
            caption: media.caption,
        });
        setTemporaryURL(undefined);
    }

    function onSelectURL(newSrc: string): void {
        if (newSrc !== src) {
            setAttributes({
                blob: undefined,
                src: newSrc,
                id: undefined,
                poster: undefined,
            });
            setTemporaryURL(undefined);
        }
    }

    function onUploadError(message: string): void {
        createErrorNotice(message, { type: 'snackbar' });
    }

    const classes = clsx(className, {
        'is-transient': !!temporaryURL,
    });

    const blockProps = useBlockProps({ className: classes });

    if (!src && !temporaryURL) {
        return (
            <div {...blockProps}>
                <MediaPlaceholder
                    icon={<BlockIcon icon={icon} />}
                    onSelect={onSelectVideo}
                    onSelectURL={onSelectURL}
                    accept="video/*"
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
                        accept="video/*"
                        onSelect={onSelectVideo}
                        onSelectURL={onSelectURL}
                        onError={onUploadError}
                        onReset={() => onSelectVideo(undefined)}
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
                            controls: true,
                            loop: false,
                            muted: false,
                            playsInline: false,
                            preload: 'metadata',
                            poster: undefined,
                        });
                    }}
                >
                    <ToolsPanelItem
                        label={__('Autoplay')}
                        isShownByDefault
                        hasValue={() => !!autoplay}
                        onDeselect={() =>
                            setAttributes({ autoplay: false, muted: false })
                        }
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
                        label={__('Muted')}
                        isShownByDefault
                        hasValue={() => !!muted}
                        onDeselect={() => setAttributes({ muted: false })}
                    >
                        <ToggleControl
                            label={__('Muted')}
                            onChange={toggleAttribute('muted')}
                            checked={!!muted}
                            disabled={autoplay}
                            help={
                                autoplay
                                    ? __('Muted because of Autoplay.')
                                    : null
                            }
                        />
                    </ToolsPanelItem>
                    <ToolsPanelItem
                        label={__('Playback controls')}
                        isShownByDefault
                        hasValue={() => !controls}
                        onDeselect={() => setAttributes({ controls: true })}
                    >
                        <ToggleControl
                            label={__('Playback controls')}
                            onChange={toggleAttribute('controls')}
                            checked={!!controls}
                        />
                    </ToolsPanelItem>
                    <ToolsPanelItem
                        label={__('Play inline')}
                        isShownByDefault
                        hasValue={() => !!playsInline}
                        onDeselect={() =>
                            setAttributes({ playsInline: false })
                        }
                    >
                        <ToggleControl
                            label={__('Play inline')}
                            onChange={toggleAttribute('playsInline')}
                            checked={!!playsInline}
                            disabled={autoplay}
                            help={
                                autoplay
                                    ? __(
                                          'Play inline enabled because of Autoplay.'
                                      )
                                    : __(
                                          'When enabled, videos will play directly within the webpage on mobile browsers, instead of opening in a fullscreen player.'
                                      )
                            }
                        />
                    </ToolsPanelItem>
                    <ToolsPanelItem
                        label={__('Preload')}
                        isShownByDefault
                        hasValue={() => preload !== 'metadata'}
                        onDeselect={() =>
                            setAttributes({ preload: 'metadata' })
                        }
                    >
                        <SelectControl
                            __next40pxDefaultSize
                            label={__('Preload')}
                            value={preload ?? 'metadata'}
                            onChange={(value: string) =>
                                setAttributes({ preload: value })
                            }
                            options={[
                                { value: 'auto', label: __('Auto') },
                                {
                                    value: 'metadata',
                                    label: __('Metadata'),
                                },
                                {
                                    value: 'none',
                                    label: _x('None', 'Preload value'),
                                },
                            ]}
                            hideCancelButton
                        />
                    </ToolsPanelItem>
                    <ToolsPanelItem
                        label={__('Poster image')}
                        isShownByDefault
                        hasValue={() => !!poster}
                        onDeselect={() =>
                            setAttributes({ poster: undefined })
                        }
                    >
                        <MediaUploadCheck>
                            <MediaUpload
                                title={__('Poster image')}
                                onSelect={(image: MediaItem) =>
                                    setAttributes({ poster: image?.url })
                                }
                                allowedTypes={['image']}
                                render={({ open }: { open: () => void }) => (
                                    <button
                                        type="button"
                                        onClick={open}
                                        className="components-button is-secondary"
                                    >
                                        {poster
                                            ? __('Replace poster image')
                                            : __('Select poster image')}
                                    </button>
                                )}
                            />
                        </MediaUploadCheck>
                    </ToolsPanelItem>
                </ToolsPanel>
            </InspectorControls>
            <figure {...blockProps}>
                <Disabled isDisabled={!isSingleSelected}>
                    <video
                        controls={controls}
                        poster={poster}
                        src={src || temporaryURL}
                        ref={videoPlayer}
                    >
                        <Tracks tracks={tracks} />
                    </video>
                </Disabled>
                {(isSingleSelected && hasNonContentControls) ||
                !RichText.isEmpty(caption ?? '') ? (
                    <RichText
                        identifier="caption"
                        tagName="figcaption"
                        aria-label={__('Video caption text')}
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
