import React from 'react';
import Sidebar from '../components/Sidebar';

const Dashboard = () => (
  <div style={{ display: 'flex', minHeight: '100vh', background: '#f6f6fa' }}>
    {/* Sidebar with logos and menu */}
    <Sidebar />
    {/* Main dashboard area */}
    <main style={{ flex: 1, padding: 32 }}>
      <div style={{ marginBottom: 24, display: 'flex', alignItems: 'center', gap: 16 }}>
        <img src="/fullattend-logo.png" alt="FullAttend Logo" style={{ height: 40, cursor: 'pointer' }} onClick={() => window.location.href = '/login'} />
        <h1 style={{ fontSize: '2rem', fontWeight: 'bold', color: '#6d4aff', margin: 0 }}>Hi Arbinda!!</h1>
        <span style={{ fontSize: '1rem', color: '#a78bfa', marginLeft: 8 }}>FULL ATTEND</span>
      </div>
      <div style={{ marginTop: 32, gap: 24, marginBottom: 32 }}>
        {/* Overall Attendance Card */}
        <div className="card" style={{ flex: 1, alignItems: 'center', display: 'flex', flexDirection: 'column', background: '#ede9fe', position: 'relative' }}>
          <span style={{ fontSize: '1.1rem', fontWeight: '600', color: '#6d4aff', marginBottom: 8 }}>Overall Attendance</span>
          <span style={{ fontSize: '3rem', fontWeight: 'bold', color: '#6d4aff' }}>90%</span>
          {/* Attendance Status */}
          <div style={{ marginTop: 16, padding: '6px 18px', borderRadius: 20, background: '#fff', color: '#6d4aff', fontWeight: 'bold', fontSize: 16, boxShadow: '0 2px 8px rgba(0,0,0,0.05)', display: 'inline-block' }}>
           Today's Status: <span style={{ color: '#22c55e' }}>Attendance Done</span>
            {/* Change to '#ef4444' and 'Not Yet' if not done */}
          </div>
        </div>

        {/* Action Dashboard Card */}
        <div className="card" style={{ flex: 1, marginTop:50, alignItems: 'center', display: 'flex', flexDirection: 'column', background: '#ede9fe' }}>
          <span style={{ fontSize: '1.1rem', fontWeight: '600', color: '#6d4aff', marginBottom: 8 }}>Action Dashboard</span>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8, width: '100%', marginTop: 8 }}>
            <button className="button" style={{ background: '#a78bfa', color: '#fff', fontWeight: 'bold', borderRadius: 8, padding: 12, gridColumn: 'span 2', marginBottom: 8 }} onClick={() => alert('Face detection attendance coming soon!')}>Make Attendance Through Face</button>
            <div style={{ background: '#fff', borderRadius: 8, padding: 8, color: '#6d4aff', textAlign: 'center' }}>View Reports</div>
            <div style={{ background: '#fff', borderRadius: 8, padding: 8, color: '#6d4aff', textAlign: 'center' }}>Class Management</div>
          </div>
        </div>
      </div>
    </main>
  </div>
);

export default Dashboard;
