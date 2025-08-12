import React from 'react';
import Sidebar from '../components/Sidebar';

const AdminDashboard = () => (
  <div style={{ display: 'flex', minHeight: '100vh', background: '#f6f6fa' }}>
    {/* Sidebar with logos and menu */}
    <Sidebar isAdmin={true} />
    {/* Main dashboard area */}
    <main style={{ flex: 1, padding: 32 }}>
      <div style={{ marginBottom: 24, display: 'flex', alignItems: 'center', gap: 16 }}>
        <img src="/fullattend-logo.png" alt="FullAttend Logo" style={{ height: 40, cursor: 'pointer' }} onClick={() => window.location.href = '/login'} />
        <h1 style={{ fontSize: '2rem', fontWeight: 'bold', color: '#6d4aff', margin: 0 }}>Admin Dashboard</h1>
      </div>
      <div style={{ gap: 24, marginBottom: 32 }}>
        {/* Attendance Overview Card */}
        <div className="card" style={{ flex: 1, alignItems: 'center', display: 'flex', flexDirection: 'column', background: '#ede9fe' }}>
          <span style={{ fontSize: '1.1rem', fontWeight: '600', color: '#6d4aff', marginBottom: 8 }}>Attendance Overview</span>
          <span style={{ fontSize: '2rem', fontWeight: 'bold', color: '#6d4aff' }}>--</span>
        </div>
        {/* <div className="card" style={{ flex: 1, alignItems: 'center', display: 'flex', flexDirection: 'column', background: '#ede9fe' }}>
          <span style={{ fontSize: '1.1rem', fontWeight: '600', color: '#6d4aff', marginBottom: 8 }}>Class Management</span>
          <span style={{ fontSize: '2rem', fontWeight: 'bold', color: '#6d4aff' }}>--</span>
        </div> */}
        {/* Reports Card */}
        <div className="card" style={{ flex: 1, marginTop:40, alignItems: 'center', display: 'flex', flexDirection: 'column', background: '#ede9fe' }}>
          <span style={{ fontSize: '1.1rem', fontWeight: '600', color: '#6d4aff', marginBottom: 8 }}>Reports</span>
          <span style={{ fontSize: '2rem', fontWeight: 'bold', color: '#6d4aff' }}>--</span>
        </div>
      </div>
    </main>
  </div>
);

export default AdminDashboard;
