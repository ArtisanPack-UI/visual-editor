import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import type { IconDefinition } from '@fortawesome/fontawesome-svg-core';

export interface IconProps {
    icon: IconDefinition;
    className?: string;
    title?: string;
}

export function Icon({ icon, className, title }: IconProps) {
    const hasTitle = typeof title === 'string' && title.length > 0;

    return (
        <FontAwesomeIcon
            icon={icon}
            className={className}
            title={title}
            role={hasTitle ? 'img' : undefined}
            aria-hidden={hasTitle ? undefined : true}
        />
    );
}
