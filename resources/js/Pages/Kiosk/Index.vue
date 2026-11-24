<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import { url } from '@/utils/url';
import { useKiosk } from './useKiosk';
import { THAI_DAYS, THAI_MONTHS } from '@/utils/thaiDate';

// จอลงเวลากลาง (แบบ C3): แถบหัวน้ำเงิน พื้นขาว ปุ่มเข้าเหลือง ปุ่มออกดำ การ์ดล่าสุดเทาอ่อน
const props = defineProps({
    enabled: Boolean,
    authUrl: { type: String, default: null },
});

const k = useKiosk();

// ---------- นาฬิกา ----------
const pad = (n) => String(n).padStart(2, '0');
const clockHm = computed(() => `${pad(k.now.value.getHours())}:${pad(k.now.value.getMinutes())}`);
const clockS = computed(() => pad(k.now.value.getSeconds()));
const dateText = computed(() => {
    const d = k.now.value;
    return `วัน${THAI_DAYS[d.getDay()]}ที่ ${d.getDate()} ${THAI_MONTHS[d.getMonth()]} ${d.getFullYear() + 543}`;
});

const statusInfo = computed(() => ({
    ok: { dot: 'bg-emerald-400', title: 'พร้อมใช้งาน', desc: k.device.value },
    auth: { dot: 'bg-yellow-400', title: 'ยังไม่ได้เข้าสู่ระบบอินเทอร์เน็ต', desc: 'ลงเวลาได้ตามปกติ' },
    offline: { dot: 'bg-orange-400', title: 'ออฟไลน์', desc: `ลงเวลาได้ · ข้อมูลรอส่ง ${k.queue.value.length} รายการ` },
})[k.status.value]);

// ---------- กล้อง ----------
const video = ref(null);
const cameraReady = ref(false);
let stream = null;
async function startCamera() {
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480, facingMode: 'user' }, audio: false });
        video.value.srcObject = stream;
        cameraReady.value = true;
    } catch {
        cameraReady.value = false;
    }
}
function capture() {
    if (!cameraReady.value || !video.value?.videoWidth) return null;
    const w = 480;
    const h = Math.round((video.value.videoHeight / video.value.videoWidth) * w);
    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    canvas.getContext('2d').drawImage(video.value, 0, 0, w, h);
    return canvas.toDataURL('image/jpeg', 0.7);
}
onMounted(() => k.token.value && startCamera());
watch(k.token, (t) => t && nextTick(startCamera));
onBeforeUnmount(() => stream?.getTracks().forEach((t) => t.stop()));

// ---------- ลงเวลา ----------
const code = ref('');
const codeInput = ref(null);
const busy = ref(false);
const result = ref(null);
let resultTimer;
const focusCode = () => nextTick(() => codeInput.value?.focus());

async function doPunch(action) {
    if (busy.value) return;
    if (!code.value.trim()) {
        result.value = { ok: false, message: 'กรุณากรอกรหัสบุคลากร' };
        return focusCode();
    }
    busy.value = true;
    try {
        result.value = await k.punch(code.value.trim(), action, capture());
    } finally {
        busy.value = false;
        code.value = '';
        focusCode();
        clearTimeout(resultTimer);
        resultTimer = setTimeout(() => (result.value = null), 6000);
    }
}

// ---------- ลงทะเบียนเครื่อง ----------
const setupCode = ref('');
const setupError = ref('');
const setupBusy = ref(false);
async function registerDevice() {
    setupError.value = '';
    setupBusy.value = true;
    try {
        await k.register(setupCode.value.trim());
    } catch (e) {
        setupError.value = e.message;
    } finally {
        setupBusy.value = false;
    }
}

const actions = [
    { key: 'in', label: 'ลงเวลาเข้า', cls: 'h-[76px] bg-sun-500 text-black text-2xl shadow-sun hover:bg-sun-600' },
    { key: 'out', label: 'ลงเวลาออก', cls: 'h-[76px] bg-black text-white text-2xl shadow-card hover:bg-neutral-800' },
    { key: 'ot_in', label: 'เข้า OT', cls: 'h-[60px] border-2 border-navy-700 bg-white text-navy-700 text-[19px]' },
    { key: 'ot_out', label: 'ออก OT', cls: 'h-[60px] border-2 border-navy-700 bg-white text-navy-700 text-[19px]' },
];
</script>

<template>
    <Head title="จอลงเวลา" />
    <div class="flex min-h-screen flex-col bg-white text-ink">
        <header class="flex flex-wrap items-center justify-between gap-3 bg-navy-700 px-6 py-4 text-white shadow-[0_4px_16px_rgba(15,30,58,0.18)] lg:px-10">
            <div class="flex items-center gap-3.5">
                <img :src="url('/img/logos/library.png')" alt="MSU Library" class="h-12 w-auto">
                <div class="border-l border-white/25 pl-3.5">
                    <div class="text-[21px] font-bold">ระบบลงเวลาปฏิบัติงาน</div>
                    <div class="text-sm text-navy-300">{{ $page.props.app.org }}</div>
                </div>
            </div>
            <div v-if="k.token.value" class="flex flex-wrap items-center gap-2.5">
                <div class="flex min-h-11 items-center gap-2.5 rounded-xl bg-white/12 px-4 text-[15px]">
                    <span class="h-3 w-3 shrink-0 rounded-full" :class="statusInfo.dot" />
                    <span><b>{{ statusInfo.title }}</b> · {{ statusInfo.desc }}</span>
                </div>
                <a v-if="k.status.value === 'auth' && props.authUrl" :href="props.authUrl" target="_blank" rel="noopener" class="flex h-11 items-center rounded-xl bg-sun-500 px-4 font-bold text-black">กดเพื่อเข้าสู่ระบบอินเทอร์เน็ต</a>
            </div>
        </header>

        <!-- ยังไม่ลงทะเบียนเครื่อง -->
        <div v-if="!k.token.value" class="flex flex-1 items-center justify-center p-6">
            <form class="card w-full max-w-md space-y-4 p-7" @submit.prevent="registerDevice">
                <h1 class="text-2xl font-bold">ลงทะเบียนเครื่องจอกลาง</h1>
                <p class="text-muted">ให้ผู้ดูแลระบบเพิ่มเครื่องที่ ตั้งค่า › เครื่อง kiosk แล้วนำรหัสลงทะเบียนมากรอกที่นี่ (ทำครั้งเดียวต่อเครื่อง)</p>
                <div>
                    <label for="setup" class="label">รหัสลงทะเบียน</label>
                    <input id="setup" v-model="setupCode" class="input h-14 text-center text-2xl uppercase tracking-[0.3em]" placeholder="XXXX-XXXX" required autocomplete="off">
                </div>
                <p v-if="setupError" role="alert" class="rounded-xl bg-red-50 px-4 py-3 text-red-800">{{ setupError }}</p>
                <button type="submit" class="btn-navy h-12 w-full" :disabled="setupBusy">ลงทะเบียน</button>
            </form>
        </div>

        <template v-else>
            <main class="grid flex-1 gap-9 px-6 py-6 lg:grid-cols-[minmax(0,600px)_minmax(0,1fr)] lg:px-10">
                <div class="flex flex-col gap-4">
                    <div class="relative flex h-[420px] items-center justify-center overflow-hidden rounded-[20px] border border-line bg-soft">
                        <video ref="video" autoplay muted playsinline class="absolute inset-0 h-full w-full -scale-x-100 object-cover" :class="{ hidden: !cameraReady }" />
                        <div class="relative h-[280px] w-[220px] rounded-[50%] border-[3px] border-dashed border-navy-400/80" />
                        <div class="absolute left-4 top-4 flex items-center gap-2 rounded-full border border-line bg-white px-3 py-1.5 text-sm">
                            <span class="h-2 w-2 rounded-full" :class="cameraReady ? 'bg-red-600' : 'bg-navy-400'" />{{ cameraReady ? 'กล้องพร้อม' : 'ไม่พบกล้อง · ลงเวลาได้แต่ไม่มีรูป' }}
                        </div>
                        <div class="absolute bottom-4 rounded-full bg-white/85 px-3 py-1 text-[15px] text-muted">กรุณาให้ใบหน้าอยู่ในกรอบ</div>
                    </div>

                    <div
                        v-if="result"
                        role="status"
                        class="flex items-center gap-3.5 rounded-2xl px-5 py-4"
                        :class="result.ok ? (result.queued ? 'bg-orange-50 text-orange-900' : 'bg-green-50 text-green-900') : 'bg-red-50 text-red-900'"
                    >
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="10" />
                            <path v-if="result.ok" d="M7 12.5l3.2 3L17 9" />
                            <path v-else d="M15 9l-6 6M9 9l6 6" />
                        </svg>
                        <div>
                            <div class="text-lg font-bold"><span v-if="result.name">{{ result.name }} — </span>{{ result.message }}</div>
                            <div v-if="result.detail" class="text-sm">{{ result.detail }}</div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-[18px]">
                    <div>
                        <div class="text-xl text-muted">{{ dateText }}</div>
                        <div class="text-[96px] font-bold leading-[1.05] tracking-wide" aria-live="off">{{ clockHm }}<span class="text-sun-500">:{{ clockS }}</span></div>
                        <div v-if="k.today.value" class="text-muted">{{ k.today.value }}</div>
                    </div>
                    <form class="flex flex-col gap-2" @submit.prevent="doPunch('in')">
                        <label for="code" class="text-base text-muted">รหัสบุคลากร</label>
                        <input
                            id="code"
                            ref="codeInput"
                            v-model="code"
                            inputmode="numeric"
                            autocomplete="off"
                            autofocus
                            class="h-16 rounded-[14px] border-2 border-navy-700 bg-white px-5 text-[32px] tracking-[0.15em] text-ink outline-none focus:ring-4 focus:ring-navy-700/15"
                        >
                    </form>
                    <div class="grid grid-cols-2 gap-3">
                        <button
                            v-for="a in actions"
                            :key="a.key"
                            type="button"
                            class="rounded-[14px] font-bold transition disabled:opacity-60"
                            :class="a.cls"
                            :disabled="busy || !props.enabled || !k.enabled.value"
                            @click="doPunch(a.key)"
                        >{{ a.label }}</button>
                    </div>
                    <p v-if="!props.enabled || !k.enabled.value" class="rounded-xl bg-red-50 px-4 py-3 text-red-800">ปิดการลงเวลาที่จอกลางชั่วคราว</p>
                    <p v-else class="text-sm text-muted">กดปุ่มแล้วระบบจะถ่ายรูปอัตโนมัติ · ปุ่ม OT ใช้ได้เฉพาะผู้มีสิทธิ์ OT</p>
                </div>
            </main>

            <footer class="flex flex-wrap items-center gap-3 px-6 pb-6 lg:px-10">
                <div class="text-sm text-muted">ล่าสุด</div>
                <div v-for="(r, i) in k.recent.value" :key="i" class="flex items-center gap-2.5 rounded-xl bg-[#F2F4F7] px-3.5 py-2 text-[#1F2933]">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-sun-500 text-sm font-bold text-black">{{ r.initials }}</div>
                    <div class="text-sm leading-tight"><div>{{ r.name }}</div><div class="text-[#52606D]">{{ r.text }}</div></div>
                </div>
            </footer>
        </template>
    </div>
</template>
