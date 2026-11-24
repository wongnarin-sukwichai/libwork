<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import FlashToast from '@/Components/FlashToast.vue';
import Icon from '@/Components/Icon.vue';
import LoginModal from '@/Components/LoginModal.vue';
import { stripBase, url } from '@/utils/url';

// หน้าสาธารณะ: แถบหัวสีน้ำเงิน โลโก้ MSU ปุ่มเข้าสู่ระบบมุมขวาบน (เปิด modal)
const page = usePage();
const user = computed(() => page.props.auth.user);

// /login = หน้าแรก + เปิด modal ไว้ (server ส่ง openLogin มา)
const loginOpen = ref(false);
watch(() => page.props.openLogin, (open) => open && !user.value && (loginOpen.value = true), { immediate: true });
const closeLogin = () => {
    loginOpen.value = false;
    // ปิดแล้วถ้ายังอยู่ที่ /login ให้ URL กลับเป็นหน้าแรก (กดรีเฟรชจะได้ไม่เด้ง modal อีก)
    if (stripBase(window.location.pathname) === '/login') router.get('/', {}, { replace: true, preserveScroll: true });
};

// [DEV ONLY] ทางลัดเข้าระบบเป็น admin/staff (แสดงเฉพาะ APP_ENV=local) — ลบออกก่อนขึ้น host
const devLogin = (role) => router.post(`/dev/login/${role}`);
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <header class="flex flex-wrap items-center justify-between gap-3 bg-navy-700 px-4 py-3.5 text-white shadow-[0_4px_16px_rgba(15,30,58,0.18)] sm:px-8">
            <Link href="/" class="flex items-center gap-3">
                <img :src="url('/img/logos/library.png')" alt="MSU Library" class="h-11 w-auto">
                <div class="border-l border-white/25 pl-3">
                    <div class="text-lg font-bold leading-tight">ระบบลงเวลาปฏิบัติงาน</div>
                    <div class="text-[13px] text-navy-300">{{ page.props.app.org }}</div>
                </div>
            </Link>
            <div class="flex flex-wrap items-center gap-2">
                <!-- [DEV ONLY] ทางลัดทดสอบ — ลบออกก่อนขึ้น host -->
                <div v-if="page.props.app.dev_login" class="flex items-center gap-1.5 rounded-xl border border-dashed border-sun-500/70 px-2 py-1">
                    <span class="px-1 text-xs text-sun-500">ทดสอบ</span>
                    <button type="button" class="btn h-9 bg-sun-500 px-3 text-sm text-black" @click="devLogin('admin')">admin</button>
                    <button type="button" class="btn h-9 bg-sun-500 px-3 text-sm text-black" @click="devLogin('staff')">staff</button>
                </div>
                <Link v-if="user" href="/dashboard" class="btn bg-white text-ink hover:bg-soft">
                    <Icon name="house" :size="18" /> เข้าสู่หน้าของฉัน
                </Link>
                <button v-else type="button" class="btn bg-white text-ink hover:bg-soft" @click="loginOpen = true">
                    <Icon name="log-in" :size="18" /> เข้าสู่ระบบ
                </button>
            </div>
        </header>
        <div data-page-content class="flex-1">
            <slot />
        </div>
        <LoginModal v-if="loginOpen && !user" @close="closeLogin" />
        <FlashToast />
    </div>
</template>
