/**
 * PHP `number_format( $value, $places, '.', '' )`, byte for byte.
 *
 * PHP rounds the number's shortest decimal representation half away from
 * zero (`0.11115` → `0.1112`), where `Number.prototype.toFixed()` rounds the
 * underlying binary value (`0.11115` → `0.1111`). Twins that format numbers
 * for Blade-parity output (duotone channels, `cssNumber()`) go through this
 * helper so the three renderers agree on ties.
 *
 * @since 1.15.0
 */

/**
 * Format `value` with exactly `places` decimals and no thousands separator.
 */
export function phpNumberFormat(value: number, places: number): string {
    if (!Number.isFinite(value)) {
        return '0';
    }

    const negative = value < 0;
    const [integerPart, fractionPart] = plainDecimal(Math.abs(value));
    let digits = integerPart + fractionPart.slice(0, places).padEnd(places, '0');

    if (fractionPart.length > places && fractionPart.charCodeAt(places) - 48 >= 5) {
        digits = (BigInt(digits) + 1n).toString().padStart(places + 1, '0');
    }

    const whole = digits.slice(0, digits.length - places).replace(/^0+(?=\d)/, '');
    const formatted = places > 0 ? `${whole}.${digits.slice(digits.length - places)}` : whole;

    return negative && /[1-9]/.test(formatted) ? `-${formatted}` : formatted;
}

/**
 * Split a non-negative number's shortest round-trip representation into
 * its integer and fraction digits, expanding exponent notation.
 */
function plainDecimal(value: number): [string, string] {
    const text = String(value);
    const match = /^(\d+)(?:\.(\d+))?e([+-]\d+)$/.exec(text);

    if (match === null) {
        const [integerPart, fractionPart = ''] = text.split('.');

        return [integerPart, fractionPart];
    }

    const significand = match[1] + (match[2] ?? '');
    const point = match[1].length + Number(match[3]);

    if (point <= 0) {
        return ['0', '0'.repeat(-point) + significand];
    }

    if (point >= significand.length) {
        return [significand + '0'.repeat(point - significand.length), ''];
    }

    return [significand.slice(0, point), significand.slice(point)];
}
