import React from 'react';

const Login = () => {
  return (
    <div className="centered-container">
      <div style={{ display: 'flex', gap: 32, marginBottom: 24 }}>
        <img src="/fullattend-logo.png" alt="FullAttend Logo" className="logo" style={{ cursor: 'pointer', height: 80 }} onClick={() => window.location.href = '/login'} />
        <img src="/cihe-logo.png" alt="CIHE Logo" className="logo" style={{ cursor: 'pointer', height: 80 }} onClick={() => window.location.href = '/login'} />
      </div>
      <div style={{ width: 350 }}>
        <button className="button" onClick={() => window.location.href = '/student-login'}>LOG IN AS STUDENT</button>
        <button className="button" onClick={() => window.location.href = '/admin-login'}>LOGIN AS ADMIN</button>
      </div>
    </div>
  );
};

export default Login;
