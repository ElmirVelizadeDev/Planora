<template>
    <main class="page-calendar" :aria-busy="calendar.loading.value">
        <CalendarHeader @add="addTask()" />
        <section class="calendar-panel">
            <div class="calendar-toolbar">
                <div class="calendar-period">
                    <button type="button" aria-label="Previous period" @click="calendar.move(-1)">‹</button>
                    <h2>{{ periodTitle }}</h2>
                    <button type="button" aria-label="Next period" @click="calendar.move(1)">›</button>
                </div>
                <div class="calendar-controls">
                    <button class="calendar-today" type="button" @click="goToday">Today</button>
                    <div class="calendar-views" role="group" aria-label="Calendar view">
                        <button v-for="option in views" :key="option.value" type="button" :class="{ active: calendar.view.value === option.value }" :aria-pressed="calendar.view.value === option.value" @click="calendar.view.value = option.value">{{ option.label }}</button>
                    </div>
                </div>
            </div>
            <div class="calendar-filters">
                <label>Status<select v-model="calendar.status.value"><option value="">All statuses</option><option value="pending">Pending</option><option value="in_progress">In progress</option><option value="completed">Completed</option></select></label>
                <label>Priority<select v-model="calendar.priority.value"><option value="">All priorities</option><option value="urgent">Urgent</option><option value="high">High</option><option value="medium">Medium</option><option value="low">Low</option></select></label>
                <button v-if="calendar.status.value || calendar.priority.value" class="calendar-reset" type="button" @click="clearFilters">Clear filters</button>
                <span class="calendar-result-count">{{ calendar.tasks.value.length }} tasks in view</span>
            </div>
            <div v-if="calendar.error.value" class="calendar-message is-error" role="alert">
                {{ calendar.error.value }} <button type="button" @click="calendar.load">Retry</button>
            </div>
            <div v-else-if="calendar.loading.value && !calendar.tasks.value.length" class="calendar-loading" role="status">Loading your calendar…</div>
            <template v-else>
                <section v-if="calendar.view.value === 'month'" class="month-view" aria-label="Monthly calendar">
                    <div class="weekday-row" role="row">
                        <span v-for="day in weekdays" :key="day" role="columnheader">{{ day }}</span>
                    </div>
                    <div class="month-grid" role="grid" :aria-label="periodTitle">
                        <div v-for="(week, weekIndex) in monthWeeks" :key="weekIndex" class="month-week" role="row">
                            <div v-for="day in week" :key="dateKey(day)" class="month-cell" :class="{ 'is-outside': day.getMonth() !== calendar.selectedDate.value.getMonth(), 'is-today': isSameDay(day, today), 'is-selected': isSameDay(day, calendar.selectedDate.value) }" role="gridcell">
                                <button class="month-date-button" type="button" :aria-label="`${formatDate(day)}, ${tasksFor(day).length} tasks`" :aria-pressed="isSameDay(day, calendar.selectedDate.value)" @click="calendar.selectedDate.value = day">
                                    <span class="day-number">{{ day.getDate() }}<small v-if="tasksFor(day).length">{{ tasksFor(day).length }}</small></span>
                                </button>
                                <button v-for="task in tasksFor(day).slice(0, 3)" :key="task.id" class="calendar-task-chip" :class="[`priority-${task.priority}`, `status-${task.status}`]" type="button" :aria-label="taskLabel(task, 'Open')" :title="taskLabel(task, 'Open')" @click="openTask(task)">
                                    <i aria-hidden="true"></i><span>{{ task.title }}</span>
                                </button>
                                <button v-if="tasksFor(day).length > 3" class="more-tasks" type="button" :aria-label="`Show ${tasksFor(day).length - 3} more tasks on ${formatDate(day)}`" @click="showMore(day)">+{{ tasksFor(day).length - 3 }} more</button>
                            </div>
                        </div>
                    </div>
                    <div v-if="!calendar.tasks.value.length" class="calendar-period-empty">
                        <strong>Your calendar is clear</strong>
                        <p>You don't have any scheduled tasks for this period.</p>
                        <button class="calendar-primary" type="button" @click="addTask(dateKey(calendar.selectedDate.value))">Add task</button>
                    </div>
                    <section class="mobile-selected-day" aria-live="polite" :aria-label="`Tasks for ${formatDate(calendar.selectedDate.value)}`">
                        <header class="mobile-selected-day__header">
                            <div><h3>{{ formatDate(calendar.selectedDate.value) }}</h3><span>{{ selectedTasks.length }} tasks</span></div>
                            <button class="calendar-primary" type="button" @click="addTask(dateKey(calendar.selectedDate.value))">Add task</button>
                        </header>
                        <TaskRows v-if="selectedTasks.length" :tasks="selectedTasks" @open="openTask" @edit="editTask" />
                        <div v-else class="calendar-empty calendar-empty--compact"><strong>No tasks scheduled</strong><p>Enjoy the free time or add a task for this date.</p></div>
                    </section>
                </section>
                <section v-else-if="calendar.view.value === 'week'" class="week-scroller" aria-label="Weekly calendar">
                    <div class="week-grid">
                        <div v-for="day in weekDays" :key="dateKey(day)" class="week-day" :class="{ 'is-today': isSameDay(day, today), 'is-selected': isSameDay(day, calendar.selectedDate.value) }">
                            <button class="week-date" type="button" :aria-pressed="isSameDay(day, calendar.selectedDate.value)" :aria-label="`Select ${formatDate(day)}, ${tasksFor(day).length} tasks`" @click="calendar.selectedDate.value = day"><strong>{{ formatDate(day, { weekday: 'short' }) }}</strong><b>{{ day.getDate() }}</b></button>
                            <div class="week-tasks">
                                <button v-for="task in tasksFor(day)" :key="task.id" class="week-task" type="button" :aria-label="taskLabel(task, 'Open')" @click="openTask(task)"><i :class="`priority-${task.priority}`" aria-hidden="true"></i><span>{{ task.title }}</span><small>{{ label(task.status) }}</small></button>
                                <span v-if="!tasksFor(day).length" class="week-empty">No tasks</span>
                            </div>
                        </div>
                    </div>
                    <div v-if="!calendar.tasks.value.length" class="calendar-period-empty"><strong>Your calendar is clear</strong><p>You don't have any scheduled tasks for this period.</p><button class="calendar-primary" type="button" @click="addTask(dateKey(calendar.selectedDate.value))">Add task</button></div>
                </section>
                <section v-else-if="calendar.view.value === 'day'" class="day-view">
                    <header><div><p>{{ formatDate(calendar.selectedDate.value, { weekday: 'long' }) }}</p><h3>{{ formatDate(calendar.selectedDate.value, { month: 'long', day: 'numeric', year: 'numeric' }) }}</h3></div><span>{{ selectedTasks.length }} tasks</span></header>
                    <div class="day-stats"><span>{{ statusCount('pending') }} pending</span><span>{{ statusCount('in_progress') }} in progress</span><span>{{ statusCount('completed') }} completed</span></div>
                    <TaskRows v-if="selectedTasks.length" :tasks="selectedTasks" @open="openTask" @edit="editTask" />
                    <div v-else class="calendar-empty"><strong>No tasks scheduled</strong><p>Enjoy the free time or add a new task to your schedule.</p><button class="calendar-primary" type="button" @click="addTask(dateKey(calendar.selectedDate.value))">Add task</button></div>
                </section>
                <section v-else class="agenda-view">
                    <p class="agenda-range-note">Overdue tasks shown here are limited to this loaded range, starting {{ formatDate(calendar.range.value.start_date, { month: 'short', day: 'numeric', year: 'numeric' }) }}. Older overdue tasks aren't included.</p>
                    <template v-for="group in agendaGroups" :key="group.key">
                        <header><h3>{{ group.label }}</h3><span>{{ group.tasks.length }} tasks</span></header>
                        <TaskRows :tasks="group.tasks" @open="openTask" @edit="editTask" />
                    </template>
                    <div v-if="!agendaGroups.length" class="calendar-empty"><strong>Your calendar is clear</strong><p>You don't have any scheduled tasks for this period.</p><button class="calendar-primary" type="button" @click="addTask(dateKey(calendar.selectedDate.value))">Add task</button></div>
                </section>
            </template>
        </section>
    </main>
</template>

<script setup>
import { computed, defineComponent, h } from 'vue';
import { useRouter } from 'vue-router';
import CalendarHeader from '../../../features/calendar/user/components/CalendarHeader.vue';
import { useCalendar } from '../../../features/calendar/user/composables/useCalendar.js';
import { addDays, dateKey, formatDate, isSameDay, mondayStart, monthGrid, todayKey } from '../../../features/calendar/user/utils/calendarDateUtils.js';
import TaskPriorityBadge from '../../../features/tasks/user/components/TaskPriorityBadge.vue';
import TaskStatusBadge from '../../../features/tasks/user/components/TaskStatusBadge.vue';

const router = useRouter();
const calendar = useCalendar();
const today = new Date();
const views = [{ label: 'Month', value: 'month' }, { label: 'Week', value: 'week' }, { label: 'Day', value: 'day' }, { label: 'Agenda', value: 'agenda' }];
const weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const monthWeeks = computed(() => {
    const days = monthGrid(calendar.selectedDate.value);
    return Array.from({ length: 6 }, (_, index) => days.slice(index * 7, index * 7 + 7));
});
const periodTitle = computed(() => {
    if (calendar.view.value === 'month') return formatDate(calendar.selectedDate.value, { month: 'long', year: 'numeric' });
    if (calendar.view.value === 'week') {
        const start = mondayStart(calendar.selectedDate.value);
        return `${formatDate(start, { month: 'short', day: 'numeric' })} – ${formatDate(addDays(start, 6), { month: 'short', day: 'numeric', year: 'numeric' })}`;
    }
    return calendar.view.value === 'day' ? formatDate(calendar.selectedDate.value) : 'Agenda';
});
const weekDays = computed(() => Array.from({ length: 7 }, (_, index) => addDays(mondayStart(calendar.selectedDate.value), index)));
const selectedTasks = computed(() => tasksFor(calendar.selectedDate.value));
const agendaGroups = computed(() => {
    const todayDate = todayKey();
    const tomorrow = dateKey(addDays(today, 1));
    const groups = new Map();

    for (const task of calendar.tasks.value) {
        let key;
        let labelText;
        if (task.due_date < todayDate && task.status !== 'completed') {
            key = 'overdue';
            labelText = 'Overdue';
        } else if (task.due_date < todayDate && task.status === 'completed') {
            key = 'completed-past';
            labelText = 'Completed';
        } else if (task.due_date === todayDate) {
            key = 'today';
            labelText = 'Today';
        } else if (task.due_date === tomorrow) {
            key = 'tomorrow';
            labelText = 'Tomorrow';
        } else {
            key = 'upcoming';
            labelText = 'Upcoming';
        }
        if (!groups.has(key)) groups.set(key, { key, label: labelText, tasks: [] });
        groups.get(key).tasks.push(task);
    }

    const order = ['overdue', 'completed-past', 'today', 'tomorrow', 'upcoming'];
    return order.flatMap(key => {
        const group = groups.get(key);
        if (!group) return [];
        group.tasks.sort((a, b) => a.due_date.localeCompare(b.due_date) || a.id - b.id);
        return [group];
    });
});

function tasksFor(day) {
    const key = dateKey(day);
    return calendar.tasks.value.filter(task => task.due_date === key);
}
function statusCount(value) { return selectedTasks.value.filter(task => task.status === value).length; }
function label(value) { return String(value || '').replaceAll('_', ' ').replace(/\b\w/g, character => character.toUpperCase()); }
function taskLabel(task, action) { return `${action} ${task.title}. Priority ${label(task.priority)}. Status ${label(task.status)}.`; }
function goToday() { calendar.selectedDate.value = new Date(); }
function addTask(date = dateKey(calendar.selectedDate.value)) { router.push({ name: 'tasks.create', query: { due_date: date } }); }
function clearFilters() { calendar.status.value = ''; calendar.priority.value = ''; }
function showMore(day) { calendar.selectedDate.value = day; calendar.view.value = 'day'; }
function openTask(task) { router.push({ name: 'tasks.show', params: { taskId: task.id } }); }
function editTask(task) { router.push({ name: 'tasks.edit', params: { taskId: task.id } }); }

const TaskRows = defineComponent({
    props: { tasks: { type: Array, required: true } },
    emits: ['open', 'edit'],
    setup(props, { emit }) {
        return () => h('div', { class: 'calendar-task-rows' }, props.tasks.map(task => h('article', { class: 'calendar-task-row', key: task.id }, [
            h('button', { class: 'row-title', 'aria-label': taskLabel(task, 'Open'), onClick: () => emit('open', task) }, task.title),
            h(TaskPriorityBadge, { priority: task.priority, 'aria-label': `Priority ${label(task.priority)}` }),
            h(TaskStatusBadge, { status: task.status, 'aria-label': `Status ${label(task.status)}` }),
            h('span', { class: 'row-due' }, task.due_date),
            task.is_owned ? h('button', { class: 'row-edit', 'aria-label': `Edit ${task.title}`, onClick: () => emit('edit', task) }, 'Edit') : null,
        ])));
    },
});
</script>

<style src="../../../../css/pages/user/calendar.css"></style>
