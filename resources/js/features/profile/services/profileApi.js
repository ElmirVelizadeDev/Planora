import api from '../../../shared/services/api.js';

export const updateCurrentProfile = (attributes) => api.patch('/user/profile', attributes);
export const getCurrentProfileOverview = () => api.get('/user/overview');

export const updateCurrentAvatar = (avatar) => {
    const payload = new FormData();
    payload.append('avatar', avatar);
    return api.post('/user/avatar', payload);
};

export const removeCurrentAvatar = () => api.delete('/user/avatar');

export const changeCurrentPassword = (attributes) => api.put('/user/password', attributes);
