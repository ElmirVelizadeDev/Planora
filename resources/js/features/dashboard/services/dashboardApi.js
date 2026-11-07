import api from '../../../shared/services/api.js';

export async function getAdminDashboard() {
    const [usersResponse, adminsResponse, activityResponse] = await Promise.all([
        api.get('/admin/users', { params: { per_page: 10, sort: 'newest' } }),
        api.get('/admin/users', { params: { role: 'admin', per_page: 10, sort: 'newest' } }),
        api.get('/admin/users/activity', { params: { per_page: 10 } }),
    ]);

    const users = usersResponse.data;
    const admins = adminsResponse.data;

    return {
        statistics: {
            totalUsers: Number(users.meta?.statistics?.total_users ?? 0),
            activeUsers: Number(users.meta?.statistics?.active_users ?? 0),
            inactiveUsers: Number(users.meta?.statistics?.inactive_users ?? 0),
            suspendedUsers: Number(users.meta?.statistics?.suspended_users ?? 0),
            bannedUsers: Number(users.meta?.statistics?.banned_users ?? 0),
            totalAdmins: Number(admins.meta?.statistics?.total_users ?? 0),
        },
        recentUsers: users.data ?? [],
        recentActivity: activityResponse.data.data ?? [],
    };
}
