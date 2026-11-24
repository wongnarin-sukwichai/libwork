<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import Modal from '@/Components/Modal.vue';
import PersonPicker from '@/Components/PersonPicker.vue';
import StatusPill from '@/Components/StatusPill.vue';
import { thaiDate, isoDate } from '@/utils/thaiDate';
import { url } from '@/utils/url';

// รายงานรายบุคคล (หน้า 12): ช่วงวันที่ใดก็ได้ · ใช้ทำรายงานส่งคณะของนิสิตฝึกงาน
const props = defineProps({
    people: Array,
    selected: { type: Object, default: null },
    from: String,
    to: String,
    report: { type: Object, default: null },
});

const userId = ref(props.selected?.id ?? '');
const from = ref(props.from ?? '');
const to = ref(props.to ?? '');
const photos = ref(true);

const load = () => userId.value && router.get('/staff/person', { user: userId.value, from: from.value || undefined, to: to.value || undefined }, { preserveScroll: true });
const pickPerson = () => {
    from.value = '';
    to.value = '';
    load();
};
const quick = (kind) => {
    const now = new Date();
    if (kind === 'this') {
        from.value = isoDate(new Date(now.getFullYear(), now.getMonth(), 1));
        to.value = isoDate(now);
    } else if (kind === 'last') {
        from.value = isoDate(new Date(now.getFullYear(), now.getMonth() - 1, 1));
        to.value = isoDate(new Date(now.getFullYear(), now.getMonth(), 0));
    } else {
        from.value = props.selected.start_date;
        to.value = props.selected.end_date && props.selected.end_date < isoDate() ? props.selected.end_date : isoDate();
    }
    load();
};

const query = computed(() => `user=${props.selected?.id}&from=${props.from}&to=${props.to}`);
const pdfUrl = computed(() => url(`/staff/reports/person.pdf?${query.value}&photos=${photos.value ? 1 : 0}`));
const xlsxUrl = computed(() => url(`/staff/reports/person.xlsx?${query.value}`));
const photo = ref(null);
const photoUrl = (r, kind) => url(`/photos/attendance/${r.attendance_id}/${kind}`);
</script>

<template>
    <Head title="รายบุคคล" />
    <div class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-[26px] font-bold">รายงานรายบุคคล</h1>
                <p class="text-[15px] text-muted">เลือกบุคลากรและช่วงวันที่ · ใช้ทำรายงานส่งคณะของนิสิตฝึกงานได้</p>
            </div>
            <div v-if="report" class="flex flex-wrap items-center gap-2">
                <label class="flex items-center gap-2 text-sm"><input v-model="photos" type="checkbox" class="h-4 w-4 accent-navy-700"> แสดงรูปใน PDF</label>
                <a :href="pdfUrl" target="_blank" class="btn-primary">PDF พร้อมช่องลงนาม</a>
                <a :href="xlsxUrl" class="btn bg-excel text-white hover:bg-excel-dark">Excel</a>
            </div>
        </div>

        <section class="card flex flex-wrap items-end gap-3 p-4">
            <div class="min-w-[260px] flex-[2_1_260px]">
                <label for="p" class="label">บุคลากร</label>
                <PersonPicker id="p" v-model="userId" :people="people" :placeholder="`พิมพ์ชื่อหรือรหัส (${people.length} คน)`" @select="pickPerson" />
            </div>
            <div class="flex-[1_1_140px]">
                <label for="f" class="label">ตั้งแต่</label>
                <input id="f" v-model="from" type="date" class="input" :disabled="!selected" @change="load">
            </div>
            <div class="flex-[1_1_140px]">
                <label for="t" class="label">ถึง</label>
                <input id="t" v-model="to" type="date" class="input" :min="from" :disabled="!selected" @change="load">
            </div>
            <div v-if="selected" class="flex flex-wrap gap-1.5">
                <button type="button" class="btn-outline h-11 text-sm" @click="quick('this')">เดือนนี้</button>
                <button type="button" class="btn-outline h-11 text-sm" @click="quick('last')">เดือนก่อน</button>
                <button v-if="selected.start_date && selected.end_date" type="button" class="btn-navy h-11 text-sm" @click="quick('period')">{{ selected.intern ? 'ตลอดช่วงฝึกงาน' : 'ตลอดช่วงสัญญา' }}</button>
            </div>
        </section>

        <div v-if="!selected" class="card p-8 text-center text-muted">เลือกบุคลากรเพื่อดูรายงาน</div>

        <template v-else-if="report">
            <div class="grid gap-3.5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <section class="flex items-center gap-3.5 rounded-[18px] bg-navy-700 p-5 text-white shadow-card">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-sun-500 text-lg font-bold text-black">{{ selected.name.slice(0, 2) }}</div>
                    <div class="text-sm leading-relaxed">
                        <div class="text-lg font-bold">{{ selected.name }}</div>
                        <div class="text-navy-200">{{ selected.type ?? '—' }} · {{ selected.department ?? '—' }}</div>
                        <div class="text-navy-200">{{ thaiDate(from, { short: true }) }} – {{ thaiDate(to, { short: true }) }}<span v-if="selected.period"> · {{ selected.period }}</span></div>
                    </div>
                </section>
                <div class="grid grid-cols-2 gap-2.5">
                    <div class="rounded-[14px] bg-sun-500 px-3.5 py-3 text-black shadow-card"><div class="text-[13px]">มาปฏิบัติงาน</div><div class="text-[22px] font-bold">{{ report.stats.present }} / {{ report.stats.workdays }} วัน</div></div>
                    <div class="card px-3.5 py-3"><div class="text-[13px] text-muted">ชั่วโมงรวม</div><div class="text-[22px] font-bold">{{ report.stats.hours }}</div></div>
                    <div class="rounded-[14px] bg-orange-100 px-3.5 py-3 text-orange-900 shadow-card"><div class="text-[13px]">มาสาย</div><div class="text-[22px] font-bold">{{ report.stats.late_count }} ครั้ง</div></div>
                    <div class="rounded-[14px] bg-soft-2 px-3.5 py-3 text-navy-700 shadow-card"><div class="text-[13px]">ลา</div><div class="text-[22px] font-bold">{{ report.stats.leave_days }} วัน</div></div>
                </div>
            </div>

            <section class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] border-collapse text-sm">
                        <thead>
                            <tr class="bg-navy-700 text-left text-white">
                                <th class="px-2.5 py-3 font-semibold">วันที่</th><th class="px-2.5 py-3 font-semibold">เข้า</th><th class="px-2.5 py-3 font-semibold">ออก</th>
                                <th class="px-2.5 py-3 font-semibold">ชม.</th><th class="px-2.5 py-3 font-semibold">OT</th><th class="px-2.5 py-3 font-semibold">สถานะ</th><th class="px-2.5 py-3 font-semibold">หมายเหตุ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!report.rows.length"><td colspan="7" class="px-4 py-8 text-center text-muted">ไม่มีข้อมูลในช่วงนี้</td></tr>
                            <tr v-for="r in report.rows" :key="r.date" class="border-b border-soft" :class="{ 'bg-[#F8FAFD] text-muted': r.holiday && !r.check_in }">
                                <td class="whitespace-nowrap px-2.5 py-2">{{ thaiDate(r.date, { short: true, weekday: true }) }}</td>
                                <td class="px-2.5 py-2">
                                    <div class="flex items-center gap-2">
                                        <button v-if="r.has_in_photo" type="button" :aria-label="`รูปตอนเข้า ${r.date}`" @click="photo = photoUrl(r, 'in')"><img :src="photoUrl(r, 'in')" alt="" loading="lazy" class="h-8 w-8 rounded-full object-cover"></button>
                                        <span v-else class="h-8 w-8" />{{ r.check_in ?? '' }}
                                    </div>
                                </td>
                                <td class="px-2.5 py-2">
                                    <div class="flex items-center gap-2">
                                        <button v-if="r.has_out_photo" type="button" :aria-label="`รูปตอนออก ${r.date}`" @click="photo = photoUrl(r, 'out')"><img :src="photoUrl(r, 'out')" alt="" loading="lazy" class="h-8 w-8 rounded-full object-cover"></button>
                                        <span v-else class="h-8 w-8" />{{ r.check_out ?? '' }}
                                    </div>
                                </td>
                                <td class="px-2.5 py-2">{{ r.hours ?? '' }}</td>
                                <td class="px-2.5 py-2">{{ r.ot ?? '' }}<span v-if="r.ot_special" class="font-semibold text-violet-700">{{ r.ot_special }}</span></td>
                                <td class="px-2.5 py-2"><StatusPill :tone="r.tone" :text="r.status" /></td>
                                <td class="px-2.5 py-2 text-[13px] text-muted">{{ r.note }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <p class="text-[13px] text-muted">PDF มีช่องลงนาม: {{ selected.intern ? 'ผู้ฝึกงาน · ผู้ควบคุมการฝึกงาน · หัวหน้าสำนัก' : 'ผู้รายงาน · หัวหน้าสำนัก' }}</p>
        </template>
    </div>
    <Modal v-if="photo" title="รูปตอนลงเวลา" width="max-w-md" @close="photo = null"><img :src="photo" alt="" class="w-full rounded-xl"></Modal>
</template>
