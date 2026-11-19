import api from '../../../shared/services/api.js';

export async function searchWorkspace(query, { signal, limit = 5 } = {}) {
    const response = await api.get('/search', { params: { q: query, limit }, signal });
    return response.data.data;
}
