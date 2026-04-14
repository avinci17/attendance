const SUPABASE_URL = 'https://tmbgwgkwsccwbffcqrty.supabase.co';
const SUPABASE_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InRtYmd3Z2t3c2Njd2JmZmNxcnR5Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzYxMjgwMTcsImV4cCI6MjA5MTcwNDAxN30.2doKUlYFOmHGSggF3DG-o7YRJXBX6wvgKykCck3B2as';
const HEADERS = {
  'Content-Type': 'application/json',
  'apikey': SUPABASE_KEY,
  'Authorization': `Bearer ${SUPABASE_KEY}`
};
const TABLE = `${SUPABASE_URL}/rest/v1/attendance`;
const OJT_REQUIRED_HOURS = 500;

document.getElementById('date-display').textContent =
  new Date().toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

const DAY_CLASS = ['day-sun', 'day-mon', 'day-tue', 'day-wed', 'day-thu', 'day-fri', 'day-sat'];
const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

function dayBadge(dateStr) {
  if (!dateStr) return '—';
  const idx = new Date(dateStr + 'T00:00:00').getDay();
  return `<span class="day-badge ${DAY_CLASS[idx]}">${DAY_NAMES[idx]}</span>`;
}

function showToast(msg, type = 'success') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.style.background = type === 'success' ? '#5a7a6a' : type === 'error' ? '#8a3a3a' : '#3a4a6a';
  t.style.display = 'block';
  setTimeout(() => { t.style.display = 'none'; }, 3000);
}

function fmtDate(d) {
  if (!d) return '—';
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
}

function fmt12(t) {
  if (!t) return '—';
  const [h, m] = t.split(':');
  const hr = parseInt(h);
  return `${hr % 12 || 12}:${m} ${hr >= 12 ? 'PM' : 'AM'}`;
}

function fmtHours(h) {
  if (h === null || h === undefined || h === '') return '—';
  const hrs = Math.floor(h);
  const mins = Math.round((h - hrs) * 60);
  return mins > 0 ? `${hrs}h ${mins}m` : `${hrs}h`;
}

function calcHours(timeIn, timeOut) {
  if (!timeIn || !timeOut) return null;
  const [ih, im] = timeIn.split(':').map(Number);
  const [oh, om] = timeOut.split(':').map(Number);
  const diff = (oh * 60 + om) - (ih * 60 + im);
  return diff > 0 ? diff / 60 : null;
}

function computeHours(row) {
  if (row.manual_hours !== null && row.manual_hours !== undefined) return parseFloat(row.manual_hours);
  return calcHours(row.time_in, row.time_out);
}

function previewHours() {
  const tin = document.getElementById('f-time-in').value;
  const tout = document.getElementById('f-time-out').value;
  const prev = document.getElementById('computed-preview');
  const h = calcHours(tin, tout);
  if (h !== null) {
    prev.textContent = `Total: ${fmtHours(h)}`;
    prev.style.display = 'block';
  } else {
    prev.style.display = 'none';
  }
}

async function loadSummary() {
  const r = await fetch(`${TABLE}?select=time_in,time_out,manual_hours&order=date.desc`, { headers: HEADERS });
  const records = await r.json();

  let total = 0, days = 0;
  for (const row of records) {
    const h = computeHours(row);
    if (h !== null) { total += h; days++; }
  }

  const percent = total > 0 ? Math.min(100, round1(total / OJT_REQUIRED_HOURS * 100)) : 0;
  const remaining = Math.max(0, OJT_REQUIRED_HOURS - total);

  document.getElementById('s-total').textContent = fmtHours(total);
  document.getElementById('s-days').textContent = days;
  document.getElementById('s-remaining').textContent = fmtHours(remaining);
  document.getElementById('s-percent').textContent = percent + '%';
  document.getElementById('progress-bar').style.width = percent + '%';
  document.getElementById('pb-label').textContent = `${round2(total)} / ${OJT_REQUIRED_HOURS} hrs`;

  const HOURS_PER_DAY = 9;
  const today = new Date();

  function estimateDate(targetHours) {
    const hoursLeft = Math.max(0, targetHours - total);
    if (hoursLeft === 0) return 'Completed ✓';
    const daysLeft = Math.ceil(hoursLeft / HOURS_PER_DAY);
    let count = 0, date = new Date(today);
    while (count < daysLeft) {
      date.setDate(date.getDate() + 1);
      if (date.getDay() !== 0) count++;
    }
    return date.toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' });
  }

  document.getElementById('est-300').innerHTML =
    `300 hrs — <span style="color:#d4926a;font-weight:500;">${estimateDate(300)}</span>`;
  document.getElementById('est-500').innerHTML =
    `500 hrs — <span style="color:#d4926a;font-weight:500;">${estimateDate(500)}</span>`;
  document.getElementById('estimation-block').style.display = 'block';
}

function round1(n) { return Math.round(n * 10) / 10; }
function round2(n) { return Math.round(n * 100) / 100; }

async function loadRecords() {
  const r = await fetch(`${TABLE}?select=*&order=date.desc`, { headers: HEADERS });
  const records = await r.json();

  const tbody = document.getElementById('records-body');
  const mcards = document.getElementById('mobile-records-body');

  if (!records || !records.length) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:36px;color:rgba(196,160,144,0.4);">No records yet. Click "+ Add Record" to get started.</td></tr>`;
    mcards.innerHTML = `<p style="text-align:center;padding:24px;color:rgba(196,160,144,0.4);font-size:14px;">No records yet. Tap "+ Add Record" to get started.</p>`;
    return;
  }

  tbody.innerHTML = records.map(row => {
    const h = computeHours(row);
    return `<tr class="record-row">
      <td style="padding:12px 20px;font-weight:500;color:#f5ebe1;">${fmtDate(row.date)}</td>
      <td style="padding:12px 12px;">${dayBadge(row.date)}</td>
      <td style="padding:12px 12px;color:#c4a090;" class="mono">${fmt12(row.time_in)}</td>
      <td style="padding:12px 12px;color:#c4a090;" class="mono">${fmt12(row.time_out)}</td>
      <td style="padding:12px 12px;font-weight:600;color:#7a9e8a;" class="mono">${fmtHours(h)}</td>
      <td style="padding:12px 12px;color:rgba(196,160,144,0.6);">${row.notes || '—'}</td>
      <td style="padding:12px 12px;">
        <div style="display:flex;gap:6px;">
          <button onclick='openEditModal(${JSON.stringify(row)})'
            style="font-size:12px;padding:5px 12px;background:rgba(255,255,255,0.08);border:1px solid rgba(196,122,122,0.2);border-radius:6px;cursor:pointer;font-weight:500;color:#c4a090;">Edit</button>
          <button onclick="deleteRecord(${row.id})"
            style="font-size:12px;padding:5px 12px;background:rgba(196,80,80,0.15);border:1px solid rgba(196,80,80,0.25);border-radius:6px;cursor:pointer;font-weight:500;color:#c47a7a;">Delete</button>
        </div>
      </td>
    </tr>`;
  }).join('');

  mcards.innerHTML = records.map(row => {
    const h = computeHours(row);
    return `<div class="record-card">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
        <div>
          <p style="font-weight:600;color:#f5ebe1;font-size:14px;">${fmtDate(row.date)}</p>
          <div style="margin-top:4px;">${dayBadge(row.date)}</div>
        </div>
        <p style="font-size:20px;font-weight:700;color:#7a9e8a;" class="mono">${fmtHours(h)}</p>
      </div>
      <div style="display:flex;gap:16px;margin-bottom:8px;">
        <div>
          <p style="font-size:11px;color:rgba(196,160,144,0.5);font-weight:600;text-transform:uppercase;letter-spacing:.4px;">In</p>
          <p style="font-size:13px;color:#c4a090;font-weight:500;" class="mono">${fmt12(row.time_in)}</p>
        </div>
        <div>
          <p style="font-size:11px;color:rgba(196,160,144,0.5);font-weight:600;text-transform:uppercase;letter-spacing:.4px;">Out</p>
          <p style="font-size:13px;color:#c4a090;font-weight:500;" class="mono">${fmt12(row.time_out)}</p>
        </div>
        ${row.notes ? `<div style="flex:1;min-width:0;">
          <p style="font-size:11px;color:rgba(196,160,144,0.5);font-weight:600;text-transform:uppercase;letter-spacing:.4px;">Notes</p>
          <p style="font-size:13px;color:rgba(196,160,144,0.7);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${row.notes}</p>
        </div>` : ''}
      </div>
      <div style="display:flex;gap:8px;margin-top:4px;">
        <button onclick='openEditModal(${JSON.stringify(row)})'
          style="flex:1;font-size:13px;padding:8px;background:rgba(255,255,255,0.07);border:1px solid rgba(196,122,122,0.2);border-radius:8px;cursor:pointer;font-weight:500;color:#c4a090;">Edit</button>
        <button onclick="deleteRecord(${row.id})"
          style="flex:1;font-size:13px;padding:8px;background:rgba(196,80,80,0.12);border:1px solid rgba(196,80,80,0.2);border-radius:8px;cursor:pointer;font-weight:500;color:#c47a7a;">Delete</button>
      </div>
    </div>`;
  }).join('');
}

function openAddModal() {
  document.getElementById('modal-title').textContent = 'Add Attendance';
  document.getElementById('edit-id').value = '';
  document.getElementById('f-date').value = new Date().toISOString().split('T')[0];
  document.getElementById('f-time-in').value = '';
  document.getElementById('f-time-out').value = '';
  document.getElementById('f-notes').value = '';
  document.getElementById('computed-preview').style.display = 'none';
  document.getElementById('modal').style.display = 'flex';
}

function openEditModal(row) {
  document.getElementById('modal-title').textContent = 'Edit Attendance';
  document.getElementById('edit-id').value = row.id;
  document.getElementById('f-date').value = row.date;
  document.getElementById('f-time-in').value = row.time_in ? row.time_in.slice(0, 5) : '';
  document.getElementById('f-time-out').value = row.time_out ? row.time_out.slice(0, 5) : '';
  document.getElementById('f-notes').value = row.notes || '';
  previewHours();
  document.getElementById('modal').style.display = 'flex';
}

function closeModal() {
  document.getElementById('modal').style.display = 'none';
}

async function saveRecord() {
  const id = document.getElementById('edit-id').value;
  const date = document.getElementById('f-date').value;
  const time_in = document.getElementById('f-time-in').value;
  const time_out = document.getElementById('f-time-out').value;
  const notes = document.getElementById('f-notes').value;

  if (!date) { showToast('Please enter a date.', 'error'); return; }
  if (!time_in) { showToast('Please enter Time In.', 'error'); return; }
  if (!time_out) { showToast('Please enter Time Out.', 'error'); return; }

  const hours = calcHours(time_in, time_out);
  if (hours === null || hours <= 0) { showToast('Time Out must be after Time In.', 'error'); return; }

  const payload = { date, time_in: time_in + ':00', time_out: time_out + ':00', notes: notes || null, manual_hours: null };

  let r;
  if (id) {
    r = await fetch(`${TABLE}?id=eq.${id}`, {
      method: 'PATCH',
      headers: { ...HEADERS, 'Prefer': 'return=minimal' },
      body: JSON.stringify(payload)
    });
  } else {
    r = await fetch(TABLE, {
      method: 'POST',
      headers: { ...HEADERS, 'Prefer': 'return=minimal' },
      body: JSON.stringify(payload)
    });
  }

  if (!r.ok) {
    const err = await r.json();
    const msg = err.message || err.details || 'Failed to save.';
    showToast(msg.includes('unique') ? 'A record for this date already exists.' : msg, 'error');
    return;
  }

  closeModal();
  showToast(id ? 'Record updated!' : 'Record added!');
  refresh();
}

async function deleteRecord(id) {
  if (!confirm('Delete this record?')) return;
  const r = await fetch(`${TABLE}?id=eq.${id}`, {
    method: 'DELETE',
    headers: { ...HEADERS, 'Prefer': 'return=minimal' }
  });
  if (!r.ok) { showToast('Failed to delete.', 'error'); return; }
  showToast('Record deleted.', 'info');
  refresh();
}

function refresh() {
  loadSummary();
  loadRecords();
}

document.getElementById('modal').addEventListener('click', function (e) {
  if (e.target === this) closeModal();
});

refresh();