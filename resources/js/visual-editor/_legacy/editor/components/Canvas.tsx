import type { ReactNode } from 'react';

export interface CanvasProps {
    children: ReactNode;
    className?: string;
    label?: string;
}

export function Canvas({ children, className, label = 'Editor canvas' }: CanvasProps) {
    return (
        <div
            className={['ve-canvas', className].filter(Boolean).join(' ')}
            data-ve-canvas=""
            role="region"
            aria-label={label}
        >
            {children}
        </div>
    );
}
