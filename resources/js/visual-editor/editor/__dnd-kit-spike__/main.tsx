import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { DndKitSpike } from './DndKitSpike';
import './spike.css';

const container = document.getElementById('spike-root');

if (!container) {
    throw new Error('dnd-kit spike: missing #spike-root mount point.');
}

createRoot(container).render(
    <StrictMode>
        <DndKitSpike />
    </StrictMode>
);
