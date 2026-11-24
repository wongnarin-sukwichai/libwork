<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';

// Toast มุมขวาบน (แบบโปรเจกต์ sstoey): การ์ดขาว ไอคอนวงกลม หัวข้อ + ข้อความ ปุ่มปิด
// เลื่อนเข้าจากขวา ซ้อนได้สูงสุด 4 อัน แถบนับถอยหลังด้านล่าง หมดแล้วปิดเอง (เอาเมาส์ชี้ = หยุดเวลา)
// ฟังทุก Inertia visit ที่สำเร็จ ข้อความเดิมซ้ำ (เช่น กดบันทึกซ้ำ) ก็ยังขึ้นใหม่ · CSS อยู่ท้าย app.css (.toast-*)
const DURATION = { success: 4000, error: 6000 };
const variants = {
    success: { title: 'สำเร็จ', icon: 'circle-check', accent: 'bg-emerald-500', iconBox: 'bg-emerald-100 text-emerald-600' },
    error: { title: 'เกิดข้อผิดพลาด', icon: 'circle-alert', accent: 'bg-red-500', iconBox: 'bg-red-100 text-red-600' },
};

const page = usePage();
const toasts = ref([]);
let seq = 0;

const push = (type, text) => {
    toasts.value.push({ id: ++seq, type, text });
    if (toasts.value.length > 4) toasts.value.shift();
};
const dismiss = (id) => (toasts.value = toasts.value.filter((t) => t.id !== id));
const showFlash = (flash) => {
    if (flash?.success) push('success', flash.success);
    if (flash?.error) push('error', flash.error);
};

let off = null;
onMounted(() => {
    showFlash(page.props.flash);
    off = router.on('success', (event) => showFlash(event.detail.page.props.flash));
});
onBeforeUnmount(() => off?.());
</script>

<template>
    <!-- อยู่เหนือ modal (z-50) -->
    <div class="pointer-events-none fixed right-4 top-20 z-[110] flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-3" aria-live="polite">
        <TransitionGroup name="toast">
            <div
                v-for="t in toasts"
                :key="t.id"
                class="toast-card pointer-events-auto relative overflow-hidden rounded-2xl bg-white shadow-[0_18px_40px_-12px_rgba(15,30,58,0.35)] ring-1 ring-ink/5"
                role="status"
            >
                <div class="flex items-start gap-3 p-4 pr-10">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full" :class="variants[t.type].iconBox">
                        <Icon :name="variants[t.type].icon" :size="20" />
                    </span>
                    <div class="min-w-0 pt-0.5">
                        <p class="font-semibold text-ink">{{ variants[t.type].title }}</p>
                        <p class="mt-0.5 break-words text-sm text-muted">{{ t.text }}</p>
                    </div>
                </div>
                <button
                    type="button"
                    class="absolute right-3 top-3 grid size-7 place-items-center rounded-full text-navy-300 transition hover:bg-soft hover:text-ink"
                    aria-label="ปิด"
                    @click="dismiss(t.id)"
                >
                    <Icon name="x" :size="15" />
                </button>
                <!-- แถบนับถอยหลัง: หมดแล้วปิดเอง -->
                <div
                    class="toast-progress absolute bottom-0 left-0 h-1"
                    :class="variants[t.type].accent"
                    :style="{ animationDuration: `${DURATION[t.type]}ms` }"
                    @animationend="dismiss(t.id)"
                />
            </div>
        </TransitionGroup>
    </div>
</template>
