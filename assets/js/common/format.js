export function parseAmount(value) {
    if (value === '' || value == null) {
        return 0;
    }

    return Number(String(value).replace(',', '.').replace(/\s/g, '').replace('€', '')) || 0;
}

export function formatNumber(value, decimals = 2) {
    const number = Number(value);
    const [intPart, frac = ''] = number.toFixed(decimals).split('.');
    const grouped = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    if (decimals === 0) {
        return grouped;
    }

    return `${grouped},${frac.padEnd(decimals, '0')}`;
}

export function formatPrice(value) {
    return formatNumber(value, 2);
}

export function formatDecimal(value) {
    return String(value).replace('.', ',');
}
