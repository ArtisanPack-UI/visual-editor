import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import './index.css';

const container = document.getElementById('editor-spike-root');

if (!container) {
    throw new Error('editor-spike: missing #editor-spike-root mount point');
}

createRoot(container).render(
    <StrictMode>
        <App />
    </StrictMode>
);
