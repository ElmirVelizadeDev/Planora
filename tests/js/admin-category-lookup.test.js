import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { mock, test } from 'node:test';

const requests = [];
const apiModule = new URL('../../resources/js/shared/services/api.js', import.meta.url).href;
mock.module(apiModule, {
    exports: {
        default: {
            get: async (...args) => {
                requests.push(args);
                return { data: { data: [{ id: 12, name: 'Operations' }] } };
            },
        },
    },
});

const { getActiveAdminCategories } = await import('../../resources/js/features/categories/admin/services/adminCategoryApi.js');
const source = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');

test('the shared Admin category lookup preserves the active-category query and API response', async () => {
    const response = await getActiveAdminCategories();

    assert.deepEqual(response, { data: [{ id: 12, name: 'Operations' }] });
    assert.deepEqual(requests, [[
        '/admin/categories',
        { params: { status: 'active', per_page: 50, sort: 'name', direction: 'asc' } },
    ]]);
});

test('Admin Tasks and Projects use the shared lookup without duplicating endpoint logic', () => {
    const consumers = [
        '../../resources/js/features/tasks/admin/composables/useAdminTasks.js',
        '../../resources/js/pages/admin/tasks/AdminTaskForm.vue',
        '../../resources/js/pages/admin/projects/AdminProjectsIndex.vue',
        '../../resources/js/pages/admin/projects/AdminProjectsShow.vue',
    ];

    for (const path of consumers) assert.match(source(path), /getActiveAdminCategories/);

    assert.doesNotMatch(source('../../resources/js/features/tasks/admin/services/adminTaskApi.js'), /admin\/categories/);
    assert.doesNotMatch(source('../../resources/js/features/projects/admin/services/adminProjectApi.js'), /admin\/categories/);
});
