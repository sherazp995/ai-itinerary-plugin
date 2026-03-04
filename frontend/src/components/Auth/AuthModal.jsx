import React, { useState } from 'react';
import { useAppStore } from '../../stores/appStore';
import { LoginForm } from './LoginForm';
import { RegisterForm } from './RegisterForm';
import { GoogleSignIn } from './GoogleSignIn';

export function AuthModal() {
  const { setShowAuth } = useAppStore();
  const [mode, setMode] = useState('login');

  return (
    <div className="aip-modal-overlay" onClick={() => setShowAuth(false)}>
      <div className="aip-modal" onClick={(e) => e.stopPropagation()}>
        <button className="aip-modal__close" onClick={() => setShowAuth(false)}>
          &times;
        </button>

        <h2 className="aip-modal__title">
          {mode === 'login' ? 'Sign In' : 'Create Account'}
        </h2>

        <GoogleSignIn />

        <div className="aip-modal__divider">
          <span>or</span>
        </div>

        {mode === 'login' ? <LoginForm /> : <RegisterForm />}

        <p className="aip-modal__switch">
          {mode === 'login' ? (
            <>Don't have an account? <button onClick={() => setMode('register')}>Sign up</button></>
          ) : (
            <>Already have an account? <button onClick={() => setMode('login')}>Sign in</button></>
          )}
        </p>
      </div>
    </div>
  );
}
