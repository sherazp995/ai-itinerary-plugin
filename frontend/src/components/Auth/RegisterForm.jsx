import React, { useState } from 'react';
import { useAuth } from '../../hooks/useAuth';

export function RegisterForm() {
  const { register } = useAuth();
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      await register(firstName, lastName, email, password);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <form className="aip-form" onSubmit={handleSubmit}>
      {error && <p className="aip-form__error">{error}</p>}
      <div className="aip-form__row">
        <input type="text" placeholder="First name" value={firstName} onChange={(e) => setFirstName(e.target.value)} required />
        <input type="text" placeholder="Last name" value={lastName} onChange={(e) => setLastName(e.target.value)} required />
      </div>
      <input type="email" placeholder="Email" value={email} onChange={(e) => setEmail(e.target.value)} required />
      <input type="password" placeholder="Password (6+ chars)" value={password} onChange={(e) => setPassword(e.target.value)} required minLength={6} />
      <button type="submit" className="aip-form__btn" disabled={loading}>
        {loading ? 'Creating account...' : 'Create Account'}
      </button>
    </form>
  );
}
