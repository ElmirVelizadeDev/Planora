import api from '../../../shared/services/api.js';

export async function getAdminDashboard() {
    const [usersResponse, adminsResponse, activityResponse] = await Promise.all([
        api.get('/admin/users', { params: { per_page: 10, sort: 'newest' } }),
        api.get('/admin/users', { params: { role: 'admin', per_page: 10, sort: 'newest' } }),
        api.get('/admin/users/activity', { params: { per_page: 10 } }),
    ]);

    const users = usersResponse.data;
    const admins = adminsResponse.data;
    const statistics = users.meta?.statistics;
    const adminStatistics = admins.meta?.statistics;

    if (!statistics || !adminStatistics || !Array.isArray(users.data) || !Array.isArray(activityResponse.data.data)) {
        throw new Error('The dashboard API returned an incomplete response.');
    }

    const requiredStatistics = ['total_users', 'active_users', 'inactive_users', 'suspended_users', 'banned_users'];
    if (requiredStatistics.some((key) => !Number.isFinite(Number(statistics[key])))
        || !Number.isFinite(Number(adminStatistics.total_users))) {
        throw new Error('The dashboard API returned invalid user statistics.');
    }

    return {
        statistics: {
            totalUsers: Number(statistics.total_users),
            activeUsers: Number(statistics.active_users),
            inactiveUsers: Number(statistics.inactive_users),
            suspendedUsers: Number(statistics.suspended_users),
            bannedUsers: Number(statistics.banned_users),
            totalAdmins: Number(adminStatistics.total_users),
        },
        recentUsers: users.data ?? [],
        recentActivity: activityResponse.data.data ?? [],
    };
}
