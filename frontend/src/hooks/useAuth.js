import { useAppStore } from '../stores/appStore';
import { api } from '../api/client';

export function useAuth() {
  const { setUser, setShowAuth } = useAppStore();

  const login = async (email, password) => {
    const data = await api.post('/auth/login', { email, password });
    const status = await api.get('/user/status');
    setUser(status);
    setShowAuth(false);
    return data;
  };

  const register = async (firstName, lastName, email, password) => {
    const data = await api.post('/auth/register', {
      first_name: firstName,
      last_name: lastName,
      email,
      password,
    });
    const status = await api.get('/user/status');
    setUser(status);
    setShowAuth(false);
    return data;
  };

  const googleAuth = async (credential) => {
    const data = await api.post('/auth/google', { credential });
    const status = await api.get('/user/status');
    setUser(status);
    setShowAuth(false);
    return data;
  };

  const logout = async () => {
    await api.post('/auth/logout');
    setUser({ logged_in: false, has_premium: false, free_limit: 3, free_used: 0, free_remaining: 3 });
  };

  return { login, register, googleAuth, logout };
}
