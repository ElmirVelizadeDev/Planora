import { ref } from 'vue';

export const hasRuntimeError = ref(false);

export function reportRuntimeError() {
    hasRuntimeError.value = true;
}
