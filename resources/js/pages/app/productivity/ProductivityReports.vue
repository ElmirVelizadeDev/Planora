<template>
    <main class="user-report" :aria-busy="loading">
        <header class="user-report__header">
            <div><p class="user-report__eyebrow">YOUR WORKSPACE</p><h1>Productivity report</h1><p>Understand your task progress and workload over time.</p></div>
            <RouterLink class="user-report__primary" :to="{ name: 'tasks.create' }">Create task</RouterLink>
        </header>

        <section class="user-report__filters" aria-label="Report filters">
            <div class="user-report__presets" aria-label="Date range presets">
                <button type="button" @click="setPreset('7')">7 days</button>
                <button type="button" @click="setPreset('30')">30 days</button>
                <button type="button" @click="setPreset('month')">This month</button>
            </div>
            <label>From <input v-model="filters.start_date" type="date" :max="filters.end_date"></label>
            <label>To <input v-model="filters.end_date" type="date" :min="filters.start_date"></label>
            <label>Status <select v-model="filters.status" @change="applyFilters"><option value="">All statuses</option><option value="pending">Pending</option><option value="in_progress">In progress</option><option value="completed">Completed</option></select></label>
            <label>Priority <select v-model="filters.priority" @change="applyFilters"><option value="">All priorities</option><option value="urgent">Urgent</option><option value="high">High</option><option value="medium">Medium</option><option value="low">Low</option></select></label>
            <button class="user-report__apply" type="button" :disabled="loading" @click="applyFilters">Apply</button>
            <button class="user-report__reset" type="button" :disabled="loading" @click="resetFilters">Reset</button>
        </section>

        <div v-if="error" class="user-report__feedback is-error" role="alert"><span>{{ error }}</span><button type="button" @click="load">Try again</button></div>
        <div v-else-if="loading && !report" class="user-report__feedback" role="status"><span class="user-report__spinner" aria-hidden="true"></span>Loading your report…</div>
        <template v-else-if="report">
            <p class="user-report__scope">Showing tasks created {{ periodLabel }}<template v-if="filters.status || filters.priority"> with the selected status and priority filters</template>. Owned and assigned tasks are included once.</p>

            <section class="user-report__kpis" aria-label="Task report metrics">
                <article><span>Tasks in period</span><strong>{{ number(report.metrics.total) }}</strong><small>Created in selected dates</small></article>
                <article><span>Completed</span><strong>{{ number(report.metrics.completed) }}</strong><small>{{ report.metrics.completion_rate }}% completion rate</small></article>
                <article><span>In progress</span><strong>{{ number(report.metrics.in_progress) }}</strong><small>Currently being worked on</small></article>
                <article><span>Overdue</span><strong>{{ number(report.metrics.overdue) }}</strong><small>Uncompleted with past due dates</small></article>
            </section>

            <section class="user-report__charts">
                <article class="user-report__panel user-report__trend">
                    <header><div><h2>Task creation</h2><p>Tasks created per day · {{ report.period.start_date }} – {{ report.period.end_date }}</p></div><span class="user-report__total">{{ number(report.metrics.total) }} total</span></header>
                    <div v-if="!report.metrics.total" class="user-report__empty">No tasks were created in this period.</div>
                    <div v-else class="user-report__bars" role="img" :aria-label="trendDescription">
                        <div v-for="point in chartTrend" :key="point.date" class="user-report__bar-wrap" :title="`${formatDate(point.date)}: ${point.count} tasks`">
                            <span class="user-report__bar-count">{{ point.count || '' }}</span><i :style="{ height: `${point.height}%` }"></i><small v-if="point.label">{{ point.label }}</small>
                        </div>
                    </div>
                </article>

                <article class="user-report__panel">
                    <header><div><h2>Status breakdown</h2><p>Current status of tasks in this period</p></div></header>
                    <div class="user-report__breakdown">
                        <div v-for="item in report.status_breakdown" :key="item.key" class="user-report__breakdown-row"><span class="user-report__dot" :class="`is-${item.key}`"></span><span>{{ label(item.key) }}</span><strong>{{ number(item.count) }}</strong><div class="user-report__track"><i :class="`is-${item.key}`" :style="{ width: `${percentage(item.count, report.metrics.total)}%` }"></i></div></div>
                    </div>
                </article>

                <article class="user-report__panel">
                    <header><div><h2>Priority mix</h2><p>Distribution across selected tasks</p></div></header>
                    <div class="user-report__breakdown">
                        <div v-for="item in report.priority_breakdown" :key="item.key" class="user-report__breakdown-row"><span class="user-report__dot" :class="`is-${item.key}`"></span><span>{{ label(item.key) }}</span><strong>{{ number(item.count) }}</strong><div class="user-report__track"><i :class="`is-${item.key}`" :style="{ width: `${percentage(item.count, report.metrics.total)}%` }"></i></div></div>
                    </div>
                </article>
            </section>

            <section class="user-report__panel user-report__recent">
                <header><div><h2>Recently created tasks</h2><p>Latest tasks matching this report</p></div><RouterLink :to="{ name: 'tasks.index' }">View all tasks</RouterLink></header>
                <div v-if="!report.recent_tasks.length" class="user-report__empty">No tasks match the selected report filters. Try a different date range or clear the status and priority filters.</div>
                <div v-else class="user-report__table-wrap"><table><thead><tr><th scope="col">Task</th><th scope="col">Status</th><th scope="col">Priority</th><th scope="col">Due date</th><th scope="col">Created</th></tr></thead><tbody><tr v-for="task in report.recent_tasks" :key="task.id"><td><RouterLink :to="{ name: 'tasks.show', params: { taskId: task.id } }">{{ task.title }}</RouterLink></td><td><span class="user-report__tag" :class="`is-${task.status}`">{{ label(task.status) }}</span></td><td><span class="user-report__tag" :class="`is-${task.priority}`">{{ label(task.priority) }}</span></td><td>{{ task.due_date ? formatDate(task.due_date) : '—' }}</td><td>{{ formatDate(task.created_at) }}</td></tr></tbody></table></div>
            </section>
        </template>
    </main>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useUserTaskReport } from '../../../features/reports/user/composables/useUserTaskReport.js';

const { filters, report, loading, error, periodLabel, load, setPreset, applyFilters, resetFilters } = useUserTaskReport();
const number = (value) => new Intl.NumberFormat().format(Number(value) || 0);
const label = (value) => ({ in_progress: 'In progress' }[value] || String(value || '').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()));
const formatDate = (value) => {
    const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!match) return '—';
    return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' }).format(new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3]))));
};
const percentage = (value, total) => total ? Math.min(100, Math.max(0, Number(value) / Number(total) * 100)) : 0;
const chartTrend = computed(() => {
    const points = report.value?.creation_trend || [];
    const max = Math.max(1, ...points.map((point) => Number(point.count) || 0));
    const stride = Math.max(1, Math.ceil(points.length / 10));
    return points.map((point, index) => ({ ...point, height: Math.max(3, (Number(point.count) || 0) / max * 100), label: index % stride === 0 || index === points.length - 1 ? point.date.slice(5) : '' }));
});
const trendDescription = computed(() => `Daily task creation from ${report.value?.period.start_date} to ${report.value?.period.end_date}. ${chartTrend.value.map((point) => `${point.date}: ${point.count}`).join('; ')}`);
onMounted(load);
</script>

<style src="../../../../css/pages/app/productivity/user-reports.css"></style>
