import React from 'react';
import Sidebar from '../components/Sidebar';

const Profile = () => {
  const user = {
    name: 'Arbinda Dangi',
    studentId: 'CIHE240369',
    email: 'cihe240369@student.cihe.edu.au',
    attendance: '92%',
    image: '/Octocat.png',
    phone: '+61 123 456 7',
    address: '123 Crown Street, Sydney, Australia',
  };

  return (
    <div style={{ display: 'flex', minHeight: '100vh', background: '#f6f6fa' }}>
      <Sidebar />
      <main style={{ flex: 1, padding: 32 }}>
        <h1 style={{ fontSize: '2rem', fontWeight: 'bold', color: '#6d4aff', marginBottom: 24 }}>Profile</h1>
        <div className="card" style={{ padding: 24, borderRadius: 8, background: '#fff', boxShadow: '0 2px 8px rgba(0,0,0,0.1)' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 16, marginBottom: 24 }}>
            <img src={user.image} alt="Profile" style={{ height: 80, width: 80, borderRadius: '50%', objectFit: 'cover' }} />
            <div>
              <h2 style={{ fontSize: '1.5rem', fontWeight: 'bold', margin: 0 }}>{user.name}</h2>
              <p style={{ margin: 0, color: '#6d4aff' }}>{user.studentId}</p>
            </div>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
            <div>
              <p style={{ fontWeight: 'bold', marginBottom: 4 }}>Email:</p>
              <p>{user.email}</p>
            </div>
            <div>
              <p style={{ fontWeight: 'bold', marginBottom: 4 }}>Phone:</p>
              <p>{user.phone}</p>
            </div>
            <div>
              <p style={{ fontWeight: 'bold', marginBottom: 4 }}>Address:</p>
              <p>{user.address}</p>
            </div>
            <div>
              <p style={{ fontWeight: 'bold', marginBottom: 4 }}>Attendance:</p>
              <p>{user.attendance}</p>
            </div>
          </div>
        </div>
      </main>
    </div>
  );
};

export default Profile;
