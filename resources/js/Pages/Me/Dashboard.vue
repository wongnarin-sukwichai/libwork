<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import MonthAttendanceTable from '@/Components/MonthAttendanceTable.vue';
import { THAI_MONTHS, thaiDate, isoDate } from '@/utils/thaiDate';

// หน้าหลักของบุคลากร (แบบ A3)
const props = defineProps({
    today: Object,
    month: Object,
    bars: Array,
    leaves: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const now = new Date();
const monthTitle = `${THAI_MONTHS[now.getMonth()]} ${now.getFullYear() + 543}`;

const todayText = computed(() => {
    if (!props.today.check_in) return 'วันนี้ยังไม่ได้ลงเวลา';
    const late = props.today.late ? `สาย ${props.today.late} นาที` : 'ตรงเวลา';
    return props.today.check_out
        ? `วันนี้ ${props.today.check_in}–${props.today.check_out} น.`
        : `วันนี้ลงเวลาเข้า ${props.today.check_in} น. · ${late}`;
});

const stats = computed(() => [
    { label: 'มาปฏิบัติงาน', value: `${props.month.stats.present} วัน`, sub: `จาก ${props.month.stats.workdays} วันทำการ`, cls: 'bg-sun-500 text-black' },
    { label: 'ชั่วโมงปกติ', value: props.month.stats.hours, sub: 'ชั่วโมง:นาที', cls: 'border border-line bg-white' },
    { label: 'ชั่วโมง OT', value: props.month.stats.ot, sub: `ปกติ ${props.month.stats.ot_normal} · พิเศษ ${props.month.stats.ot_special}`, cls: 'border border-line bg-soft-2' },
    { label: 'มาสาย', value: `${props.month.stats.late_count} ครั้ง`, sub: `รวม ${props.month.stats.late_minutes} นาที`, cls: 'bg-navy-700 text-white' },
]);

const scale = 13; // px ต่อชั่วโมง
</script>

<template>
    <Head title="หน้าหลัก" />
    <div class="space-y-5">
        <div class="flex flex-wrap items-end justify-between gap-4 rounded-[20px] bg-navy-700 px-6 py-6 text-white shadow-card">
            <div class="space-y-1">
                <div class="text-[15px] text-navy-300">{{ thaiDate(isoDate(), { weekday: true }) }}</div>
                <h1 class="text-3xl font-bold">สวัสดี {{ user.first_name }}</h1>
                <div class="text-[15px] text-navy-300">{{ todayText }}</div>
            </div>
            <Link href="/me/attendance" class="rounded-[10px] bg-sun-500 px-3.5 py-2 text-sm font-bold text-black">{{ monthTitle }}</Link>
        </div>

        <div class="grid grid-cols-2 gap-3.5 xl:grid-cols-4">
            <div v-for="s in stats" :key="s.label" class="rounded-[18px] px-4 py-4 shadow-card" :class="s.cls">
                <div class="text-sm opacity-85">{{ s.label }}</div>
                <div class="mt-1 text-[30px] font-bold">{{ s.value }}</div>
                <div class="text-[13px] opacity-80">{{ s.sub }}</div>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <section class="card p-5">
                <div class="mb-3.5 flex items-center justify-between">
                    <h2 class="text-lg font-bold">ชั่วโมงทำงาน 10 วันล่าสุด</h2>
                    <div class="flex gap-3 text-xs">
                        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-[3px] bg-sun-500" />ปกติ</span>
                        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-[3px] bg-navy-700" />OT</span>
                    </div>
                </div>
                <div class="flex h-[210px] items-end gap-2.5 border-b border-line px-1" role="img" aria-label="กราฟชั่วโมงทำงาน 10 วันล่าสุด">
                    <div v-for="b in bars" :key="b.day" class="flex h-full flex-1 flex-col items-center justify-end">
                        <div v-if="b.ot" class="w-full max-w-[30px] bg-navy-700" :class="b.normal ? 'rounded-t-md' : 'rounded-md'" :style="{ height: `${b.ot * scale}px` }" />
                        <div v-if="b.normal" class="w-full max-w-[30px] bg-sun-500" :class="b.ot ? 'rounded-b-md' : 'rounded-md'" :style="{ height: `${b.normal * scale}px` }" />
                        <div class="mt-1.5 min-h-[14px] text-[11px] font-semibold" :class="b.flag === '!' ? 'text-red-700' : 'text-muted'">{{ b.flag }}</div>
                        <div class="pb-1.5 text-xs text-muted">{{ b.day }}</div>
                    </div>
                </div>
            </section>

            <section class="card p-5">
                <div class="mb-3.5 flex items-center justify-between">
                    <h2 class="text-lg font-bold">วันลาคงเหลือ</h2>
                    <Link href="/me/leave" class="text-sm font-semibold underline decoration-sun-500 decoration-[3px] underline-offset-4">ดูรายละเอียด</Link>
                </div>
                <p v-if="!leaves.length" class="rounded-xl bg-page px-4 py-6 text-center text-muted">ยังไม่ได้กำหนดสิทธิ์วันลา (ติดต่อ HR)</p>
                <div class="flex flex-col gap-3.5">
                    <div v-for="l in leaves" :key="l.id">
                        <div class="mb-1.5 flex justify-between text-sm">
                            <span class="font-semibold">{{ l.name }}</span>
                            <span v-if="l.counts_quota"><b>{{ l.remaining }} วัน</b> <span class="text-[13px] text-muted">{{ l.carry_over ? (l.pending ? '· ยอดยกมารอตรวจ' : `/ รวมได้ ${l.entitled + l.carried_over}`) : `ใช้ไป ${l.used} / ${l.entitled}` }}</span></span>
                            <span v-else><b>{{ l.times }} ครั้ง</b> <span class="text-[13px] text-muted">ไม่หักโควต้า</span></span>
                        </div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-soft">
                            <div class="h-full rounded-full" :class="l.carry_over ? 'bg-sun-500' : 'bg-navy-700'" :style="{ width: `${l.counts_quota && l.entitled + l.carried_over > 0 ? Math.max(0, (l.remaining / (l.entitled + l.carried_over)) * 100) : l.times ? 15 : 0}%` }" />
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <section class="card p-5">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-bold">เวลาปฏิบัติงาน {{ monthTitle }}</h2>
                <span class="pill" :class="month.closed ? 'bg-green-50 text-green-800' : 'bg-sun-100 text-sun-800'">{{ month.closed ? 'HR ปิดงวดแล้ว' : 'รอ HR ปิดงวด' }}</span>
            </div>
            <MonthAttendanceTable :rows="month.rows.slice(0, 10)" />
            <Link v-if="month.rows.length > 10" href="/me/attendance" class="mt-3 inline-block text-sm font-semibold underline decoration-sun-500 decoration-2 underline-offset-4">ดูทั้งเดือน</Link>
        </section>
    </div>
</template>
