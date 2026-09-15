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

export function toIsoDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

export function isoToSlDate(iso) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso ?? '').trim());
    if (!match) {
        return '';
    }

    return `${Number(match[3])}. ${Number(match[2])}. ${match[1]}`;
}

export function slDateToIso(value) {
    const raw = String(value ?? '').trim();
    if (raw === '') {
        return '';
    }

    const iso = /^(\d{4})-(\d{2})-(\d{2})$/.exec(raw);
    if (iso) {
        return raw;
    }

    const slovenian = /^(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})$/.exec(raw);
    if (!slovenian) {
        return '';
    }

    const day = Number(slovenian[1]);
    const month = Number(slovenian[2]);
    const year = Number(slovenian[3]);
    const date = new Date(year, month - 1, day);
    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
        return '';
    }

    return toIsoDate(date);
}
