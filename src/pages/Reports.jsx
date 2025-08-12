import React, { useState } from 'react';
import Sidebar from '../components/Sidebar';

const sampleData = [
  { name: 'Arbinda Dangi', studentId: 'CIHE240369', attendance: '92%', image: '/Octocat.png', email: 'cihe240369@student.cihe.edu.au' },
  { name: 'Sudip Sharma', studentId: 'CIHE240370', attendance: '86%', image: '/Octocat.png', email: 'cihe240370@student.cihe.edu.au' },
  { name: 'Sample', studentId: 'Sample', attendance: 'Sample', image: '', email: 'Sample' },
  { name: 'Sample', studentId: 'Sample', attendance: 'Sample', image: '', email: 'Sample' },
];

const Reports = () => {
  const [filterType, setFilterType] = useState('name');
  const [filterValue, setFilterValue] = useState('');
  const filteredData = sampleData.filter(row =>
    row[filterType].toLowerCase().includes(filterValue.toLowerCase())
  );
  return (
    <div style={{ display: 'flex', minHeight: '100vh', background: '#f6f6fa' }}>
      <Sidebar />
      <main style={{ flex: 1, padding: 32 }}>
        <div style={{ marginBottom: 24, display: 'flex', alignItems: 'center', gap: 16 }}>
          <img src="/fullattend-logo.png" alt="FullAttend Logo" style={{ height: 40, cursor: 'pointer' }} onClick={() => window.location.href = '/login'} />
          <h1 style={{ fontSize: '2rem', fontWeight: 'bold', color: '#6d4aff', margin: 0 }}>Reports</h1>
        </div>
        <div style={{gap: 24 }}>
          <div style={{ flex: 2 }}>
            <div style={{ display: 'flex', gap: 16, marginBottom: 16 }}>
              <select style={{ padding: 8, borderRadius: 8, border: '1px solid #a78bfa', color: '#6d4aff', fontWeight: 'bold' }} value={filterType} onChange={e => setFilterType(e.target.value)}>
                <option value="name">Name</option>
                <option value="email">Email</option>
                <option value="studentId">Student ID</option>
              </select>
              <input type="text" placeholder={`Filter by ${filterType}`} value={filterValue} onChange={e => setFilterValue(e.target.value)} style={{ padding: 8, borderRadius: 8, border: '1px solid #a78bfa', color: '#6d4aff', fontWeight: 'bold', width: 200 }} />
            </div>
            <table style={{ width: '100%', borderCollapse: 'collapse', background: '#fff', borderRadius: 8, overflow: 'hidden', boxShadow: '0 2px 8px rgba(0,0,0,0.05)' }}>
              <thead>
                <tr style={{ background: '#ede9fe', color: '#6d4aff', fontWeight: 'bold' }}>
                  <th style={{ padding: 12 }}>Name</th>
                  <th style={{ padding: 12 }}>Student ID</th>
                  <th style={{ padding: 12 }}>Attendance</th>
                  <th style={{ padding: 12 }}>Image</th>
                  <th style={{ padding: 12 }}>Email</th>
                </tr>
              </thead>
              <tbody>
                {filteredData.map((row, idx) => (
                  <tr key={idx}>
                    <td style={{ padding: 12 }}>{row.name}</td>
                    <td style={{ padding: 12 }}>{row.studentId}</td>
                    <td style={{ padding: 12 }}>{row.attendance}</td>
                    <td style={{ padding: 12 }}>{row.image ? <img src={row.image} alt="Profile" style={{ height: 32, borderRadius: '50%' }} /> : ''}</td>
                    <td style={{ padding: 12 }}>{row.email}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div style={{ flex: 1, display: 'flex',marginTop:40, alignItems: 'center', justifyContent: 'center' }}>
            {/* Pie chart placeholder */}
            <div style={{ width: 180, height: 180, background: '#ede9fe', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#6d4aff', fontWeight: 'bold', fontSize: 24 }}>
              90%<br />Attendance
            </div>
          </div>
        </div>
      </main>
    </div>
  );
};

export default Reports;
