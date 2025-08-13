import React from 'react';
import { useNavigate } from 'react-router-dom';

const AdminLogin = () => {
  const navigate = useNavigate();
  return (
    <div className="centered-container">
      <div className="card">
        <img src="/fullattend-logo.png" alt="FullAttend Logo" className="logo" />
        <h2 className="title" style={{ textAlign: 'center', marginBottom: 16 }}>Admin Login</h2>
        <form
          onSubmit={e => {
            e.preventDefault();
            window.location.href = '/admin-dashboard';
          }}
        >
          <input type="text" placeholder="Username" className="input" />
          <input type="password" placeholder="Password" className="input" />
          <button type="submit" className="button">LOG IN</button>
        </form>
        <div style={{ marginTop: 8 }}>
          <a href="#" className="link">Forgotten Password?</a>
        </div>
      </div>
    </div>
  );
};

export default AdminLogin;
