<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import Modal from '@/Components/Modal.vue';

// ตรวจยอดยกมาปีงบ (หน้า 9): ร่างโดยระบบ → HR ตรวจ/แก้ (มีเหตุผล) → ยืนยัน → บุคลากรเห็นยอดใหม่
const props = defineProps({ fy: Number, status: String, rows: Array, inquiries: Array });
const page = usePage();
const isAdmin = computed(() => page.props.auth.user.role === 'admin');

const reviewed = computed(() => props.rows.filter((r) => r.reviewed).length);
const capped = computed(() => props.rows.filter((r) => r.system_carried < Math.max(0, r.prev_remaining)).length);
const edited = computed(() => props.rows.filter((r) => r.carried !== r.system_carried).length);
const openInquiries = computed(() => props.inquiries.filter((i) => !i.reply).length);
const locked = computed(() => props.status === 'confirmed');
const fmt = (n) => (n === null || n === undefined ? '—' : Number.isInteger(n) ? n : Number(n).toFixed(1));

const goFy = (fy) => router.get('/staff/carry-over', { fy }, { preserveScroll: true });
const generate = () => router.post('/staff/carry-over/generate', { fy: props.fy }, { preserveScroll: true });
const confirmAll = () => confirm(`ยืนยันยอดยกมาปีงบ ${props.fy}? บุคลากรจะเห็นยอดใหม่ทันที`) && router.post('/staff/carry-over/confirm', { fy: props.fy }, { preserveScroll: true });

const editing = ref(null);
const form = useForm({ carried: 0, reason: '', reviewed: true });
const open = (r) => {
    editing.value = r;
    form.clearErrors();
    Object.assign(form, { carried: r.carried, reason: '', reviewed: true });
};
const save = () => form.put(`/staff/carry-over/${editing.value.id}`, { preserveScroll: true, onSuccess: () => (editing.value = null) });
const markReviewed = (r) => router.put(`/staff/carry-over/${r.id}`, { carried: r.carried, reviewed: !r.reviewed }, { preserveScroll: true });

const replying = ref(null);
const replyForm = useForm({ reply: '' });
const sendReply = () => replyForm.post(`/staff/carry-over/inquiries/${replying.value.id}/reply`, { preserveScroll: true, onSuccess: () => { replying.value = null; replyForm.reset(); } });

const unlockForm = useForm({ fy: props.fy, reason: '' });
const unlocking = ref(false);
const unlock = () => unlockForm.post('/staff/carry-over/unlock', { preserveScroll: true, onSuccess: () => (unlocking.value = false) });
</script>

<template>
    <Head title="ยอดยกมาปีงบ" />
    <div class="space-y-5">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-[26px] font-bold">ตรวจยอดยกมา · ลาพักผ่อน</h1>
                <p class="text-[15px] text-muted">ปีงบประมาณ {{ fy - 1 }} → {{ fy }} · ใช้กรอกยอดเริ่มต้นตอนเปิดระบบได้ด้วย</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn-outline w-11 px-0" aria-label="ปีงบก่อนหน้า" @click="goFy(fy - 1)"><Icon name="chevron-left" :size="18" /></button>
                <span class="min-w-[90px] text-center font-bold">ปีงบ {{ fy }}</span>
                <button type="button" class="btn-outline w-11 px-0" aria-label="ปีงบถัดไป" @click="goFy(fy + 1)"><Icon name="chevron-right" :size="18" /></button>
                <span class="pill px-3.5 py-1.5 text-sm" :class="locked ? 'bg-green-50 text-green-800' : status === 'none' ? 'bg-soft text-muted' : 'bg-sun-100 text-sun-800'">
                    {{ locked ? 'ยืนยันแล้ว · บุคลากรเห็นยอดนี้' : status === 'none' ? 'ยังไม่มีร่าง' : 'ร่าง · บุคลากรยังไม่เห็นยอดใหม่' }}
                </span>
            </div>
        </div>

        <div v-if="status === 'none'" class="card p-8 text-center">
            <p class="text-lg font-semibold">ยังไม่มีร่างยอดของปีงบ {{ fy }}</p>
            <p class="mt-1 text-muted">ระบบคำนวณให้อัตโนมัติทุกวันที่ 1 ต.ค. หรือกดคำนวณตอนนี้ได้เลย</p>
            <button type="button" class="btn-primary mt-4" @click="generate">คำนวณร่างยอด</button>
        </div>

        <template v-else>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-2xl bg-navy-700 px-4 py-3.5 text-white shadow-card"><div class="text-[13px] text-navy-300">ตรวจแล้ว</div><div class="text-[26px] font-bold">{{ reviewed }} / {{ rows.length }} คน</div></div>
                <div class="rounded-2xl bg-orange-50 px-4 py-3.5 text-orange-900 shadow-card"><div class="text-[13px]">ถูกตัดตามเพดาน</div><div class="text-[26px] font-bold">{{ capped }} คน</div></div>
                <div class="card px-4 py-3.5"><div class="text-[13px] text-muted">HR ปรับยอด</div><div class="text-[26px] font-bold">{{ edited }} คน</div></div>
                <div class="rounded-2xl bg-sun-500 px-4 py-3.5 text-black shadow-card"><div class="text-[13px]">คำขอตรวจสอบรอตอบ</div><div class="text-[26px] font-bold">{{ openInquiries }} รายการ</div></div>
            </div>

            <section class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[960px] border-collapse text-sm">
                        <thead>
                            <tr class="bg-navy-700 text-left text-white">
                                <th class="px-2.5 py-3 font-semibold">ชื่อ</th>
                                <th class="px-2.5 py-3 font-semibold">อายุงาน</th>
                                <th class="px-2.5 py-3 font-semibold">ปีก่อน: ยกมา + สิทธิ์ − ใช้</th>
                                <th class="px-2.5 py-3 font-semibold">คงเหลือ</th>
                                <th class="px-2.5 py-3 font-semibold">สิทธิ์ปีนี้</th>
                                <th class="px-2.5 py-3 font-semibold">เพดานรวม</th>
                                <th class="px-2.5 py-3 font-semibold">ยกมา</th>
                                <th class="px-2.5 py-3 font-semibold">หมายเหตุ</th>
                                <th class="px-2.5 py-3 font-semibold">ตรวจ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in rows" :key="r.id" class="border-b border-soft">
                                <td class="px-2.5 py-2.5"><div class="font-semibold">{{ r.name }}</div><div class="text-xs text-muted">{{ r.employment_type }}</div></td>
                                <td class="px-2.5 py-2.5">{{ r.service_years !== null ? `${r.service_years} ปี` : '—' }}</td>
                                <td class="px-2.5 py-2.5 text-muted">{{ fmt(r.prev_carried) }} + {{ fmt(r.prev_entitled) }} − {{ fmt(r.prev_used) }}</td>
                                <td class="px-2.5 py-2.5">{{ fmt(r.prev_remaining) }}</td>
                                <td class="px-2.5 py-2.5">{{ fmt(r.entitled) }}</td>
                                <td class="px-2.5 py-2.5">{{ fmt(r.cap) }}</td>
                                <td class="px-2.5 py-2.5">
                                    <button
                                        type="button"
                                        class="min-w-14 rounded-lg px-2.5 py-1.5 text-left font-bold"
                                        :class="r.carried !== r.system_carried ? 'border-2 border-navy-700 bg-soft-2' : r.system_carried < r.prev_remaining ? 'border border-orange-300 bg-orange-50 text-orange-900' : 'border border-line-strong'"
                                        :disabled="locked"
                                        :aria-label="`แก้ยอดยกมาของ ${r.name}`"
                                        @click="open(r)"
                                    >{{ fmt(r.carried) }}</button>
                                </td>
                                <td class="px-2.5 py-2.5 text-[13px]">
                                    <span v-if="r.adjustments.length" class="text-navy-700">ปรับ {{ r.adjustments[0].amount > 0 ? '+' : '' }}{{ fmt(r.adjustments[0].amount) }}: {{ r.adjustments[0].reason }}</span>
                                    <span v-else-if="r.system_carried < r.prev_remaining" class="text-orange-800">ตัดตามเพดาน (−{{ fmt(r.prev_remaining - r.system_carried) }})</span>
                                    <span v-else class="text-muted">—</span>
                                </td>
                                <td class="px-2.5 py-2.5">
                                    <button type="button" class="h-9 w-9 rounded-lg font-bold" :class="r.reviewed ? 'bg-green-50 text-green-800' : 'border border-line-strong text-muted'" :disabled="locked" :aria-label="`ตรวจแล้ว ${r.name}`" :aria-pressed="r.reviewed" @click="markReviewed(r)">{{ r.reviewed ? '✓' : '' }}</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="grid gap-4 lg:grid-cols-2">
                <section class="card p-5">
                    <h2 class="mb-2.5 text-[17px] font-bold">คำขอตรวจสอบจากบุคลากร</h2>
                    <p v-if="!inquiries.length" class="text-sm text-muted">ไม่มีคำขอ</p>
                    <div v-for="q in inquiries" :key="q.id" class="mb-2 space-y-1.5 rounded-xl bg-page px-4 py-3 text-sm">
                        <div><b>{{ q.name }}</b> · {{ q.at }}</div>
                        <div class="text-muted">“{{ q.message }}”</div>
                        <div v-if="q.reply" class="text-navy-700">ตอบแล้ว: {{ q.reply }}</div>
                        <div v-else class="text-right"><button type="button" class="btn-outline h-9 px-3 text-sm" @click="replying = q">ตอบกลับ</button></div>
                    </div>
                </section>
                <section class="card flex flex-col justify-between gap-3 p-5">
                    <div class="text-sm">
                        <div class="font-bold">{{ locked ? 'ยอดปีงบนี้ยืนยันแล้ว' : rows.length - reviewed ? `ยังไม่ได้ตรวจ ${rows.length - reviewed} คน` : 'ตรวจครบแล้ว' }}</div>
                        <div class="text-muted">กดยืนยันแล้วยอดจะถูกล็อก และบุคลากรเห็นยอดยกมาใหม่ทันที · แก้ภายหลังต้องให้ admin ปลดล็อกพร้อมเหตุผล</div>
                    </div>
                    <div class="flex flex-wrap justify-end gap-2">
                        <button v-if="!locked" type="button" class="btn-outline" @click="generate">คำนวณร่างใหม่</button>
                        <button v-if="!locked" type="button" class="btn-primary" :disabled="rows.length - reviewed > 0" @click="confirmAll">ยืนยันยอดยกมา</button>
                        <button v-if="locked && isAdmin" type="button" class="btn-outline" @click="unlocking = true">ปลดล็อก (admin)</button>
                    </div>
                </section>
            </div>
        </template>
    </div>

    <Modal v-if="editing" :title="`ยอดยกมา · ${editing.name}`" width="max-w-md" @close="editing = null">
        <form id="co" class="space-y-4" @submit.prevent="save">
            <p class="text-sm text-muted">ระบบคำนวณได้ {{ fmt(editing.system_carried) }} วัน (ปีก่อนคงเหลือ {{ fmt(editing.prev_remaining) }}, เพดานรวม {{ fmt(editing.cap) }}, สิทธิ์ปีนี้ {{ fmt(editing.entitled) }})</p>
            <div>
                <label for="co-v" class="label">ยอดยกมา (วัน)</label>
                <input id="co-v" v-model="form.carried" type="number" step="0.5" min="0" class="input" required>
            </div>
            <div>
                <label for="co-r" class="label">เหตุผลการแก้ไข (บุคลากรจะเห็นข้อความนี้)</label>
                <input id="co-r" v-model="form.reason" class="input" placeholder="เช่น แก้ใบลาวันที่ 3 ก.ย. ที่บันทึกซ้ำ">
                <p v-if="form.errors.reason" class="field-error">{{ form.errors.reason }}</p>
            </div>
            <div v-if="editing.adjustments.length" class="space-y-1 text-sm">
                <div class="font-semibold">ประวัติ</div>
                <div v-for="(a, i) in editing.adjustments" :key="i" class="text-muted">{{ a.at }} · {{ a.amount > 0 ? '+' : '' }}{{ fmt(a.amount) }} · {{ a.reason }} · {{ a.by }}</div>
            </div>
        </form>
        <template #footer>
            <button type="button" class="btn-outline" @click="editing = null">ยกเลิก</button>
            <button type="submit" form="co" class="btn-primary" :disabled="form.processing">บันทึกและทำเครื่องหมายตรวจแล้ว</button>
        </template>
    </Modal>

    <Modal v-if="replying" :title="`ตอบ ${replying.name}`" width="max-w-md" @close="replying = null">
        <form id="rp" @submit.prevent="sendReply">
            <p class="mb-3 text-sm text-muted">“{{ replying.message }}”</p>
            <label for="rp-t" class="label">คำตอบ</label>
            <textarea id="rp-t" v-model="replyForm.reply" rows="3" class="input h-auto py-2.5" required />
        </form>
        <template #footer>
            <button type="button" class="btn-outline" @click="replying = null">ยกเลิก</button>
            <button type="submit" form="rp" class="btn-primary" :disabled="replyForm.processing">ส่งคำตอบ</button>
        </template>
    </Modal>

    <Modal v-if="unlocking" title="ปลดล็อกยอดยกมา" width="max-w-md" @close="unlocking = false">
        <form id="ul" @submit.prevent="unlock">
            <label for="ul-r" class="label">เหตุผล</label>
            <input id="ul-r" v-model="unlockForm.reason" class="input" required>
        </form>
        <template #footer>
            <button type="button" class="btn-outline" @click="unlocking = false">ยกเลิก</button>
            <button type="submit" form="ul" class="btn-navy" :disabled="unlockForm.processing">ปลดล็อก</button>
        </template>
    </Modal>
</template>
