/**
 * Video — track list renderer.
 *
 * Ported from `@wordpress/block-library/src/video/tracks.js` (v9.43.0).
 * Renders a `<track>` element for each entry in `attributes.tracks`.
 * Used by both `edit.tsx` and `save.tsx` so the editor preview and the
 * saved markup stay in lockstep.
 */

import type { ReactElement } from 'react';

export interface VideoTrack {
    readonly id?: number | string;
    readonly src?: string;
    readonly label?: string;
    readonly srcLang?: string;
    readonly kind?: string;
    readonly default?: boolean;
}

interface TracksProps {
    readonly tracks?: readonly VideoTrack[];
}

export default function Tracks({ tracks = [] }: TracksProps): ReactElement[] {
    return tracks.map((track) => {
        const { id, ...trackAttrs } = track;
        return <track key={id ?? trackAttrs.src} {...trackAttrs} />;
    });
}
