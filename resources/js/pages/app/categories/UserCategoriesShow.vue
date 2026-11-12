<template>
    <UserCategoryDetails :category="detail.category.value" :loading="detail.loading.value" :error="detail.error.value" @retry="detail.load" />
</template>

<script setup>
import { useRoute } from 'vue-router';
import { useUserCategoryDetails } from '../../../features/categories/user/composables/useUserCategoryDetails.js';
import UserCategoryDetails from '../../../features/categories/user/components/UserCategoryDetails.vue';

const route = useRoute();
const detail = useUserCategoryDetails(() => route.params.categoryId);
</script>

<style src="../../../../css/features/categories/user/details.css"></style>
<style src="../../../../css/features/categories/user/shared.css"></style>
