<script setup>
import { onBeforeUnmount, onMounted } from 'vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    title: { type: String, required: true },
    width: { type: String, default: 'max-w-2xl' },
});
const emit = defineEmits(['close']);

const onKey = (e) => e.key === 'Escape' && emit('close');
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy-900/40 p-4 sm:p-8" @mousedown.self="emit('close')">
            <div class="card w-full p-0" :class="props.width" role="dialog" aria-modal="true" :aria-label="title">
                <div class="flex items-center justify-between border-b border-line px-5 py-4">
                    <h2 class="text-lg font-bold">{{ title }}</h2>
                    <button type="button" class="btn-ghost h-9 px-2" aria-label="ปิด" @click="emit('close')">
                        <Icon name="x" />
                    </button>
                </div>
                <div class="p-5">
                    <slot />
                </div>
                <div v-if="$slots.footer" class="flex flex-wrap justify-end gap-2 border-t border-line px-5 py-4">
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </Teleport>
</template>
