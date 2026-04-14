import type { ReactNode } from 'react';

export interface CanvasProps {
    children: ReactNode;
    className?: string;
}

export function Canvas({ children, className }: CanvasProps) {
    return (
        <div
            className={['ve-canvas', className].filter(Boolean).join(' ')}
            data-ve-canvas=""
            role="region"
            aria-label="Editor canvas"
        >
            {children}
        </div>
    );
}
