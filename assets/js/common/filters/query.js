export function unixStartOfDay(value) {
    if (!value) {
        return undefined;
    }
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) {
        return undefined;
    }
    return Math.floor(date.getTime() / 1000);
}

export function unixEndOfDay(value) {
    if (!value) {
        return undefined;
    }
    const date = new Date(`${value}T23:59:59`);
    if (Number.isNaN(date.getTime())) {
        return undefined;
    }
    return Math.floor(date.getTime() / 1000);
}

export function isoDateFromUnix(unix) {
    if (!unix) {
        return '';
    }
    const date = new Date(Number(unix) * 1000);
    if (Number.isNaN(date.getTime())) {
        return '';
    }
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

export function withQuery(url, params) {
    const parsed = new URL(url, window.location.origin);
    Object.entries(params).forEach(([key, value]) => {
        if (value === undefined || value === '') {
            parsed.searchParams.delete(key);
        } else {
            parsed.searchParams.set(key, value);
        }
    });
    parsed.searchParams.delete('page');
    return parsed.pathname + parsed.search;
}
