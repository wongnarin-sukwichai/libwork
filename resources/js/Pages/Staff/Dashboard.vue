<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import Modal from '@/Components/Modal.vue';
import FadeSwap from '@/Components/FadeSwap.vue';
import StatusPill from '@/Components/StatusPill.vue';
import EditAttendance from './EditAttendance.vue';
import LeaveModal from '@/Components/LeaveModal.vue';
import { thaiDate, isoDate } from '@/utils/thaiDate';
import { url } from '@/utils/url';
import { STATUS_TONES } from '@/utils/statusTones';

// หน้าแรก HR: ตารางลงเวลาประจำวัน จัดกลุ่มตามกลุ่มงาน (หน้า 6 ใน mockup)
const props = defineProps({ report: Object, monthClose: Object });

const go = (patch) => router.get('/staff', { date: props.report.date, type: props.report.type, ...patch }, { preserveScroll: true, preserveState: true });
const shiftDay = (delta) => {
    const d = new Date(`${props.report.date}T00:00:00`);
    d.setDate(d.getDate() + delta);
    go({ date: isoDate(d) });
};
const isToday = computed(() => props.report.date === isoDate());

// สีการ์ด = สี badge ของสถานะเดียวกันในตาราง (utils/statusTones.js)
// แท็บ OT แสดงเฉพาะคนที่มีรายการ OT จึงเหลือ 3 การ์ดที่มีความหมาย (สาย/ลา/ไม่ได้ลงเวลา เป็น 0 เสมอ)
const stats = computed(() => props.report.type === 'ot' ? [
    { label: 'มาปฏิบัติงาน (OT)', value: props.report.stats.present, cls: STATUS_TONES.ot },
    { label: 'ล่วงเวลาพิเศษ', value: props.report.stats.special, cls: STATUS_TONES.special },
    { label: 'ไม่ได้ลงเวลาออก', value: props.report.stats.no_out, cls: STATUS_TONES.bad },
] : [
    { label: 'บุคลากร', value: props.report.stats.total, cls: 'border border-line bg-white text-ink' },
    { label: 'มาปฏิบัติงาน', value: props.report.stats.present, cls: STATUS_TONES.ok },
    { label: 'สาย / ออกก่อน', value: props.report.stats.late, cls: STATUS_TONES.late },
    { label: 'ลา', value: props.report.stats.leave, cls: STATUS_TONES.leave },
    { label: 'ไปราชการ', value: props.report.stats.duty, cls: STATUS_TONES.duty },
    { label: 'ไม่ได้ลงเวลาออก', value: props.report.stats.no_out, cls: STATUS_TONES.bad },
    { label: 'ไม่ได้ลงเวลา', value: props.report.stats.absent, cls: STATUS_TONES.absent },
]);

const editing = ref(null);
// บันทึกลา: { person } จากแถว หรือ {} จากปุ่มบนหัว (เลือกคนเอง) · วันที่ตั้งต้น = วันที่กำลังดู
const leaving = ref(null);
const canLeave = (r) => props.report.type !== 'ot' && !['leave', 'duty'].includes(r.tone);
const photo = ref(null);
const photoUrl = (row, kind) => url(`/photos/attendance/${row.attendance_id}/${kind}`);
let n = 0;
const numbered = computed(() => {
    n = 0;
    return props.report.groups.map((g) => ({ ...g, rows: g.rows.map((r) => ({ ...r, no: ++n })) }));
});
</script>

<template>
    <Head title="รายงานประจำวัน" />
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3.5 rounded-[20px] bg-navy-700 px-6 py-5 text-white shadow-card">
            <div class="flex items-center gap-4">
                <img :src="url('/img/logos/library.png')" alt="MSU Library" class="h-14 w-auto shrink-0">
                <div class="border-l border-white/25 pl-4">
                <div class="text-sm text-navy-300">รายงานประจำวัน<span v-if="report.schedule?.special" class="ml-2 rounded-md bg-violet-600 px-2 py-0.5 text-xs font-bold text-white">{{ report.schedule.name }}</span></div>
                <h1 class="text-[26px] font-bold">{{ thaiDate(report.date, { weekday: true }) }}</h1>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/12" aria-label="วันก่อนหน้า" @click="shiftDay(-1)"><Icon name="chevron-left" :size="18" /></button>
                <label for="dt" class="sr-only">วันที่</label>
                <input id="dt" type="date" :value="report.date" :max="isoDate()" class="h-11 rounded-xl border-0 bg-white/12 px-3 text-[15px] text-white scheme-dark" @change="go({ date: $event.target.value })">
                <button type="button" class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/12 disabled:opacity-40" aria-label="วันถัดไป" :disabled="isToday" @click="shiftDay(1)"><Icon name="chevron-right" :size="18" /></button>
                <button v-if="!isToday" type="button" class="h-11 rounded-xl bg-white/12 px-3 text-sm" @click="go({ date: isoDate() })">วันนี้</button>
                <button type="button" class="btn bg-white text-ink hover:bg-soft" @click="leaving = {}"><Icon name="plus" :size="18" /> บันทึกลา</button>
                <a :href="url(`/staff/reports/daily.pdf?date=${report.date}&type=${report.type}`)" target="_blank" class="btn-primary" title="PDF ส่งหัวหน้าสำนัก"><Icon name="file-pen" :size="18" /> PDF</a>
                <a :href="url(`/staff/reports/daily.xlsx?date=${report.date}&type=${report.type}`)" class="btn bg-excel text-white hover:bg-excel-dark">Excel</a>
            </div>
        </div>

        <div class="grid gap-2.5" :class="report.type === 'ot' ? 'grid-cols-1 sm:grid-cols-3' : 'grid-cols-2 sm:grid-cols-4 xl:grid-cols-7'">
            <div v-for="s in stats" :key="s.label" class="rounded-[14px] px-3.5 py-3 shadow-card" :class="s.cls">
                <div class="text-[13px]">{{ s.label }}</div>
                <div class="text-2xl font-bold">{{ s.value }}</div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2.5">
            <div class="flex gap-1 rounded-xl border border-line bg-white p-1">
                <button type="button" class="rounded-[9px] px-4 py-2 text-sm" :class="report.type === 'normal' ? 'bg-navy-700 font-bold text-white' : 'hover:bg-soft'" @click="go({ type: 'normal' })">เวลาปกติ</button>
                <button type="button" class="rounded-[9px] px-4 py-2 text-sm" :class="report.type === 'ot' ? 'bg-navy-700 font-bold text-white' : 'hover:bg-soft'" @click="go({ type: 'ot' })">ล่วงเวลา (OT)</button>
            </div>
            <span v-if="report.schedule" class="text-sm text-muted">{{ report.schedule.name }} · {{ report.schedule.start }}–{{ report.schedule.end }} น.</span>
            <span v-else-if="report.type === 'normal' && !report.workday" class="text-sm text-muted">วันหยุด · ดูได้ที่แท็บล่วงเวลา (OT)</span>
            <Link
                :href="`/staff/close?month=${monthClose.month}`"
                class="ml-auto flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm"
                :class="monthClose.closed ? 'bg-green-50 text-green-900' : monthClose.pending ? 'bg-sun-100 text-sun-800' : 'bg-soft text-ink'"
            >
                <template v-if="monthClose.closed">งวด {{ monthClose.title }} ปิดแล้ว · แก้ไขไม่ได้</template>
                <template v-else-if="monthClose.pending"><b>{{ monthClose.pending }} รายการรอตรวจสอบ</b> · งวด {{ monthClose.title }} →</template>
                <template v-else>งวด {{ monthClose.title }} ไม่มีรายการรอตรวจสอบ →</template>
            </Link>
        </div>

        <!-- เปลี่ยนแท็บ ปกติ/OT หรือวันที่ ตารางจะ fade -->
        <FadeSwap>
        <section :key="`${report.type}-${report.date}`" class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] border-collapse text-sm">
                    <thead>
                        <tr class="bg-navy-700 text-left text-white">
                            <th class="w-10 px-2.5 py-3 font-semibold">#</th>
                            <th class="px-2.5 py-3 font-semibold">ชื่อ-สกุล</th>
                            <th class="px-2.5 py-3 font-semibold">เข้า</th>
                            <th class="px-2.5 py-3 font-semibold">ออก</th>
                            <th class="px-2.5 py-3 font-semibold">ชม. สะสม</th>
                            <th class="px-2.5 py-3 font-semibold">สถานะ</th>
                            <th class="px-2.5 py-3 font-semibold">หมายเหตุ</th>
                            <th class="px-2.5 py-3"><span class="sr-only">จัดการ</span></th>
                        </tr>
                    </thead>
                    <tbody v-if="!numbered.length">
                        <tr><td colspan="8" class="px-4 py-10 text-center text-muted">{{ report.type === 'ot' ? 'ไม่มีการลงเวลา OT ในวันนี้' : 'ไม่มีข้อมูลในวันนี้' }}</td></tr>
                    </tbody>
                    <tbody v-for="g in numbered" :key="g.id">
                        <tr class="bg-soft-2">
                            <td colspan="8" class="px-2.5 py-2 font-bold">{{ g.name }} <span class="text-[13px] font-medium text-muted">· {{ g.summary }}</span></td>
                        </tr>
                        <tr v-for="r in g.rows" :key="r.user_id" class="border-b border-soft">
                            <td class="px-2.5 py-2 text-muted">{{ r.no }}</td>
                            <td class="px-2.5 py-2 font-semibold">{{ r.name }}</td>
                            <td class="px-2.5 py-2">
                                <div class="flex items-center gap-2">
                                    <button v-if="r.has_in_photo" type="button" class="shrink-0" :aria-label="`รูปตอนเข้า ${r.name}`" @click="photo = { src: photoUrl(r, 'in'), title: `${r.name} · เข้า ${r.check_in_seconds}` }">
                                        <img :src="photoUrl(r, 'in')" alt="" loading="lazy" class="h-[34px] w-[34px] rounded-full object-cover">
                                    </button>
                                    <span v-else class="h-[34px] w-[34px] shrink-0 rounded-full border border-dashed border-line-strong" />
                                    {{ r.check_in ?? '—' }}
                                </div>
                            </td>
                            <td class="px-2.5 py-2">
                                <div class="flex items-center gap-2">
                                    <button v-if="r.has_out_photo" type="button" class="shrink-0" :aria-label="`รูปตอนออก ${r.name}`" @click="photo = { src: photoUrl(r, 'out'), title: `${r.name} · ออก ${r.check_out_seconds}` }">
                                        <img :src="photoUrl(r, 'out')" alt="" loading="lazy" class="h-[34px] w-[34px] rounded-full object-cover">
                                    </button>
                                    <span v-else class="h-[34px] w-[34px] shrink-0 rounded-full border border-dashed border-line-strong" />
                                    {{ r.check_out ?? '—' }}
                                </div>
                            </td>
                            <td class="px-2.5 py-2">
                                {{ r.hours ?? '—' }}<span v-if="r.special_hours" class="ml-1 font-semibold text-violet-700">+{{ r.special_hours }}</span>
                            </td>
                            <td class="px-2.5 py-2"><StatusPill :tone="r.tone" :text="r.status" /></td>
                            <td class="px-2.5 py-2 text-[13px] text-muted">
                                {{ r.note }}
                                <span v-if="r.manual" class="ml-1 rounded bg-soft px-1.5 text-xs">แก้โดย HR</span>
                                <span v-if="r.offline" class="ml-1 rounded bg-sun-100 px-1.5 text-xs text-sun-800">ออฟไลน์</span>
                            </td>
                            <td class="px-2.5 py-2 text-right">
                                <div class="flex justify-end gap-1.5">
                                    <button v-if="canLeave(r)" type="button" class="btn-ghost h-8 px-2.5 text-[13px] text-navy-700" @click="leaving = { person: { id: r.user_id, name: r.name } }">บันทึกลา</button>
                                    <button type="button" class="btn-outline h-8 px-3 text-[13px]" @click="editing = r">{{ r.attendance_id ? 'แก้ไข' : 'เพิ่มเวลา' }}</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
        </FadeSwap>
        <p class="text-[13px] text-muted">จัดกลุ่มตามกลุ่มงาน ณ วันที่เลือก · เรียงตามลำดับในโครงสร้างหน่วยงาน · กดรูปเพื่อดูรูปเต็ม</p>
    </div>

    <EditAttendance v-if="editing" :row="editing" :date="report.date" :type="report.type" @close="editing = null" />
    <LeaveModal v-if="leaving" :date="report.date" :person="leaving.person" @close="leaving = null" />
    <Modal v-if="photo" :title="photo.title" width="max-w-md" @close="photo = null">
        <img :src="photo.src" alt="" class="w-full rounded-xl">
    </Modal>
</template>
