/**
 * Section-portal helper — H7 (#432).
 *
 * Section orchestrators (styles, navigation, patterns) are loaded
 * lazily via `React.lazy` so the heavy chunks stay out of the initial
 * site-editor boot bundle. The shell exposes stable navigator / canvas
 * / inspector / overlay mount points and the lazy section component
 * portals its three views into those mount points instead of returning
 * them as separate slot elements (a hook can't be lazy-loaded; a
 * component can).
 *
 * `slot` is null on the first render before the shell's callback-ref
 * has populated it. In that case we render nothing — once the ref
 * populates and the section re-renders, the portal lights up.
 */

import { type ReactNode } from 'react';
import { createPortal } from 'react-dom';

export interface SectionPortalProps {
    slot: HTMLElement | null;
    children: ReactNode;
}

export function SectionPortal(props: SectionPortalProps): ReactNode {
    const { slot, children } = props;

    if (slot === null) {
        return null;
    }

    return createPortal(children, slot);
}
