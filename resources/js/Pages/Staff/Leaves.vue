<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import { thaiDate } from '@/utils/thaiDate';
import { url } from '@/utils/url';

// บันทึกวันลา (หน้า 7): HR บันทึกโดยตรง ไม่มีขั้นตอนอนุมัติ
const props = defineProps({
    people: Array,
    types: Array,
    selected: { type: Object, default: null },
    fiscalYear: Number,
    recent: Array,
});

const search = ref('');
const filtered = computed(() => {
    const q = search.value.trim();
    return q ? props.people.filter((p) => p.name.includes(q) || (p.code ?? '').includes(q)) : props.people;
});

const editing = ref(null);
const blank = () => ({
    user_id: props.selected?.id ?? '',
    leave_type_id: props.types[0]?.id ?? '',
    start_date: '',
    end_date: '',
    half_day: '',
    reason: '',
    attachment: null,
    allow_over: false,
});
const form = useForm(blank());

const pickPerson = (id) => router.get('/staff/leaves', id ? { user: id } : {}, { preserveScroll: true, preserveState: false });
watch(() => form.user_id, (id) => id && id !== props.selected?.id && pickPerson(id));

const type = computed(() => props.types.find((t) => t.id === form.leave_type_id));
const sameDay = computed(() => form.start_date && form.start_date === form.end_date);
watch(() => form.start_date, (v) => {
    if (v && (!form.end_date || form.end_date < v)) form.end_date = v;
});
watch(sameDay, (v) => !v && (form.half_day = ''));

// นับวันทำการจากเซิร์ฟเวอร์ (ตัดวันหยุดให้)
const days = ref(null);
let t;
watch(() => [form.start_date, form.end_date, form.half_day], () => {
    clearTimeout(t);
    days.value = null;
    if (!form.start_date || !form.end_date || form.end_date < form.start_date) return;
    t = setTimeout(async () => {
        const q = new URLSearchParams({ start_date: form.start_date, end_date: form.end_date, ...(form.half_day ? { half_day: form.half_day } : {}) });
        const res = await fetch(`/staff/leaves/preview?${q}`, { headers: { Accept: 'application/json' } });
        if (res.ok) days.value = (await res.json()).days;
    }, 250);
});

const balance = computed(() => props.selected?.summary.find((s) => s.id === form.leave_type_id));
const after = computed(() => (balance.value?.counts_quota && days.value !== null ? balance.value.remaining - days.value + (editing.value?.leave_type_id === form.leave_type_id ? editing.value.days : 0) : null));

const submit = () => {
    const options = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            editing.value = null;
            form.defaults(blank());
            form.reset();
        },
    };
    const data = form.transform((d) => ({ ...d, half_day: d.half_day || null, allow_over: d.allow_over ? 1 : 0 }));
    if (editing.value) data.post(`/staff/leaves/${editing.value.id}`, options);
    else data.post('/staff/leaves', options);
};

const edit = (r) => {
    editing.value = r;
    Object.assign(form, { user_id: r.user_id, leave_type_id: r.leave_type_id, start_date: r.start_date, end_date: r.end_date, half_day: r.half_day ?? '', reason: r.reason ?? '', attachment: null, allow_over: false });
    window.scrollTo({ top: 0, behavior: 'smooth' });
};
const cancelEdit = () => {
    editing.value = null;
    form.reset();
};
const remove = (r) => confirm(`ลบรายการ${r.type}ของ ${r.name} (${thaiDate(r.start_date, { short: true })})?`) && router.delete(`/staff/leaves/${r.id}`, { preserveScroll: true });

const range = (r) => (r.start_date === r.end_date ? thaiDate(r.start_date, { short: true }) : `${thaiDate(r.start_date, { short: true })} – ${thaiDate(r.end_date, { short: true })}`);
const typeClass = (c) => (c === 'duty' ? 'bg-sun-100 text-sun-800' : 'bg-soft-2 text-navy-700');
</script>

<template>
    <Head title="บันทึกวันลา" />
    <div class="space-y-5">
        <div>
            <h1 class="text-[26px] font-bold">บันทึกวันลา</h1>
            <p class="text-[15px] text-muted">HR บันทึกลงระบบโดยตรง ไม่มีขั้นตอนอนุมัติ · ปีงบประมาณ {{ fiscalYear }}</p>
        </div>

        <div class="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,420px)]">
            <form class="card space-y-4 p-5" @submit.prevent="submit">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold">{{ editing ? 'แก้ไขใบลา' : 'ใบลาใหม่' }}</h2>
                    <button v-if="editing" type="button" class="btn-ghost h-9 text-sm" @click="cancelEdit">ยกเลิกการแก้ไข</button>
                </div>
                <div>
                    <label for="who" class="label">บุคลากร</label>
                    <div class="flex gap-2">
                        <input v-model="search" class="input max-w-[180px]" placeholder="ค้นชื่อ/รหัส" aria-label="ค้นหาบุคลากร">
                        <select id="who" v-model="form.user_id" class="input" required :disabled="!!editing">
                            <option value="" disabled>เลือกบุคลากร ({{ filtered.length }})</option>
                            <option v-for="p in filtered" :key="p.id" :value="p.id">{{ p.name }}{{ p.code ? ` (${p.code})` : '' }} · {{ p.department ?? '—' }}</option>
                        </select>
                    </div>
                    <p v-if="form.errors.user_id" class="field-error">{{ form.errors.user_id }}</p>
                </div>

                <fieldset>
                    <legend class="label">ประเภทการลา</legend>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="t in types"
                            :key="t.id"
                            type="button"
                            class="h-10 rounded-[10px] px-3.5 text-sm"
                            :class="form.leave_type_id === t.id ? 'bg-navy-700 font-bold text-white' : 'border border-line-strong bg-white'"
                            :aria-pressed="form.leave_type_id === t.id"
                            @click="form.leave_type_id = t.id"
                        >{{ t.name }}</button>
                    </div>
                </fieldset>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="d1" class="label">ตั้งแต่วันที่</label>
                        <input id="d1" v-model="form.start_date" type="date" class="input" required>
                        <p v-if="form.errors.start_date" class="field-error">{{ form.errors.start_date }}</p>
                    </div>
                    <div>
                        <label for="d2" class="label">ถึงวันที่</label>
                        <input id="d2" v-model="form.end_date" type="date" class="input" :min="form.start_date" required>
                        <p v-if="form.errors.end_date" class="field-error">{{ form.errors.end_date }}</p>
                    </div>
                </div>
                <div v-if="sameDay && type?.allow_half_day" class="flex flex-wrap gap-4 text-sm">
                    <label class="flex items-center gap-1.5"><input v-model="form.half_day" type="radio" value="" class="accent-navy-700"> เต็มวัน</label>
                    <label class="flex items-center gap-1.5"><input v-model="form.half_day" type="radio" value="am" class="accent-navy-700"> ครึ่งเช้า</label>
                    <label class="flex items-center gap-1.5"><input v-model="form.half_day" type="radio" value="pm" class="accent-navy-700"> ครึ่งบ่าย</label>
                </div>
                <div>
                    <label for="why" class="label">เหตุผล</label>
                    <input id="why" v-model="form.reason" class="input">
                </div>
                <div>
                    <label for="file" class="label">แนบไฟล์ (ถ้ามี) · PDF/JPG/PNG ไม่เกิน 5 MB</label>
                    <input id="file" type="file" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-soft file:px-3 file:py-2" @input="form.attachment = $event.target.files[0]">
                    <p v-if="form.errors.attachment" class="field-error">{{ form.errors.attachment }}</p>
                </div>

                <div v-if="days !== null" class="space-y-1 rounded-xl bg-soft px-4 py-3 text-sm">
                    <div><b>นับเป็น {{ days }} วันทำการ</b> (ไม่รวมเสาร์-อาทิตย์และวันหยุด)</div>
                    <div v-if="after !== null" class="text-muted">{{ type?.name }}คงเหลือ {{ balance.remaining }} → <b :class="after < 0 ? 'text-red-700' : 'text-ink'">{{ after }} วัน</b> หลังบันทึก</div>
                    <div v-else-if="type && !type.counts_quota" class="text-muted">{{ type.name }} ไม่หักโควต้า</div>
                </div>
                <div v-if="form.errors.allow_over" class="rounded-xl bg-orange-50 px-4 py-3 text-sm text-orange-900">
                    {{ form.errors.allow_over }}
                    <label class="mt-2 flex items-center gap-2 font-semibold"><input v-model="form.allow_over" type="checkbox" class="h-4 w-4 accent-navy-700"> ยืนยันบันทึกเกินสิทธิ์</label>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="submit" class="btn-primary" :disabled="form.processing">{{ editing ? 'บันทึกการแก้ไข' : 'บันทึกใบลา' }}</button>
                </div>
            </form>

            <div class="space-y-4">
                <section v-if="selected" class="rounded-[18px] bg-navy-700 p-5 text-white shadow-card">
                    <div class="text-sm text-navy-300">สิทธิ์คงเหลือของ {{ selected.name }} · ปีงบ {{ fiscalYear }}</div>
                    <div class="mt-3 grid grid-cols-2 gap-2.5">
                        <div v-for="s in selected.summary.filter((s) => s.counts_quota || s.times)" :key="s.id" class="rounded-xl bg-white/10 px-3 py-2.5">
                            <div class="text-[13px] text-navy-200">{{ s.name }}</div>
                            <div class="text-[22px] font-bold">{{ s.counts_quota ? `${s.remaining} วัน` : `${s.times} ครั้ง · ${s.used} วัน` }}</div>
                        </div>
                    </div>
                </section>

                <section class="card p-5">
                    <h2 class="mb-2 text-lg font-bold">{{ selected ? `รายการของ ${selected.name}` : 'บันทึกล่าสุด' }}</h2>
                    <p v-if="!recent.length" class="text-sm text-muted">ยังไม่มีรายการ</p>
                    <div v-for="r in recent" :key="r.id" class="flex items-center gap-2.5 border-b border-soft py-2.5 last:border-0">
                        <span class="pill" :class="typeClass(r.category)">{{ r.type }}</span>
                        <div class="min-w-0 flex-1 text-sm leading-snug">
                            <div class="font-semibold">{{ r.name }}</div>
                            <div class="text-muted">{{ range(r) }} · {{ r.half_label ?? `${r.days} วัน` }}<span v-if="r.reason"> · {{ r.reason }}</span></div>
                        </div>
                        <a v-if="r.has_attachment" :href="url(`/leave-attachments/${r.id}`)" target="_blank" class="btn-ghost h-8 px-2 text-xs" aria-label="ไฟล์แนบ">ไฟล์</a>
                        <button type="button" class="btn-ghost h-8 px-2" :aria-label="`แก้ไข ${r.name}`" @click="edit(r)"><Icon name="pencil" :size="16" /></button>
                        <button type="button" class="btn-ghost h-8 px-2" :aria-label="`ลบ ${r.name}`" @click="remove(r)"><Icon name="x" :size="16" /></button>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
