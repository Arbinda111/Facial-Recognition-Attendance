import React from 'react';
import { useNavigate } from 'react-router-dom';

const StudentLogin = () => {
  const navigate = useNavigate();
  return (
    <div className="centered-container">
      <div className="card">
        <img src="/fullattend-logo.svg" alt="FullAttend Logo" className="logo" />
        <h2 className="title" style={{ textAlign: 'center', marginBottom: 16 }}>Student Login</h2>
        <form
          onSubmit={e => {
            e.preventDefault();
            window.location.href = '/dashboard';
          }}
        >
          <input type="text" placeholder="Username" className="input" />
          <input type="password" placeholder="Password" className="input" />
          <button type="submit" className="button">LOG IN</button>
        </form>
        <div style={{ marginTop: 8 }}>
          <a href="#" className="link" onClick={() => navigate('/forgot-password')}>Forgotten Password?</a>
        </div>
        <div style={{ marginTop: 16 }}>
          <button className="button" style={{ background: '#ede9fe', color: '#6d4aff' }} onClick={() => window.location.href = '/register'}>REGISTER</button>
        </div>
      </div>
    </div>
  );
};

export default StudentLogin;
