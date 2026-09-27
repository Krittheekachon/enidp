const BUDDHIST_YEAR_OFFSET = 543;

export const toBuddhistDate = (isoDate) => {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(isoDate || '');
    if (!match) return '';

    return `${match[3]}/${match[2]}/${Number(match[1]) + BUDDHIST_YEAR_OFFSET}`;
};

export const toIsoDate = (buddhistDate) => {
    const normalized = String(buddhistDate || '').trim().replace(/[๐-๙]/g, (digit) => String(digit.charCodeAt(0) - 0x0E50));
    const match = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec(normalized);
    if (!match) return null;

    const day = Number(match[1]);
    const month = Number(match[2]);
    const buddhistYear = Number(match[3]);
    const year = buddhistYear - BUDDHIST_YEAR_OFFSET;
    if (buddhistYear < 2500 || buddhistYear > 2700 || month < 1 || month > 12) return null;

    const date = new Date(Date.UTC(year, month - 1, day));
    if (date.getUTCFullYear() !== year || date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day) return null;

    return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
};
