/**
 * Media bridge type contracts.
 *
 * These types describe the narrow surface the visual editor needs from the
 * host application's media picker and uploader. They intentionally mirror
 * the shapes exposed by `artisanpack-ui/media-library` without importing
 * from it, so the visual editor stays a standalone package.
 *
 * See umbrella issue #309 and M4 (#314) for background on the adoption of
 * `MediaModal` as the bridge component.
 */

import type { ComponentType, ReactElement, ReactNode } from 'react';

/**
 * High-level media categories recognised by the bridge. Matches the
 * `MediaType` union exported from `artisanpack-ui/media-library`.
 */
export type BridgeMediaType = 'image' | 'video' | 'audio' | 'document';

/**
 * Structural subset of the `Media` resource returned by the host's media
 * API. Declared as an interface so structurally-compatible objects — in
 * particular `artisanpack-ui/media-library`'s `Media` type — can be passed
 * without additional conversion.
 */
export interface BridgeMedia {
    id: number;
    url: string;
    mime_type: string;
    title?: string | null;
    file_name?: string;
    alt_text?: string | null;
    caption?: string | null;
    width?: number | null;
    height?: number | null;
    is_image?: boolean;
    is_video?: boolean;
    is_audio?: boolean;
    is_document?: boolean;
    metadata?: Record<string, unknown> | null;
}

/**
 * Props the registered bridge component receives. Matches the public API
 * of `MediaModal` from `artisanpack-ui/media-library`.
 */
export interface MediaBridgeComponentProps {
    open: boolean;
    onClose: () => void;
    onSelect: (media: BridgeMedia[], context: string) => void;
    multiSelect?: boolean;
    maxSelections?: number;
    allowedTypes?: BridgeMediaType[];
    context?: string;
    title?: string;
}

/**
 * React component type for the registered media bridge.
 */
export type MediaBridgeComponent = ComponentType<MediaBridgeComponentProps>;

/**
 * Uploader contract used by `BlockEditorProvider`'s `settings.mediaUpload`.
 * The host supplies an implementation that posts to its own media API and
 * resolves with the stored media record. Matches the call signature of
 * `uploadMedia` exported from `artisanpack-ui/media-library`.
 */
export type MediaUploader = (
    file: File,
    metadata?: Record<string, unknown>,
    onProgress?: (percent: number) => void
) => Promise<{ data: BridgeMedia } | BridgeMedia>;

/**
 * Image size variant produced by the host for a single media item. Matches
 * Gutenberg's `attachment.sizes[size]` shape.
 */
export interface GutenbergMediaSize {
    url: string;
    width?: number;
    height?: number;
}

/**
 * Media record shape expected by Gutenberg core blocks (core/image,
 * core/gallery, core/video, etc.). Fields derive from the WordPress
 * attachment REST resource; only the keys core blocks actually read are
 * modelled here.
 */
export interface GutenbergMedia {
    id: number;
    url: string;
    alt: string;
    caption: string;
    mime: string;
    media_type?: 'image' | 'video' | 'audio' | 'file';
    width?: number;
    height?: number;
    link?: string;
    filename?: string;
    sizes?: Record<string, GutenbergMediaSize>;
}

/**
 * Gutenberg `MediaUpload` slot-fill props. The filter receives components
 * that accept this shape; our replacement implements it and fans the call
 * out to the registered bridge component.
 */
export interface GutenbergMediaUploadProps {
    allowedTypes?: string[];
    multiple?: boolean | 'add';
    value?: number | number[];
    onSelect?: (media: GutenbergMedia | GutenbergMedia[]) => void;
    render?: (args: { open: () => void }) => ReactElement | null;
    children?: ReactNode;
    title?: string;
    gallery?: boolean;
    modalClass?: string;
}

/**
 * Options passed to the `settings.mediaUpload` callback when Gutenberg
 * needs to upload files directly (for example drag-and-drop onto the
 * canvas).
 */
export interface MediaUploadSettingsOptions {
    allowedTypes?: string[];
    filesList: File[] | FileList;
    onFileChange?: (media: GutenbergMedia[]) => void;
    onError?: (message: string) => void;
    maxUploadFileSize?: number;
    additionalData?: Record<string, unknown>;
}
