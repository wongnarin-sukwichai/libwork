<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import FadeSwap from '@/Components/FadeSwap.vue';
import Icon from '@/Components/Icon.vue';
import { thaiDate, isoDate } from '@/utils/thaiDate';
import { url } from '@/utils/url';
import { STATUS_TONES } from '@/utils/statusTones';

// สรุปภาพรวมการมาปฏิบัติงานและการลา ทุกคน ช่วงวันที่อิสระ (เช่น ทั้งปีงบประมาณ) · จัดกลุ่มตามกลุ่มงาน
const props = defineProps({ report: Object });

const from = ref(props.report.from);
const to = ref(props.report.to);
const load = () => from.value && to.value && router.get('/staff/summary', { from: from.value, to: to.value }, { preserveScroll: true, preserveState: true });

// ปีงบประมาณไทย: 1 ต.ค. – 30 ก.ย. (ปีงบ 2570 = 1 ต.ค. 2569 – 30 ก.ย. 2570)
const now = new Date();
const fyStartYear = now.getMonth() >= 9 ? now.getFullYear() : now.getFullYear() - 1;
const quick = [
    { label: 'เดือนนี้', from: new Date(now.getFullYear(), now.getMonth(), 1), to: now },
    { label: 'เดือนก่อน', from: new Date(now.getFullYear(), now.getMonth() - 1, 1), to: new Date(now.getFullYear(), now.getMonth(), 0) },
    { label: `ปีงบ ${fyStartYear + 544}`, from: new Date(fyStartYear, 9, 1), to: now },
    { label: `ปีงบ ${fyStartYear + 543}`, from: new Date(fyStartYear - 1, 9, 1), to: new Date(fyStartYear, 8, 30) },
].map((q) => ({ ...q, from: isoDate(q.from), to: isoDate(q.to) }));
const pick = (q) => {
    from.value = q.from;
    to.value = q.to;
    load();
};

const query = computed(() => `from=${props.report.from}&to=${props.report.to}`);
const t = computed(() => props.report.total);
const leaveCols = computed(() => Object.entries(props.report.leave_columns));
const leaveAll = computed(() => t.value.leave_total);

const stats = computed(() => [
    { label: 'บุคลากร', value: `${props.report.people} คน`, cls: 'border border-line bg-white text-ink' },
    { label: 'วันทำการ', value: `${props.report.workdays} วัน`, cls: 'border border-line bg-white text-ink' },
    { label: 'มาปฏิบัติงาน', value: `${t.value.present} วัน`, cls: STATUS_TONES.ok },
    { label: 'สาย', value: `${t.value.late_count} ครั้ง`, cls: STATUS_TONES.late },
    { label: 'ไม่ลงเวลา', value: `${t.value.absent} วัน`, cls: STATUS_TONES.absent },
    { label: 'ลา', value: `${fmt(leaveAll.value)} วัน`, cls: STATUS_TONES.leave },
    { label: 'ไปราชการ', value: `${fmt(t.value.leave.duty)} วัน`, cls: STATUS_TONES.duty },
    { label: 'OT', value: `${t.value.ot ?? '0:00'} ชม.`, cls: STATUS_TONES.ot },
]);

// 0 แสดงเป็นขีดจาง ๆ ให้ตัวเลขที่มีค่าเด่นขึ้น
const fmt = (n) => (Number.isInteger(n) ? n : Number(n).toFixed(1));
const cell = (n) => (n ? fmt(n) : null);
const personHref = (r) => `/staff/person?user=${r.user_id}&${query.value}`;
let no = 0;
const numbered = computed(() => {
    no = 0;
    return props.report.groups.map((g) => ({ ...g, rows: g.rows.map((r) => ({ ...r, no: ++no })) }));
});
</script>

<template>
    <Head title="สรุปภาพรวม" />
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3.5 rounded-[20px] bg-navy-700 px-6 py-5 text-white shadow-card">
            <div>
                <div class="text-sm text-navy-300">สรุปการมาปฏิบัติงานและการลา</div>
                <h1 class="text-[24px] font-bold">{{ thaiDate(report.from) }} – {{ thaiDate(report.to) }}</h1>
            </div>
            <div class="flex flex-wrap gap-2">
                <a :href="url(`/staff/reports/summary.pdf?${query}`)" target="_blank" class="btn-primary"><Icon name="file-pen" :size="18" /> PDF</a>
                <a :href="url(`/staff/reports/summary.xlsx?${query}`)" class="btn bg-excel text-white hover:bg-excel-dark">Excel</a>
            </div>
        </div>

        <section class="card flex flex-wrap items-end gap-3 p-4">
            <div class="flex-[1_1_150px]">
                <label for="s-from" class="label">ตั้งแต่</label>
                <input id="s-from" v-model="from" type="date" class="input" @change="load">
            </div>
            <div class="flex-[1_1_150px]">
                <label for="s-to" class="label">ถึง</label>
                <input id="s-to" v-model="to" type="date" class="input" :min="from" @change="load">
            </div>
            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="q in quick"
                    :key="q.label"
                    type="button"
                    class="h-11 rounded-xl px-3.5 text-sm"
                    :class="report.from === q.from && report.to === q.to ? 'bg-navy-700 font-bold text-white' : 'border border-line-strong bg-white hover:bg-soft'"
                    @click="pick(q)"
                >{{ q.label }}</button>
            </div>
        </section>

        <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4 xl:grid-cols-8">
            <div v-for="s in stats" :key="s.label" class="rounded-[14px] px-3.5 py-3 shadow-card" :class="s.cls">
                <div class="text-[13px]">{{ s.label }}</div>
                <div class="text-xl font-bold">{{ s.value }}</div>
            </div>
        </div>

        <FadeSwap>
            <section :key="query" class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1100px] border-collapse text-sm">
                        <thead>
                            <tr class="bg-navy-700 text-white">
                                <th rowspan="2" class="w-10 px-2.5 py-2 text-left font-semibold">#</th>
                                <th rowspan="2" class="px-2.5 py-2 text-left font-semibold">ชื่อ-สกุล</th>
                                <th rowspan="2" class="px-2 py-2 text-center font-semibold">วันทำการ</th>
                                <th rowspan="2" class="px-2 py-2 text-center font-semibold">มา</th>
                                <th colspan="2" class="border-b border-white/20 px-2 pt-2 pb-1 text-center font-semibold">สาย</th>
                                <th colspan="2" class="border-b border-white/20 px-2 pt-2 pb-1 text-center font-semibold">ออกก่อน</th>
                                <th rowspan="2" class="px-2 py-2 text-center font-semibold">ไม่ลงเวลา</th>
                                <th :colspan="leaveCols.length + 2" class="border-b border-white/20 px-2 pt-2 pb-1 text-center font-semibold">ลา (วัน)</th>
                                <th rowspan="2" class="px-2 py-2 text-center font-semibold">ไปราชการ</th>
                                <th rowspan="2" class="px-2 py-2 text-center font-semibold">OT (ชม.)</th>
                            </tr>
                            <tr class="bg-navy-700 text-[13px] text-navy-100">
                                <th class="px-2 pb-2 font-medium">ครั้ง</th><th class="px-2 pb-2 font-medium">นาที</th>
                                <th class="px-2 pb-2 font-medium">ครั้ง</th><th class="px-2 pb-2 font-medium">นาที</th>
                                <th v-for="[k, label] in leaveCols" :key="k" class="px-2 pb-2 font-medium">{{ label }}</th>
                                <th class="px-2 pb-2 font-medium">อื่นๆ</th><th class="px-2 pb-2 font-semibold text-white">รวม</th>
                            </tr>
                        </thead>
                        <tbody v-if="!numbered.length">
                            <tr><td :colspan="13 + leaveCols.length" class="px-4 py-10 text-center text-muted">ไม่มีข้อมูลในช่วงนี้</td></tr>
                        </tbody>
                        <tbody v-for="g in numbered" :key="g.id">
                            <tr class="bg-soft-2"><td :colspan="13 + leaveCols.length" class="px-2.5 py-2 font-bold">{{ g.name }} <span class="text-[13px] font-medium text-muted">· {{ g.rows.length }} คน</span></td></tr>
                            <tr v-for="r in [...g.rows, { ...g.total, total: true }]" :key="r.user_id ?? 'total'" class="border-b border-soft text-center" :class="r.total ? 'bg-page font-bold' : ''">
                                <td class="px-2.5 py-2 text-left text-muted">{{ r.no }}</td>
                                <td class="px-2.5 py-2 text-left">
                                    <Link v-if="!r.total" :href="personHref(r)" class="font-semibold text-navy-700 hover:underline">{{ r.name }}</Link>
                                    <span v-else>รวม</span>
                                </td>
                                <td class="px-2 py-2 text-muted">{{ r.workdays ?? '' }}</td>
                                <td class="px-2 py-2">{{ r.present }}</td>
                                <td class="px-2 py-2" :class="r.late_count ? 'text-orange-800' : 'text-navy-300'">{{ cell(r.late_count) ?? '–' }}</td>
                                <td class="px-2 py-2" :class="r.late_minutes ? 'text-orange-800' : 'text-navy-300'">{{ cell(r.late_minutes) ?? '–' }}</td>
                                <td class="px-2 py-2" :class="r.early_count ? 'text-orange-800' : 'text-navy-300'">{{ cell(r.early_count) ?? '–' }}</td>
                                <td class="px-2 py-2" :class="r.early_minutes ? 'text-orange-800' : 'text-navy-300'">{{ cell(r.early_minutes) ?? '–' }}</td>
                                <td class="px-2 py-2" :class="r.absent ? 'font-semibold text-red-800' : 'text-navy-300'">{{ cell(r.absent) ?? '–' }}</td>
                                <td v-for="[k] in leaveCols" :key="k" class="px-2 py-2" :class="r.leave[k] ? '' : 'text-navy-300'">{{ cell(r.leave[k]) ?? '–' }}</td>
                                <td class="px-2 py-2" :class="r.leave.other ? '' : 'text-navy-300'">{{ cell(r.leave.other) ?? '–' }}</td>
                                <td class="px-2 py-2 font-semibold" :class="r.leave_total ? 'text-navy-700' : 'text-navy-300'">{{ cell(r.leave_total) ?? '–' }}</td>
                                <td class="px-2 py-2" :class="r.leave.duty ? 'text-sun-800' : 'text-navy-300'">{{ cell(r.leave.duty) ?? '–' }}</td>
                                <td class="px-2 py-2" :class="r.ot ? '' : 'text-navy-300'">
                                    {{ r.ot ?? '–' }}<span v-if="r.ot_special" class="ml-1 font-semibold text-violet-700">+{{ r.ot_special }}</span>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="numbered.length">
                            <tr class="bg-navy-700 text-center font-bold text-white">
                                <td colspan="2" class="px-2.5 py-2.5 text-left">รวมทั้งหมด</td>
                                <td class="px-2 py-2.5">{{ report.workdays }}</td>
                                <td class="px-2 py-2.5">{{ t.present }}</td>
                                <td class="px-2 py-2.5">{{ t.late_count }}</td><td class="px-2 py-2.5">{{ t.late_minutes }}</td>
                                <td class="px-2 py-2.5">{{ t.early_count }}</td><td class="px-2 py-2.5">{{ t.early_minutes }}</td>
                                <td class="px-2 py-2.5">{{ t.absent }}</td>
                                <td v-for="[k] in leaveCols" :key="k" class="px-2 py-2.5">{{ fmt(t.leave[k]) }}</td>
                                <td class="px-2 py-2.5">{{ fmt(t.leave.other) }}</td>
                                <td class="px-2 py-2.5">{{ fmt(t.leave_total) }}</td>
                                <td class="px-2 py-2.5">{{ fmt(t.leave.duty) }}</td>
                                <td class="px-2 py-2.5">{{ t.ot ?? '0:00' }}<span v-if="t.ot_special" class="ml-1">+{{ t.ot_special }}</span></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        </FadeSwap>
        <p class="text-[13px] text-muted">
            วันทำการและ "ไม่ลงเวลา" นับถึง{{ report.counted_to === report.to ? 'วันสุดท้ายของช่วง' : `วันนี้ (${thaiDate(report.counted_to)})` }} · ไม่ลงเวลา = วันทำการที่ผ่านมาแล้ว ไม่มีเวลาเข้าและไม่มีวันลา
            · วันลานับรวมวันลาล่วงหน้าในช่วงที่เลือก (ครึ่งวัน = 0.5) · OT ตัวม่วง = ล่วงเวลาพิเศษ · กดชื่อเพื่อดูรายบุคคลช่วงเดียวกัน
        </p>
    </div>
</template>
