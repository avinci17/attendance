const API = 'https://OJT-Attendance.fwh.is/api.php';

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
  const r = await fetch(`${API}?action=summary`);
  const d = await r.json();

  document.getElementById('s-total').textContent = fmtHours(d.total_hours);
  document.getElementById('s-days').textContent = d.days_attended;
  document.getElementById('s-remaining').textContent = fmtHours(d.remaining);
  document.getElementById('s-percent').textContent = d.percent + '%';
  document.getElementById('progress-bar').style.width = d.percent + '%';
  document.getElementById('pb-label').textContent = `${d.total_hours} / ${d.required_hours} hrs`;

  const HOURS_PER_DAY = 9;
  const today = new Date();

  function estimateDate(targetHours) {
    const hoursLeft = Math.max(0, targetHours - d.total_hours);
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

async function loadRecords() {
  const r = await fetch(`${API}?action=list`);
  const d = await r.json();
  const tbody = document.getElementById('records-body');
  const mcards = document.getElementById('mobile-records-body');

  if (!d.records || !d.records.length) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:36px;color:rgba(196,160,144,0.4);">No records yet. Click "+ Add Record" to get started.</td></tr>`;
    mcards.innerHTML = `<p style="text-align:center;padding:24px;color:rgba(196,160,144,0.4);font-size:14px;">No records yet. Tap "+ Add Record" to get started.</p>`;
    return;
  }

  tbody.innerHTML = d.records.map(row => {
    const h = row.manual_hours !== null ? row.manual_hours : row.computed_hours;
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

  mcards.innerHTML = d.records.map(row => {
    const h = row.manual_hours !== null ? row.manual_hours : row.computed_hours;
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

  let r;
  if (id) {
    r = await fetch(`${API}?action=update&id=${id}&_method=PUT`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ date, notes, time_in, time_out })
    });
  } else {
    r = await fetch(`${API}?action=add_manual`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ date, notes, time_in, time_out })
    });
  }

  const d = await r.json();
  if (d.error) { showToast(d.error, 'error'); return; }
  closeModal();
  showToast(id ? 'Record updated!' : 'Record added!');
  refresh();
}

async function deleteRecord(id) {
  if (!confirm('Delete this record?')) return;
  const r = await fetch(`${API}?action=delete&id=${id}&_method=DELETE`, { method: 'POST' });
  const d = await r.json();
  if (d.error) { showToast(d.error, 'error'); return; }
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