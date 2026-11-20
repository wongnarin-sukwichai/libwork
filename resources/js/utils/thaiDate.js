export const THAI_MONTHS = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
export const THAI_MONTHS_SHORT = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
export const THAI_DAYS = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
export const THAI_DAYS_SHORT = ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'];

const toDate = (value) => (value instanceof Date ? value : new Date(`${value}T00:00:00`));

/** 20 พฤศจิกายน 2569 */
export function thaiDate(value, { short = false, weekday = false } = {}) {
    if (!value) return '—';
    const d = toDate(value);
    const month = (short ? THAI_MONTHS_SHORT : THAI_MONTHS)[d.getMonth()];
    const text = `${d.getDate()} ${month} ${d.getFullYear() + 543}`;
    return weekday ? `${(short ? THAI_DAYS_SHORT : THAI_DAYS)[d.getDay()]} ${text}` : text;
}

/** YYYY-MM-DD ตามเวลาเครื่อง (ไม่แปลงเป็น UTC) */
export function isoDate(d = new Date()) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}
