import api from '../../../../shared/services/api.js';

export async function getUserCategories(params = {}) {
    return (await api.get('/categories', { params })).data;
}

export async function getUserCategory(categoryId) {
    return (await api.get(`/categories/${categoryId}`)).data;
}
