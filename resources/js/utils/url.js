// ระบบอาจติดตั้งใต้ sub-path เช่น https://golden.msu.ac.th/libwork
// เซิร์ฟเวอร์เขียน path นั้นไว้ใน <meta name="base-path"> (ว่างเมื่อรันที่ root)
// ทุก URL ที่ขึ้นต้นด้วย "/" ในโค้ดจะผ่าน url() เพื่อเติม prefix ให้ เรียกซ้ำได้ไม่เพี้ยน
export const basePath = (document.querySelector('meta[name="base-path"]')?.content ?? '').replace(/\/+$/, '');

const hasBase = (path) => path === basePath || path.startsWith(`${basePath}/`) || path.startsWith(`${basePath}?`);

export function url(path) {
    if (!basePath || typeof path !== 'string' || !path.startsWith('/') || path.startsWith('//') || hasBase(path)) {
        return path;
    }
    return basePath + path;
}

/** เหมือน url() แต่รับ URL เต็มบน origin เดียวกัน (Inertia ส่ง URL เต็ม) */
export function urlAbsolute(href) {
    if (!basePath || typeof href !== 'string') return href;
    try {
        const parsed = new URL(href, window.location.origin);
        if (parsed.origin !== window.location.origin || hasBase(parsed.pathname)) return href;
        parsed.pathname = basePath + parsed.pathname;
        return /^https?:/i.test(href) ? parsed.href : parsed.pathname + parsed.search + parsed.hash;
    } catch {
        return href;
    }
}

/** ตัด base path ออก ใช้เทียบกับ path ของแอป เช่น "/staff" */
export function stripBase(path) {
    if (!basePath || typeof path !== 'string' || !hasBase(path)) return path;
    return path.slice(basePath.length) || '/';
}
