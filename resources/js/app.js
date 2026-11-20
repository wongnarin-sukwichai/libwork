import { createApp, h } from 'vue';
import { createInertiaApp, http } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { url, urlAbsolute } from '@/utils/url';

// ติดตั้งใต้ sub-path: เติม prefix ให้ทุกการเรียกของ Inertia และ fetch() ที่ใช้ path ขึ้นต้นด้วย "/"
http.onRequest((config) => ({ ...config, url: urlAbsolute(config.url) }));
const nativeFetch = window.fetch.bind(window);
window.fetch = (input, init) => nativeFetch(typeof input === 'string' ? url(input) : input, init);

const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });

// หน้าที่ไม่ต้อง login ใช้ PublicLayout (แถบหัวสีน้ำเงิน) ที่เหลือใช้ AppLayout (sidebar)
const publicPages = ['Home', 'Auth/Login'];

createInertiaApp({
    title: (title) => (title ? `${title} · ระบบลงเวลาปฏิบัติงาน` : 'ระบบลงเวลาปฏิบัติงาน'),
    resolve: (name) => {
        const page = pages[`./Pages/${name}.vue`];
        if (!page) throw new Error(`ไม่พบหน้า: ${name}`);
        if (name.startsWith('Kiosk/')) page.default.layout = undefined; // จอ kiosk เต็มจอ ไม่มี layout
        else page.default.layout ??= publicPages.includes(name) ? PublicLayout : AppLayout;
        return page;
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        app.config.globalProperties.$url = url;
        app.use(plugin).mount(el);
    },
    progress: { color: '#F5B700' },
});
