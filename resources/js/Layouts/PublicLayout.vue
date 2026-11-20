<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppMark from '@/Components/AppMark.vue';
import FlashToast from '@/Components/FlashToast.vue';
import Icon from '@/Components/Icon.vue';

// หน้าสาธารณะ: แถบหัวสีน้ำเงิน ปุ่มเข้าสู่ระบบมุมขวาบน
const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <header class="flex flex-wrap items-center justify-between gap-3 bg-navy-700 px-4 py-3.5 text-white shadow-[0_4px_16px_rgba(15,30,58,0.18)] sm:px-8">
            <Link href="/" class="flex items-center gap-3">
                <AppMark tone="sun" :size="42" />
                <div>
                    <div class="text-lg font-bold leading-tight">ระบบลงเวลาปฏิบัติงาน</div>
                    <div class="text-[13px] text-navy-300">{{ page.props.app.org }}</div>
                </div>
            </Link>
            <Link v-if="user" href="/dashboard" class="btn bg-white text-ink hover:bg-soft">
                <Icon name="house" :size="18" /> เข้าสู่หน้าของฉัน
            </Link>
            <Link v-else-if="page.component !== 'Auth/Login'" href="/login" class="btn bg-white text-ink hover:bg-soft">
                <Icon name="log-in" :size="18" /> เข้าสู่ระบบ
            </Link>
        </header>
        <div class="flex-1">
            <slot />
        </div>
        <FlashToast />
    </div>
</template>
