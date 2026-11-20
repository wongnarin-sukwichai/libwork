import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

// สถานะและการสื่อสารของจอ kiosk: token ของเครื่อง, เวลาเซิร์ฟเวอร์, คิวออฟไลน์, ตรวจเน็ตมหาวิทยาลัย
const TOKEN_KEY = 'libwork.kiosk.token';
const QUEUE_KEY = 'libwork.kiosk.queue';

const store = {
    get(key, fallback) {
        try {
            const v = localStorage.getItem(key);
            return v === null ? fallback : JSON.parse(v);
        } catch {
            return fallback;
        }
    },
    set(key, value) {
        try {
            if (value === null) localStorage.removeItem(key);
            else localStorage.setItem(key, JSON.stringify(value));
        } catch {
            /* พื้นที่เต็มหรือถูกปิดกั้น: ทำงานต่อโดยไม่มีคิว */
        }
    },
};

// crypto.randomUUID ใช้ได้เฉพาะ https/localhost จึงมีสำรอง
const uuid = () =>
    globalThis.crypto?.randomUUID?.() ??
    'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });

async function request(method, path, token, body, timeoutMs = 8000) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    try {
        const res = await fetch(path, {
            method,
            signal: controller.signal,
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(token ? { 'X-Kiosk-Token': token } : {}) },
            body: body ? JSON.stringify(body) : undefined,
        });
        const data = await res.json().catch(() => ({}));
        return { status: res.status, data };
    } finally {
        clearTimeout(timer);
    }
}

export function useKiosk() {
    const token = ref(store.get(TOKEN_KEY, null));
    const device = ref('');
    const serverOnline = ref(true);
    const internetOk = ref(true);
    const offset = ref(0); // เวลาเซิร์ฟเวอร์ - เวลาเครื่อง (ms)
    const now = ref(new Date());
    const today = ref('');
    const recent = ref([]);
    const enabled = ref(true);
    const queue = ref(store.get(QUEUE_KEY, []));

    const status = computed(() => {
        if (!serverOnline.value) return 'offline';
        if (!internetOk.value) return 'auth';
        return 'ok';
    });

    const saveQueue = () => store.set(QUEUE_KEY, queue.value);
    const serverNow = () => new Date(Date.now() + offset.value);

    async function register(code) {
        const { status: s, data } = await request('POST', '/kiosk/api/register', null, { code });
        if (s !== 200) throw new Error(data.message ?? 'ลงทะเบียนไม่สำเร็จ');
        token.value = data.token;
        device.value = data.name;
        store.set(TOKEN_KEY, data.token);
        await refresh();
    }

    function forget() {
        token.value = null;
        store.set(TOKEN_KEY, null);
    }

    async function refresh() {
        if (!token.value) return;
        try {
            const sent = Date.now();
            const { status: s, data } = await request('GET', '/kiosk/api/status', token.value);
            if (s === 401) return forget();
            if (s !== 200) throw new Error();
            serverOnline.value = true;
            offset.value = new Date(data.server_time).getTime() - (sent + Date.now()) / 2;
            device.value = data.device;
            today.value = data.today;
            recent.value = data.recent;
            enabled.value = data.enabled;
            flush();
        } catch {
            serverOnline.value = false;
        }
    }

    // เน็ตมหาวิทยาลัยยังไม่ authen: เซิร์ฟเวอร์ภายในเข้าได้ แต่เว็บภายนอกไม่ได้
    async function checkInternet() {
        try {
            const controller = new AbortController();
            const timer = setTimeout(() => controller.abort(), 5000);
            await fetch('https://www.gstatic.com/generate_204', { mode: 'no-cors', cache: 'no-store', signal: controller.signal });
            clearTimeout(timer);
            internetOk.value = true;
        } catch {
            internetOk.value = false;
        }
    }

    let flushing = false;
    async function flush() {
        if (flushing || !queue.value.length || !token.value) return;
        flushing = true;
        try {
            while (queue.value.length) {
                const item = queue.value[0];
                const { status: s } = await request('POST', '/kiosk/api/punch', token.value, item, 15000);
                if (s === 401 || s >= 500 || s === 429) break; // ลองใหม่รอบหน้า
                queue.value.shift(); // 200 หรือ 422 = เซิร์ฟเวอร์ตัดสินแล้ว
                saveQueue();
            }
        } catch {
            serverOnline.value = false;
        } finally {
            flushing = false;
        }
    }

    /** ลงเวลา: ส่งทันทีถ้าออนไลน์ ถ้าส่งไม่ได้เก็บเข้าคิว · คืน { ok, queued, message, detail, name } */
    async function punch(employeeCode, action, photo) {
        const payload = { employee_code: employeeCode, action, photo, client_uuid: uuid(), punched_at: serverNow().toISOString(), offline: false };
        if (serverOnline.value) {
            try {
                const { status: s, data } = await request('POST', '/kiosk/api/punch', token.value, payload);
                if (s === 401) {
                    forget();
                    return { ok: false, message: 'เครื่องนี้ถูกยกเลิกการลงทะเบียน' };
                }
                if (s === 200 || s === 422 || s === 423) {
                    refresh();
                    return { ok: !!data.ok, message: data.message, detail: data.detail, name: data.name };
                }
            } catch {
                serverOnline.value = false;
            }
        }
        queue.value.push({ ...payload, offline: true });
        saveQueue();
        return { ok: true, queued: true, message: 'บันทึกไว้ในเครื่องแล้ว', detail: 'ระบบจะส่งข้อมูลเมื่อเชื่อมต่อได้' };
    }

    let clock;
    let poll;
    onMounted(() => {
        clock = setInterval(() => (now.value = serverNow()), 1000);
        poll = setInterval(() => {
            refresh();
            checkInternet();
        }, 30000);
        refresh();
        checkInternet();
    });
    onBeforeUnmount(() => {
        clearInterval(clock);
        clearInterval(poll);
    });

    return { token, device, status, now, today, recent, enabled, queue, register, punch };
}
