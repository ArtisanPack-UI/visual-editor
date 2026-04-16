import type { IconDefinition } from '@fortawesome/fontawesome-svg-core';
import {
    faParagraph,
    faHeading,
    faList,
    faQuoteLeft,
    faCode,
    faTerminal,
    faCube,
} from '@fortawesome/free-solid-svg-icons';

const iconMap: Record<string, IconDefinition> = {
    paragraph: faParagraph,
    heading: faHeading,
    list: faList,
    'quote-left': faQuoteLeft,
    code: faCode,
    terminal: faTerminal,
};

/**
 * Resolves a block icon name (from block.json `icon` field) to a
 * FontAwesome icon definition. Falls back to a generic cube icon.
 */
export function resolveBlockIcon(iconName: string | undefined): IconDefinition {
    if (!iconName) {
        return faCube;
    }
    return iconMap[iconName] ?? faCube;
}
