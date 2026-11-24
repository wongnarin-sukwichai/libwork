<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import Modal from '@/Components/Modal.vue';
import { thaiDate } from '@/utils/thaiDate';

// รูปแบบเวลา + วันที่ใช้รูปแบบพิเศษ
const props = defineProps({
    schedules: Array,
    specialDays: Array,
    flex: { type: Object, default: () => ({ latest_start: '09:00', min_hours: 7, people: [] }) },
});

const appliesLabel = { weekday: 'จ.–ศ.', holiday: 'ส.–อา. / วันหยุด', special: 'กำหนดรายวัน' };
const roundingLabel = { minute: 'นาที', half_hour: 'ครึ่งชม. (ปัดลง)', hour: 'ชม.เต็ม (ปัดลง)' };
const hm = (min) => (min ? `${Math.floor(min / 60)}${min % 60 ? `:${String(min % 60).padStart(2, '0')}` : ''} ชม.` : '—');

const blank = { id: null, name: '', type: 'ot', applies_to: 'special', start_time: '16:30', end_time: '20:00', grace_minutes: 0, count_from: '17:00', break_start: '', break_end: '', special_from: '', rounding: 'hour', daily_cap_minutes: '', is_active: true };
const form = useForm({ ...blank });
const open = ref(false);
const edit = (s = null) => {
    form.clearErrors();
    Object.assign(form, s ? { ...blank, ...s, break_start: s.break_start ?? '', break_end: s.break_end ?? '', special_from: s.special_from ?? '', daily_cap_minutes: s.daily_cap_minutes ?? '' } : { ...blank });
    open.value = true;
};
const save = () => {
    const data = form.transform((d) => ({
        ...d,
        break_start: d.break_start || null,
        break_end: d.break_end || null,
        special_from: d.type === 'ot' ? d.special_from || null : null,
        daily_cap_minutes: d.daily_cap_minutes === '' ? null : Number(d.daily_cap_minutes),
    }));
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };
    if (form.id) data.put(`/admin/settings/schedules/${form.id}`, options);
    else data.post('/admin/settings/schedules', options);
};

const dayForm = useForm({ date: '', schedule_id: props.schedules.find((s) => s.applies_to === 'special')?.id ?? '', note: '' });
const addDay = () => dayForm.post('/admin/settings/special-days', { preserveScroll: true, onSuccess: () => dayForm.reset('date', 'note') });
// เวลายืดหยุ่น: นับได้ถึง เข้าไม่เกิน + ชั่วโมงขั้นต่ำ + พัก 1 ชม.
const flexForm = useForm({ latest_start: props.flex.latest_start, min_hours: props.flex.min_hours });
const saveFlex = () => flexForm.put('/admin/settings/flex', { preserveScroll: true });
const flexEnd = computed(() => {
    const [h, m] = (flexForm.latest_start || '09:00').split(':').map(Number);
    const t = h * 60 + m + Math.round(Number(flexForm.min_hours || 0) * 60) + 60;
    return `${String(Math.floor(t / 60)).padStart(2, '0')}:${String(t % 60).padStart(2, '0')}`;
});
const removeDay = (d) => confirm(`ยกเลิกวันพิเศษ ${thaiDate(d.date)}?`) && router.delete(`/admin/settings/special-days/${d.id}`, { preserveScroll: true });
</script>

<template>
    <div class="space-y-6">
        <section class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="max-w-2xl text-sm text-muted">แก้แล้วคำนวณชั่วโมงใหม่ให้รายการตั้งแต่วันนี้ ข้อมูลย้อนหลังไม่เปลี่ยน · วันหยุดใช้ "OT วันหยุด" อัตโนมัติ · วันที่ต้องการรูปแบบพิเศษ กำหนดด้านล่าง</p>
                <button type="button" class="btn-primary" @click="edit()"><Icon name="plus" :size="18" /> เพิ่มรูปแบบ</button>
            </div>
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[960px] border-collapse text-sm">
                        <thead>
                            <tr class="bg-navy-700 text-left text-white">
                                <th class="px-3 py-3 font-semibold">รูปแบบ</th>
                                <th class="px-3 py-3 font-semibold">ใช้กับ</th>
                                <th class="px-3 py-3 font-semibold">ช่วงเวลา</th>
                                <th class="px-3 py-3 font-semibold">ผ่อนผัน</th>
                                <th class="px-3 py-3 font-semibold">เริ่มนับ</th>
                                <th class="px-3 py-3 font-semibold">หักพัก</th>
                                <th class="px-3 py-3 font-semibold">ช่วงพิเศษ</th>
                                <th class="px-3 py-3 font-semibold">ปัดเศษ</th>
                                <th class="px-3 py-3 font-semibold">เพดาน</th>
                                <th class="px-3 py-3"><span class="sr-only">จัดการ</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in schedules" :key="s.id" class="border-b border-soft" :class="{ 'opacity-50': !s.is_active }">
                                <td class="px-3 py-2.5">
                                    <span class="rounded-lg px-2.5 py-1 font-semibold" :class="s.applies_to === 'special' ? 'bg-violet-700 text-white' : s.type === 'ot' ? 'bg-teal-50 text-teal-900' : 'bg-soft-2 text-navy-700'">{{ s.name }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-muted">{{ appliesLabel[s.applies_to] }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5">{{ s.start_time }}–{{ s.end_time }}</td>
                                <td class="px-3 py-2.5">{{ s.grace_minutes ? `${s.grace_minutes} นาที` : '—' }}</td>
                                <td class="px-3 py-2.5">{{ s.count_from }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5">{{ s.break_start ? `${s.break_start}–${s.break_end}` : '—' }}</td>
                                <td class="px-3 py-2.5">{{ s.special_from ? `หลัง ${s.special_from}` : '—' }}</td>
                                <td class="px-3 py-2.5">{{ roundingLabel[s.rounding] }}</td>
                                <td class="px-3 py-2.5">{{ hm(s.daily_cap_minutes) }}</td>
                                <td class="px-3 py-2.5 text-right"><button type="button" class="btn-outline h-9 px-3 text-sm" @click="edit(s)"><Icon name="pencil" :size="15" /> แก้ไข</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg font-bold">วันที่ใช้รูปแบบพิเศษ</h2>
            <form class="card flex flex-wrap items-end gap-3 p-4" @submit.prevent="addDay">
                <div>
                    <label for="sd-date" class="label">วันที่</label>
                    <input id="sd-date" v-model="dayForm.date" type="date" class="input" required>
                </div>
                <div class="min-w-[240px] flex-1">
                    <label for="sd-sch" class="label">รูปแบบ OT</label>
                    <select id="sd-sch" v-model="dayForm.schedule_id" class="input" required>
                        <option v-for="s in schedules.filter((s) => s.type === 'ot')" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <div class="min-w-[200px] flex-1">
                    <label for="sd-note" class="label">หมายเหตุ</label>
                    <input id="sd-note" v-model="dayForm.note" class="input" placeholder="เช่น ช่วงสอบปลายภาค">
                </div>
                <button type="submit" class="btn-navy" :disabled="dayForm.processing">เพิ่มวัน</button>
                <p v-if="dayForm.errors.date" class="field-error w-full">{{ dayForm.errors.date }}</p>
            </form>
            <div class="card divide-y divide-soft">
                <p v-if="!specialDays.length" class="px-4 py-4 text-sm text-muted">ยังไม่มีวันที่กำหนด</p>
                <div v-for="d in specialDays" :key="d.id" class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                    <div><b>{{ thaiDate(d.date, { weekday: true }) }}</b> · <span class="font-semibold text-violet-700">{{ d.schedule }}</span><span v-if="d.note" class="text-muted"> · {{ d.note }}</span></div>
                    <button type="button" class="btn-ghost h-9 px-2" :aria-label="`ยกเลิก ${d.date}`" @click="removeDay(d)"><Icon name="x" :size="17" /></button>
                </div>
            </div>
        </section>

        <!-- เวลายืดหยุ่น: ใช้กับคนที่ admin ติ๊กในหน้าสมาชิก · OT ไม่ยืดหยุ่น -->
        <section class="card max-w-3xl space-y-4 p-5">
            <div>
                <h2 class="text-lg font-bold">เวลายืดหยุ่น</h2>
                <p class="text-sm text-muted">เฉพาะคนที่ติ๊ก "เวลายืดหยุ่น" ในหน้าสมาชิก · นับชั่วโมงจริงตั้งแต่ 08:30 (ตามกฎหมาย) หักพัก 12:00–13:00 · OT ใช้รูปแบบเดิม ไม่ยืดหยุ่น</p>
            </div>
            <form class="flex flex-wrap items-end gap-3" @submit.prevent="saveFlex">
                <div>
                    <label for="fx-latest" class="label">เข้าไม่เกิน</label>
                    <input id="fx-latest" v-model="flexForm.latest_start" type="time" class="input w-36" required>
                    <p v-if="flexForm.errors.latest_start" class="field-error">{{ flexForm.errors.latest_start }}</p>
                </div>
                <div>
                    <label for="fx-hours" class="label">ชั่วโมงสุทธิขั้นต่ำ / วัน</label>
                    <input id="fx-hours" v-model="flexForm.min_hours" type="number" min="1" max="12" step="0.5" class="input w-36" required>
                    <p v-if="flexForm.errors.min_hours" class="field-error">{{ flexForm.errors.min_hours }}</p>
                </div>
                <button type="submit" class="btn-primary" :disabled="flexForm.processing">บันทึก</button>
            </form>
            <ul class="space-y-1 rounded-xl bg-soft px-4 py-3 text-sm">
                <li>เข้าหลัง {{ flexForm.latest_start }} น. (เกินผ่อนผัน) = <b>สาย</b> นับนาทีจาก {{ flexForm.latest_start }}</li>
                <li>ชั่วโมงสุทธิไม่ถึง {{ flexForm.min_hours }} ชม. = <b>ชม. ไม่ครบ</b> (นับเป็นออกก่อนในรายงาน)</li>
                <li>นับชั่วโมงได้ถึง {{ flexEnd }} น. · วันที่ลาครึ่งวันต้องการครึ่งหนึ่ง ({{ flexForm.min_hours / 2 }} ชม.)</li>
            </ul>
            <p class="text-sm"><span class="text-muted">ใช้อยู่ {{ flex.people.length }} คน:</span> {{ flex.people.length ? flex.people.join(', ') : 'ยังไม่มี' }}</p>
        </section>

    <Modal v-if="open" :title="form.id ? `แก้ไข ${form.name}` : 'เพิ่มรูปแบบเวลา'" @close="open = false">
        <form id="sch-form" class="grid gap-4 sm:grid-cols-4" @submit.prevent="save">
            <div class="sm:col-span-2">
                <label for="s-name" class="label">ชื่อรูปแบบ</label>
                <input id="s-name" v-model="form.name" class="input" required>
            </div>
            <div>
                <label for="s-type" class="label">ประเภท</label>
                <select id="s-type" v-model="form.type" class="input"><option value="normal">เวลาปกติ</option><option value="ot">OT</option></select>
            </div>
            <div>
                <label for="s-apply" class="label">ใช้กับ</label>
                <select id="s-apply" v-model="form.applies_to" class="input">
                    <option value="weekday">จ.–ศ.</option><option value="holiday">ส.–อา. / วันหยุด</option><option value="special">กำหนดรายวัน</option>
                </select>
            </div>
            <div><label for="s-start" class="label">เริ่ม</label><input id="s-start" v-model="form.start_time" type="time" class="input" required></div>
            <div><label for="s-end" class="label">สิ้นสุด</label><input id="s-end" v-model="form.end_time" type="time" class="input" required><p v-if="form.errors.end_time" class="field-error">{{ form.errors.end_time }}</p></div>
            <div><label for="s-count" class="label">เริ่มนับชั่วโมง</label><input id="s-count" v-model="form.count_from" type="time" class="input" required></div>
            <div><label for="s-grace" class="label">ผ่อนผัน (นาที)</label><input id="s-grace" v-model.number="form.grace_minutes" type="number" min="0" max="120" class="input"></div>
            <div><label for="s-bs" class="label">พักเริ่ม</label><input id="s-bs" v-model="form.break_start" type="time" class="input"></div>
            <div><label for="s-be" class="label">พักสิ้นสุด</label><input id="s-be" v-model="form.break_end" type="time" class="input"><p v-if="form.errors.break_end" class="field-error">{{ form.errors.break_end }}</p></div>
            <div v-if="form.type === 'ot'"><label for="s-sp" class="label">ช่วงพิเศษหลัง</label><input id="s-sp" v-model="form.special_from" type="time" class="input"></div>
            <div :class="form.type === 'ot' ? '' : 'sm:col-span-2'">
                <label for="s-cap" class="label">เพดาน (นาที/วัน)</label>
                <input id="s-cap" v-model="form.daily_cap_minutes" type="number" min="0" class="input" placeholder="เช่น 300 = 5 ชม.">
            </div>
            <div class="sm:col-span-2">
                <label for="s-round" class="label">การปัดเศษ</label>
                <select id="s-round" v-model="form.rounding" class="input">
                    <option value="minute">นับเป็นนาที (ไม่ปัด)</option><option value="half_hour">ปัดลงทีละครึ่งชั่วโมง</option><option value="hour">ปัดลงเป็นชั่วโมงเต็ม</option>
                </select>
            </div>
            <label class="flex items-center gap-2.5 text-[15px] sm:col-span-2 sm:self-end"><input v-model="form.is_active" type="checkbox" class="h-4 w-4 accent-navy-700"> ใช้งานอยู่</label>
            <p class="text-xs text-muted sm:col-span-4">มาก่อน "เริ่มนับชั่วโมง" ไม่นับ · มาไม่เกิน เริ่ม + ผ่อนผัน นับเหมือนตรงเวลา · ส่วนหลัง "ช่วงพิเศษหลัง" แยกเป็น OT พิเศษ · ปัดเศษแยกแต่ละส่วน แล้วจำกัดด้วยเพดาน</p>
        </form>
        <template #footer>
            <button type="button" class="btn-outline" @click="open = false">ยกเลิก</button>
            <button type="submit" form="sch-form" class="btn-primary" :disabled="form.processing">บันทึก</button>
        </template>
    </Modal>
    </div>
</template>
