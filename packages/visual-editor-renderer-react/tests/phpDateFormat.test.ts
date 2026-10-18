import { describe, expect, it } from 'vitest';
import { phpDateFormat } from '../src/support/phpDateFormat';
import { phpDateFormat as vuePhpDateFormat } from '../../visual-editor-renderer-vue/src/support/phpDateFormat';
import { phpDateFormat as editorPhpDateFormat } from '../../../resources/js/visual-editor/blocks/_shared/php-date-format';

// Expected values are Carbon `translatedFormat()` output in `en` (#941).
describe.each([
    ['react', phpDateFormat],
    ['vue', vuePhpDateFormat],
    ['editor', editorPhpDateFormat],
])('phpDateFormat (%s)', (_renderer, format) => {
    it.each([
        ['M j', 'Oct 2'],
        ['F j, Y', 'October 2, 2026'],
        ['D, d M y', 'Fri, 02 Oct 26'],
        ['l jS \\of F', 'Friday 2nd of October'],
        ['Y-m-d H:i:s', '2026-10-02 13:05:09'],
        ['g:i A', '1:05 PM'],
        ['N w z t L', '5 5 274 31 0'],
        ['W', '40'],
    ])('formats %s', (pattern, expected) => {
        expect(format('2026-10-02T13:05:09-05:00', pattern)).toBe(expected);
    });

    it('gives ISO-8601 week numbers across a year boundary (PHP W)', () => {
        expect(['2026-01-01', '2027-01-01', '2027-01-04'].map((date) => format(date, 'W'))).toEqual(['01', '53', '01']);
    });

    it('formats the timestamp wall-clock time, not the viewer timezone', () => {
        expect(format('2026-04-20T23:30:00+00:00', 'M j')).toBe('Apr 20');
    });

    it('uses st/nd/rd/th suffixes, with th for 11-13', () => {
        expect(['01', '02', '03', '04', '11', '12', '13', '21', '22', '23'].map((day) =>
            format(`2026-01-${day}`, 'jS'),
        )).toEqual(['1st', '2nd', '3rd', '4th', '11th', '12th', '13th', '21st', '22nd', '23rd']);
    });

    it('returns an empty string for no date and the raw value when unparseable', () => {
        expect(format('', 'M j')).toBe('');
        expect(format(null, 'M j')).toBe('');
        expect(format('not a date', 'M j')).toBe('not a date');
    });
});
