/**
 * Inline SVG icons shared between the callout `edit.tsx` and `save.tsx`.
 *
 * Inlining here (rather than pulling @wordpress/icons) guarantees the
 * save output is byte-identical across environments — critical because
 * Gutenberg validates saved markup on load and throws a recovery modal
 * when the editor's render doesn't match what was persisted.
 */

import type { ReactElement } from 'react';

export type CalloutIconName =
    | 'info'
    | 'check'
    | 'warning'
    | 'error'
    | 'lightbulb';

interface CalloutIconProps {
    readonly name: CalloutIconName;
}

function InfoIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            aria-hidden="true"
            focusable="false"
        >
            <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm1 15h-2v-6h2Zm0-8h-2V7h2Z" />
        </svg>
    );
}

function CheckIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            aria-hidden="true"
            focusable="false"
        >
            <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm-1.5 14.5-4-4 1.4-1.4 2.6 2.6 6.6-6.6L17.5 8.5Z" />
        </svg>
    );
}

function WarningIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            aria-hidden="true"
            focusable="false"
        >
            <path d="M12 2 1 21h22Zm1 15h-2v-2h2Zm0-4h-2V9h2Z" />
        </svg>
    );
}

function ErrorIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            aria-hidden="true"
            focusable="false"
        >
            <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm5 13.6L15.6 17 12 13.4 8.4 17 7 15.6 10.6 12 7 8.4 8.4 7 12 10.6 15.6 7 17 8.4 13.4 12Z" />
        </svg>
    );
}

function LightbulbIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            aria-hidden="true"
            focusable="false"
        >
            <path d="M9 21h6v-1H9Zm3-19a7 7 0 0 0-4 12.74V17h8v-2.26A7 7 0 0 0 12 2Zm1 12h-2v-2h2Z" />
        </svg>
    );
}

const REGISTRY: Readonly<Record<CalloutIconName, () => ReactElement>> = {
    info: InfoIcon,
    check: CheckIcon,
    warning: WarningIcon,
    error: ErrorIcon,
    lightbulb: LightbulbIcon,
};

export function CalloutIcon({ name }: CalloutIconProps): ReactElement {
    const Renderer = REGISTRY[name] ?? InfoIcon;
    return <Renderer />;
}

export const CALLOUT_ICON_NAMES: ReadonlyArray<CalloutIconName> =
    Object.keys(REGISTRY) as ReadonlyArray<CalloutIconName>;
