<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import PersonPicker from '@/Components/PersonPicker.vue';
import { thaiDate } from '@/utils/thaiDate';

// บันทึกวันลาจากหน้ารายวัน (การบันทึกส่วนใหญ่ทำที่นี่) · ประวัติ/แก้ไข/ลบ อยู่ที่เมนู "ประวัติวันลา"
// person = { id, name } เมื่อกดจากแถวของคนนั้น (ล็อกชื่อ) · ไม่ส่ง = เลือกคนในหน้าต่าง
const props = defineProps({
    date: { type: String, required: true },
    person: { type: Object, default: null },
});
const emit = defineEmits(['close']);

const people = ref([]);
const types = ref([]);
const summary = ref(null);

const form = useForm({
    user_id: props.person?.id ?? '',
    leave_type_id: '',
    start_date: props.date,
    end_date: props.date,
    half_day: '',
    reason: '',
    attachment: null,
    allow_over: false,
    stay: 1,
});

const loadOptions = async (withPeople) => {
    const q = new URLSearchParams({ date: form.start_date, ...(form.user_id ? { user: form.user_id } : {}), ...(withPeople ? { people: 1 } : {}) });
    const res = await fetch(`/staff/leaves/options?${q}`, { headers: { Accept: 'application/json' } });
    if (!res.ok) return;
    const data = await res.json();
    if (data.people) people.value = data.people;
    types.value = data.types;
    summary.value = data.summary;
    form.leave_type_id ||= data.types[0]?.id ?? '';
};
onMounted(() => loadOptions(!props.person));
watch(() => form.user_id, (id) => id && loadOptions(false));

const type = computed(() => types.value.find((t) => t.id === form.leave_type_id));
const sameDay = computed(() => form.start_date && form.start_date === form.end_date);
watch(() => form.start_date, (v) => {
    if (v && (!form.end_date || form.end_date < v)) form.end_date = v;
});
watch(sameDay, (v) => !v && (form.half_day = ''));
watch(type, (t) => !t?.allow_half_day && (form.half_day = ''));

// นับวันทำการจากเซิร์ฟเวอร์ (ตัดวันหยุดให้)
const days = ref(null);
let timer;
watch(() => [form.start_date, form.end_date, form.half_day], () => {
    clearTimeout(timer);
    days.value = null;
    if (!form.start_date || !form.end_date || form.end_date < form.start_date) return;
    timer = setTimeout(async () => {
        const q = new URLSearchParams({ start_date: form.start_date, end_date: form.end_date, ...(form.half_day ? { half_day: form.half_day } : {}) });
        const res = await fetch(`/staff/leaves/preview?${q}`, { headers: { Accept: 'application/json' } });
        if (res.ok) days.value = (await res.json()).days;
    }, 250);
}, { immediate: true });

const balance = computed(() => summary.value?.find((s) => s.id === form.leave_type_id));
const after = computed(() => (balance.value?.counts_quota && days.value !== null ? balance.value.remaining - days.value : null));

const submit = () => form
    .transform((d) => ({ ...d, half_day: d.half_day || null, allow_over: d.allow_over ? 1 : 0 }))
    .post('/staff/leaves', { preserveScroll: true, forceFormData: true, onSuccess: () => emit('close') });
</script>

<template>
    <Modal :title="person ? `บันทึกลา · ${person.name}` : 'บันทึกลา'" width="max-w-xl" @close="emit('close')">
        <form id="leave-modal" class="space-y-4" @submit.prevent="submit">
            <div v-if="!person">
                <label for="lm-who" class="label">บุคลากร</label>
                <PersonPicker id="lm-who" v-model="form.user_id" :people="people" />
                <p v-if="form.errors.user_id" class="field-error">{{ form.errors.user_id }}</p>
            </div>

            <fieldset>
                <legend class="label">ประเภท</legend>
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
                    <label for="lm-d1" class="label">ตั้งแต่วันที่</label>
                    <input id="lm-d1" v-model="form.start_date" type="date" class="input" required>
                    <p v-if="form.errors.start_date" class="field-error">{{ form.errors.start_date }}</p>
                </div>
                <div>
                    <label for="lm-d2" class="label">ถึงวันที่</label>
                    <input id="lm-d2" v-model="form.end_date" type="date" class="input" :min="form.start_date" required>
                    <p v-if="form.errors.end_date" class="field-error">{{ form.errors.end_date }}</p>
                </div>
            </div>
            <div v-if="sameDay && type?.allow_half_day">
                <div class="flex flex-wrap gap-4 text-sm">
                    <label class="flex items-center gap-1.5"><input v-model="form.half_day" type="radio" value="" class="accent-navy-700"> เต็มวัน</label>
                    <label class="flex items-center gap-1.5"><input v-model="form.half_day" type="radio" value="am" class="accent-navy-700"> ครึ่งเช้า (มาทำงานบ่าย)</label>
                    <label class="flex items-center gap-1.5"><input v-model="form.half_day" type="radio" value="pm" class="accent-navy-700"> ครึ่งบ่าย (ทำงานเช้า)</label>
                </div>
                <p v-if="form.half_day" class="mt-1 text-xs text-muted">ลงเวลาที่ kiosk ตามปกติ ระบบคิดสาย/ออกก่อนจากครึ่งวันที่มาทำงานให้เอง</p>
                <p v-if="form.errors.half_day" class="field-error">{{ form.errors.half_day }}</p>
            </div>
            <div>
                <label for="lm-why" class="label">เหตุผล</label>
                <input id="lm-why" v-model="form.reason" class="input">
            </div>
            <div>
                <label for="lm-file" class="label">แนบไฟล์ (ถ้ามี) · PDF/JPG/PNG ไม่เกิน 5 MB</label>
                <input id="lm-file" type="file" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-soft file:px-3 file:py-2" @input="form.attachment = $event.target.files[0]">
                <p v-if="form.errors.attachment" class="field-error">{{ form.errors.attachment }}</p>
            </div>

            <div v-if="days !== null" class="space-y-1 rounded-xl bg-soft px-4 py-3 text-sm">
                <div>
                    <b>นับเป็น {{ days }} วันทำการ</b>
                    <span class="text-muted">· {{ thaiDate(form.start_date, { short: true }) }}{{ form.end_date !== form.start_date ? ` – ${thaiDate(form.end_date, { short: true })}` : '' }}</span>
                </div>
                <div v-if="after !== null" class="text-muted">{{ type?.name }}คงเหลือ {{ balance.remaining }} → <b :class="after < 0 ? 'text-red-700' : 'text-ink'">{{ after }} วัน</b> หลังบันทึก</div>
                <div v-else-if="type && !type.counts_quota" class="text-muted">{{ type.name }} ไม่หักโควต้า</div>
            </div>
            <div v-if="form.errors.allow_over" class="rounded-xl bg-orange-50 px-4 py-3 text-sm text-orange-900">
                {{ form.errors.allow_over }}
                <label class="mt-2 flex items-center gap-2 font-semibold"><input v-model="form.allow_over" type="checkbox" class="h-4 w-4 accent-navy-700"> ยืนยันบันทึกเกินสิทธิ์</label>
            </div>
        </form>
        <template #footer>
            <button type="button" class="btn-outline" @click="emit('close')">ยกเลิก</button>
            <button type="submit" form="leave-modal" class="btn-primary" :disabled="form.processing || !form.user_id">บันทึกลา</button>
        </template>
    </Modal>
</template>
