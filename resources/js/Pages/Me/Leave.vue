<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import Modal from '@/Components/Modal.vue';
import { thaiDate } from '@/utils/thaiDate';
import { url } from '@/utils/url';

// สิทธิ์วันลาของฉัน (หน้า 4): แจกแจงยอด · ประวัติการลา · ประวัติการปรับยอด · แจ้ง HR ตรวจสอบ
const props = defineProps({
    fy: Number,
    employmentType: String,
    serviceYears: Number,
    confirmed: Boolean,
    confirmedAt: String,
    summary: Array,
    records: Array,
    adjustments: Array,
    inquiries: Array,
});

const carry = computed(() => props.summary.filter((s) => s.carry_over && s.counts_quota));
const others = computed(() => props.summary.filter((s) => !(s.carry_over && s.counts_quota)));
const pending = computed(() => props.summary.some((s) => s.pending));

const asking = ref(false);
const form = useForm({ message: '', fy: props.fy });
const send = () => form.post('/me/leave/inquiries', { preserveScroll: true, onSuccess: () => { asking.value = false; form.reset('message'); } });

const goFy = (delta) => router.get('/me/leave', { fy: props.fy + delta }, { preserveScroll: true });
const range = (r) => (r.start_date === r.end_date ? thaiDate(r.start_date, { short: true }) : `${thaiDate(r.start_date, { short: true })} – ${thaiDate(r.end_date, { short: true })}`);
const fmt = (n) => (Number.isInteger(n) ? n : n.toFixed(1));
</script>

<template>
    <Head title="สิทธิ์วันลา" />
    <div class="space-y-5">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-[26px] font-bold">สิทธิ์วันลาของฉัน</h1>
                <p class="text-[15px] text-muted">
                    ปีงบประมาณ {{ fy }} (1 ต.ค. {{ fy - 1 }} – 30 ก.ย. {{ fy }})<span v-if="employmentType"> · {{ employmentType }}</span><span v-if="serviceYears !== null"> · อายุงาน {{ serviceYears }} ปี</span>
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-outline w-11 px-0" aria-label="ปีงบก่อนหน้า" @click="goFy(-1)"><Icon name="chevron-left" :size="18" /></button>
                <button type="button" class="btn-outline w-11 px-0" aria-label="ปีงบถัดไป" @click="goFy(1)"><Icon name="chevron-right" :size="18" /></button>
                <button type="button" class="btn-outline border-navy-700 text-navy-700" @click="asking = true">แจ้ง HR ตรวจสอบยอด</button>
            </div>
        </div>

        <div v-if="confirmed" class="flex items-center gap-2.5 rounded-xl bg-green-50 px-4 py-3 text-sm text-green-900">
            <Icon name="check" :size="18" /> HR ยืนยันยอดยกมาแล้ว เมื่อ {{ confirmedAt }}
        </div>
        <div v-else-if="pending" class="rounded-xl bg-sun-100 px-4 py-3 text-sm text-sun-800">ยอดยกมาจากปีก่อนอยู่ระหว่าง HR ตรวจสอบ · ตอนนี้แสดงเฉพาะสิทธิ์ของปีนี้</div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section v-for="s in carry" :key="s.id" class="card p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold">{{ s.name }}</h2>
                    <span v-if="s.cap" class="text-[13px] text-muted">ยกมา + สิทธิ์ปีนี้ รวมไม่เกิน {{ fmt(s.cap) }} วัน</span>
                </div>
                <dl class="mt-3.5 space-y-2 text-[15px]">
                    <div class="flex justify-between"><dt class="text-muted">ยกมาจากปีงบ {{ fy - 1 }}</dt><dd>{{ s.pending ? 'รอตรวจสอบ' : `${fmt(s.carried_over)} วัน` }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">+ สิทธิ์ปีนี้</dt><dd>{{ fmt(s.entitled) }} วัน</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">− ใช้ไปแล้ว</dt><dd>{{ fmt(s.used) }} วัน</dd></div>
                    <div class="flex justify-between border-t border-line pt-2.5 text-lg font-bold"><dt>คงเหลือ</dt><dd class="text-navy-700">{{ fmt(s.remaining) }} วัน</dd></div>
                </dl>
            </section>
            <section class="card space-y-3 p-5">
                <h2 class="text-lg font-bold">ลาประเภทอื่น</h2>
                <div v-for="s in others" :key="s.id" class="flex items-center justify-between border-b border-soft py-1.5 text-[15px] last:border-0">
                    <span>{{ s.name }}</span>
                    <span class="text-muted">{{ s.counts_quota ? `ใช้ไป ${fmt(s.used)} จาก ${fmt(s.entitled)} วัน` : `${s.times} ครั้ง · ${fmt(s.used)} วัน (ไม่หักโควต้า)` }}</span>
                </div>
            </section>
        </div>

        <section class="card p-5">
            <h2 class="mb-3 text-lg font-bold">ประวัติการลา ปีงบ {{ fy }}</h2>
            <p v-if="!records.length" class="text-sm text-muted">ยังไม่มีการลาในปีงบนี้</p>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[640px] border-collapse text-sm">
                    <thead><tr class="bg-soft text-left"><th class="px-2 py-2.5 font-semibold">ประเภท</th><th class="px-2 py-2.5 font-semibold">วันที่</th><th class="px-2 py-2.5 font-semibold">จำนวน</th><th class="px-2 py-2.5 font-semibold">เหตุผล</th><th class="px-2 py-2.5" /></tr></thead>
                    <tbody>
                        <tr v-for="r in records" :key="r.id" class="border-b border-line">
                            <td class="px-2 py-2.5">{{ r.type }}</td>
                            <td class="whitespace-nowrap px-2 py-2.5">{{ range(r) }}</td>
                            <td class="px-2 py-2.5">{{ r.half_label ?? `${fmt(r.days)} วัน` }}</td>
                            <td class="px-2 py-2.5 text-muted">{{ r.reason ?? '—' }}</td>
                            <td class="px-2 py-2.5 text-right"><a v-if="r.has_attachment" :href="url(`/leave-attachments/${r.id}`)" target="_blank" class="text-sm underline">ไฟล์แนบ</a></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="adjustments.length || inquiries.length" class="card space-y-3 p-5">
            <h2 class="text-lg font-bold">ประวัติการปรับยอดและการสอบถาม</h2>
            <div v-for="(a, i) in adjustments" :key="`a${i}`" class="flex flex-wrap gap-x-4 gap-y-1 rounded-xl bg-page px-4 py-3 text-sm">
                <b>{{ a.at }}</b><span>{{ a.type }} {{ a.amount > 0 ? '+' : '' }}{{ fmt(a.amount) }} วัน</span><span class="text-muted">โดย {{ a.by }} · {{ a.reason }}</span>
            </div>
            <div v-for="q in inquiries" :key="q.id" class="rounded-xl border border-line px-4 py-3 text-sm">
                <div><b>{{ q.at }}</b> · ฉันแจ้ง: {{ q.message }}</div>
                <div class="mt-1" :class="q.reply ? 'text-navy-700' : 'text-muted'">{{ q.reply ? `HR ตอบ: ${q.reply}` : 'รอ HR ตอบ' }}</div>
            </div>
        </section>
    </div>

    <Modal v-if="asking" title="แจ้ง HR ตรวจสอบยอดวันลา" width="max-w-lg" @close="asking = false">
        <form id="ask" @submit.prevent="send">
            <label for="msg" class="label">รายละเอียด</label>
            <textarea id="msg" v-model="form.message" rows="4" class="input h-auto py-2.5" placeholder="เช่น ใบลาวันที่ 3 ก.ย. น่าจะถูกบันทึกซ้ำ" required />
            <p v-if="form.errors.message" class="field-error">{{ form.errors.message }}</p>
        </form>
        <template #footer>
            <button type="button" class="btn-outline" @click="asking = false">ยกเลิก</button>
            <button type="submit" form="ask" class="btn-primary" :disabled="form.processing">ส่งถึง HR</button>
        </template>
    </Modal>
</template>
