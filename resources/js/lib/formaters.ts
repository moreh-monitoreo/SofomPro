export function currency(value: string) {
    if (!value) {
        return '-';
    }
    try {
        const formatValue = parseFloat(value);
        const formated = new Intl.NumberFormat('es-MX', {
            minimumFractionDigits: 2,
        }).format(formatValue);
        return formated;
    } catch (error) {
        return '-';
    }
}

export function round(value: string) {
    if (!value) {
        return '-';
    }
    return Math.round(parseFloat(value));
}
