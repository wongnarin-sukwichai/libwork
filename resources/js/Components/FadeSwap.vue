<script setup>
// สลับเนื้อหาแบบ fade (เช่น กดแท็บ) · ลูกต้องเป็น element เดียว มี v-if/v-else หรือ :key ที่เปลี่ยนตามแท็บ
</script>

<template>
    <Transition
        mode="out-in"
        enter-active-class="transition-opacity duration-200 ease-out motion-reduce:transition-none"
        leave-active-class="transition-opacity duration-150 ease-in motion-reduce:transition-none"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
    >
        <slot />
    </Transition>
</template>
