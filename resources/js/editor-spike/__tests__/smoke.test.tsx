import { render, screen } from '@testing-library/react';
import App from '../App';

describe('editor-spike App', () => {
    it('renders the spike heading', () => {
        render(<App />);
        expect(
            screen.getByRole('heading', { name: /visual editor spike/i })
        ).toBeInTheDocument();
    });
});
