import React from 'react';

const ForgotPassword = () => (
  <div className="centered-container" style={{ background: '#f6f6fa', minHeight: '100vh' }}>
    <div style={{ display: 'flex', flexDirection: 'row', width: 700, borderRadius: 20, overflow: 'hidden', boxShadow: '0 2px 16px rgba(0,0,0,0.08)' }}>
      {/* Left Panel */}
      <div style={{ background: '#a78bfa', width: 240, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: 32 }}>
        <img src="/cihe-logo.png" alt="CIHE Logo" style={{ height: 64, marginBottom: 16 }} />
        <img src="/fullattend-logo.png" alt="FullAttend Logo" style={{ height: 64, marginBottom: 16, cursor: 'pointer' }} onClick={() => window.location.href = '/login'} />
        <div style={{ color: '#fff', fontWeight: 'bold', textAlign: 'center', fontSize: 18, marginBottom: 8 }}>CROWN INSTITUTE OF HIGHER EDUCATION</div>
      </div>
      {/* Right Panel */}
      <div style={{ background: '#fff', flex: 1, padding: 40, display: 'flex', flexDirection: 'column', justifyContent: 'center' }}>
        <div style={{ fontWeight: 'bold', fontSize: 22, color: '#6d4aff', marginBottom: 16 }}>Reset Password</div>
        <form style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
          <input type="email" placeholder="Student Email" className="input" style={{ borderRadius: 8, border: '1px solid #a78bfa', padding: 12 }} />
          <input type="text" placeholder="Student ID" className="input" style={{ borderRadius: 8, border: '1px solid #a78bfa', padding: 12 }} />
          <input type="password" placeholder="New Password" className="input" style={{ borderRadius: 8, border: '1px solid #a78bfa', padding: 12 }} />
          <input type="password" placeholder="Confirm Password" className="input" style={{ borderRadius: 8, border: '1px solid #a78bfa', padding: 12 }} />
          <button className="button" style={{ marginTop: 8, background: '#a78bfa', color: '#fff', fontWeight: 'bold', borderRadius: 8, padding: 12 }}>Reset Password</button>
        </form>
      </div>
    </div>
  </div>
);

export default ForgotPassword;
