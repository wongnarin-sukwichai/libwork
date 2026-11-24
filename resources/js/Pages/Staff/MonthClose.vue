<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import Modal from '@/Components/Modal.vue';
import Pager from '@/Components/Pager.vue';
import { thaiDate } from '@/utils/thaiDate';

// ตรวจสอบรายการ (เดิม "ตรวจสอบ/ปิดงวด" หน้า 8): จัดการรายการผิดปกติให้ครบก่อนปิด · ปิดแล้วข้อมูลเดือนนั้นถูกล็อก
const props = defineProps({ month: String, title: String, closed: Object, monthEnded: Boolean, items: Array, history: Array });
const page = usePage();
const isAdmin = computed(() => page.props.auth.user.role === 'admin');

const blocking = computed(() => props.items.filter((i) => i.blocking));
const done = computed(() => blocking.value.filter((i) => i.resolved).length);
const left = computed(() => blocking.value.length - done.value);
const pct = computed(() => (blocking.value.length ? Math.round((done.value / blocking.value.length) * 100) : 100));
const kinds = [
    { key: 'no_out', label: 'ไม่ได้ลงเวลาออก', cls: 'bg-red-50 text-red-900' },
    { key: 'absent', label: 'ไม่มาโดยไม่มีใบลา', cls: 'bg-red-700 text-white' },
    { key: 'clash', label: 'ข้อมูลขัดกัน', cls: 'bg-violet-50 text-violet-900' },
    { key: 'offline', label: 'บันทึกขณะออฟไลน์', cls: 'border border-line bg-white' },
    { key: 'late', label: 'สาย / ออกก่อน (เพื่อทราบ)', cls: 'border border-line bg-white', also: ['early'] },
];
const count = (k) => props.items.filter((i) => [k.key, ...(k.also ?? [])].includes(i.kind));
const pill = { no_out: 'bg-red-50 text-red-800', no_in: 'bg-red-50 text-red-800', absent: 'bg-red-700 text-white', clash: 'bg-violet-50 text-violet-800', offline: 'bg-sun-100 text-sun-800', late: 'bg-orange-100 text-orange-900', early: 'bg-orange-100 text-orange-900' };

const filter = ref('todo');
const shown = computed(() => (filter.value === 'todo' ? props.items.filter((i) => i.blocking && !i.resolved) : props.items));

// แบ่งหน้า 25 รายการ · เปลี่ยนตัวกรองกลับไปหน้าแรก · จัดการรายการแล้วจำนวนลด ถ้าหน้าเกินให้ถอยกลับ
const PER_PAGE = 25;
const pageNo = ref(1);
const paged = computed(() => shown.value.slice((pageNo.value - 1) * PER_PAGE, pageNo.value * PER_PAGE));
watch(filter, () => (pageNo.value = 1));
watch(() => shown.value.length, (n) => (pageNo.value = Math.min(pageNo.value, Math.max(1, Math.ceil(n / PER_PAGE)))));

const go = (delta) => {
    const [y, m] = props.month.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    router.get('/staff/close', { month: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}` });
};
const fixHref = (i) => (['absent', 'clash'].includes(i.kind) ? `/staff/leaves?user=${i.user_id}` : `/staff?date=${i.date}`);

const acking = ref(null);
const ack = useForm({ user_id: null, date: '', kind: '', note: '' });
const openAck = (i) => {
    acking.value = i;
    Object.assign(ack, { user_id: i.user_id, date: i.date, kind: i.kind, note: '' });
};
const saveAck = () => ack.post('/staff/close/acknowledge', { preserveScroll: true, onSuccess: () => (acking.value = null) });
const close = () => confirm(`ปิดงวด ${props.title}? ข้อมูลเดือนนี้จะถูกล็อก`) && router.post('/staff/close', { month: props.month }, { preserveScroll: true });
const unlockForm = useForm({ month: props.month, reason: '' });
const unlocking = ref(false);
const unlock = () => unlockForm.post('/staff/close/unlock', { preserveScroll: true, onSuccess: () => (unlocking.value = false) });
</script>

<template>
    <Head title="ตรวจสอบรายการ" />
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-[20px] bg-navy-700 px-6 py-5 text-white shadow-card">
            <div class="flex items-center gap-3">
                <button type="button" class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/12" aria-label="เดือนก่อนหน้า" @click="go(-1)"><Icon name="chevron-left" :size="18" /></button>
                <div>
                    <div class="text-sm text-navy-300">ตรวจสอบรายการ</div>
                    <h1 class="text-[28px] font-bold">{{ title }}</h1>
                    <div class="text-sm text-navy-300">{{ closed ? `ปิดแล้วเมื่อ ${closed.at} โดย ${closed.by}` : monthEnded ? 'สถานะ: เปิดอยู่' : 'ยังไม่สิ้นเดือน · ตรวจล่วงหน้าได้' }}</div>
                </div>
                <button type="button" class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/12" aria-label="เดือนถัดไป" @click="go(1)"><Icon name="chevron-right" :size="18" /></button>
            </div>
            <div class="min-w-[260px]">
                <div class="mb-1.5 flex justify-between text-sm"><span>จัดการแล้ว {{ done }} / {{ blocking.length }} รายการ</span><b>{{ pct }}%</b></div>
                <div class="h-2.5 overflow-hidden rounded-full bg-white/20"><div class="h-full rounded-full bg-sun-500" :style="{ width: `${pct}%` }" /></div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
            <div v-for="k in kinds" :key="k.key" class="rounded-2xl px-4 py-3.5 shadow-card" :class="k.cls">
                <div class="text-[13px]">{{ k.label }}</div>
                <div class="text-[26px] font-bold">{{ count(k).filter((i) => !i.resolved).length }}<span class="text-sm font-medium opacity-75"> / {{ count(k).length }}</span></div>
            </div>
        </div>

        <div class="flex gap-1 self-start rounded-xl border border-line bg-white p-1">
            <button type="button" class="rounded-[9px] px-4 py-2 text-sm" :class="filter === 'todo' ? 'bg-navy-700 font-bold text-white' : 'hover:bg-soft'" @click="filter = 'todo'">รอจัดการ ({{ left }})</button>
            <button type="button" class="rounded-[9px] px-4 py-2 text-sm" :class="filter === 'all' ? 'bg-navy-700 font-bold text-white' : 'hover:bg-soft'" @click="filter = 'all'">ทั้งหมด ({{ items.length }})</button>
        </div>

        <section class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[880px] border-collapse text-sm">
                    <thead><tr class="border-b border-line text-left text-muted"><th class="px-3 py-3 font-semibold">วันที่</th><th class="px-3 py-3 font-semibold">ชื่อ</th><th class="px-3 py-3 font-semibold">ปัญหา</th><th class="px-3 py-3 font-semibold">รายละเอียด</th><th class="px-3 py-3 font-semibold">สถานะ</th><th class="px-3 py-3" /></tr></thead>
                    <tbody>
                        <tr v-if="!shown.length"><td colspan="6" class="px-4 py-10 text-center text-muted">{{ filter === 'todo' ? 'ไม่มีรายการที่ต้องจัดการ' : 'ไม่มีรายการผิดปกติ' }}</td></tr>
                        <tr v-for="i in paged" :key="`${i.user_id}-${i.date}-${i.kind}`" class="border-b border-soft">
                            <td class="whitespace-nowrap px-3 py-2.5">{{ thaiDate(i.date, { short: true, weekday: true }).replace(/ \d{4}$/, '') }}</td>
                            <td class="px-3 py-2.5 font-semibold">{{ i.name }}</td>
                            <td class="px-3 py-2.5"><span class="pill" :class="pill[i.kind]">{{ i.label }}</span></td>
                            <td class="px-3 py-2.5 text-muted">{{ i.detail }}<div v-if="i.ack_note" class="text-navy-700">รับทราบ: {{ i.ack_note }}</div></td>
                            <td class="whitespace-nowrap px-3 py-2.5 font-semibold" :class="i.resolved ? 'text-green-800' : 'text-orange-800'">{{ i.resolved ? (i.blocking ? '✓ จัดการแล้ว' : 'เพื่อทราบ') : 'รอจัดการ' }}</td>
                            <td class="px-3 py-2.5">
                                <div v-if="i.blocking && !i.resolved && !closed" class="flex justify-end gap-1.5">
                                    <Link :href="fixHref(i)" class="btn-navy h-8 px-3 text-[13px]">{{ ['absent', 'clash'].includes(i.kind) ? 'บันทึกลา' : 'แก้เวลา' }}</Link>
                                    <button type="button" class="btn-outline h-8 px-3 text-[13px]" @click="openAck(i)">รับทราบ</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pager v-model="pageNo" :total="shown.length" :per-page="PER_PAGE" />
        </section>

        <div class="card flex flex-wrap items-center justify-between gap-3 px-5 py-4">
            <div class="text-sm">
                <div class="font-bold">{{ closed ? 'งวดนี้ปิดแล้ว' : left ? `ยังเหลือ ${left} รายการที่ต้องจัดการก่อนปิดงวด` : monthEnded ? 'พร้อมปิดงวด' : 'ปิดงวดได้หลังสิ้นเดือน' }}</div>
                <div class="text-muted">ปิดงวดแล้วข้อมูลเวลาและวันลาของเดือนนี้จะถูกล็อก · แก้ภายหลังต้องให้ admin ปลดล็อกพร้อมเหตุผล</div>
            </div>
            <button v-if="!closed" type="button" class="btn-primary" :disabled="left > 0 || !monthEnded" @click="close">ยืนยันปิดงวด</button>
            <button v-else-if="isAdmin" type="button" class="btn-outline" @click="unlocking = true">ปลดล็อก (admin)</button>
        </div>
        <p v-if="history.length" class="text-[13px] text-muted">งวดที่ปิดแล้ว: <span v-for="(h, i) in history" :key="h.month">{{ i ? ' · ' : '' }}{{ h.title }} (ปิด {{ h.at }})</span></p>
    </div>

    <Modal v-if="acking" :title="`รับทราบ · ${acking.name}`" width="max-w-md" @close="acking = null">
        <form id="ack" class="space-y-3" @submit.prevent="saveAck">
            <p class="text-sm text-muted">{{ acking.label }} · {{ thaiDate(acking.date, { weekday: true }) }} · {{ acking.detail }}</p>
            <label for="ack-n" class="label">หมายเหตุ (ทำไมไม่ต้องแก้ข้อมูล)</label>
            <input id="ack-n" v-model="ack.note" class="input" placeholder="เช่น ไปอบรมภายนอก มีหนังสือแจ้งแล้ว" required>
            <p v-if="ack.errors.note" class="field-error">{{ ack.errors.note }}</p>
        </form>
        <template #footer>
            <button type="button" class="btn-outline" @click="acking = null">ยกเลิก</button>
            <button type="submit" form="ack" class="btn-primary" :disabled="ack.processing">บันทึก</button>
        </template>
    </Modal>
    <Modal v-if="unlocking" :title="`ปลดล็อกงวด ${title}`" width="max-w-md" @close="unlocking = false">
        <form id="ul" @submit.prevent="unlock"><label for="ul-r" class="label">เหตุผล</label><input id="ul-r" v-model="unlockForm.reason" class="input" required></form>
        <template #footer>
            <button type="button" class="btn-outline" @click="unlocking = false">ยกเลิก</button>
            <button type="submit" form="ul" class="btn-navy" :disabled="unlockForm.processing">ปลดล็อก</button>
        </template>
    </Modal>
</template>
