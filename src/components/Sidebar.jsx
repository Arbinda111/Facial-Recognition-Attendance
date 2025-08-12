import React from 'react';

const Sidebar = ({ isAdmin }) => (
  <aside style={{ background: '#ede9fe', width: 240, minHeight: '100vh', padding: 24, display: 'flex', flexDirection: 'column' }}>
    <div style={{ marginBottom: 32 }}>
      <img src="/fullattend-logo.svg" alt="FullAttend Logo" style={{ height: 64, display: 'block', margin: '0 auto' }} />
      <h2 style={{ fontSize: '1.25rem', fontWeight: 'bold', textAlign: 'center', marginTop: 8, color: '#6d4aff' }}>FULL ATTEND</h2>
    </div>
    <nav style={{ flex: 1 }}>
      <ul style={{ listStyle: 'none', padding: 0 }}>
        <li style={{ marginBottom: 16 }}><a href="/dashboard" style={{ color: '#6d4aff', fontWeight: 600, textDecoration: 'none' }}>Dashboard</a></li>
        <li style={{ marginBottom: 16 }}><a href="/reports" style={{ color: '#6d4aff', fontWeight: 600, textDecoration: 'none' }}>Reports</a></li>
        {!isAdmin && (
          <li style={{ marginBottom: 16 }}><a href="/profile" style={{ color: '#6d4aff', fontWeight: 600, textDecoration: 'none' }}>Profile</a></li>
        )}
        <li><a href="/logout" style={{ color: '#6d4aff', fontWeight: 600, textDecoration: 'none' }}>Logout</a></li>
      </ul>
    </nav>
  </aside>
);

export default Sidebar;
