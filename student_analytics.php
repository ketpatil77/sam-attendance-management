<?php
require_once 'app_ui.php';
sam_require_admin();

$initialRid = (int)($_GET['rid'] ?? 0);

sam_render_head(
    'Student Search & Analytics',
    '<script src="assets/vendor/chart.umd.min.js"></script>
     <script src="assets/vendor/jspdf.umd.min.js"></script>
     <script src="assets/vendor/jspdf.plugin.autotable.min.js"></script>'
);
sam_page_start(
    'Admin Analytics',
    'Student Search & Analytics',
    'Search one student. See profile, logs, trends, heatmap, late analysis, and exports in one guarded panel.',
    [
        ['href' => 'dashboard.php', 'label' => 'Dashboard', 'class' => 'btn btn-outline-secondary', 'icon' => 'bi bi-grid'],
        ['href' => 'report.php', 'label' => 'Reports', 'class' => 'btn btn-outline-primary', 'icon' => 'bi bi-bar-chart'],
    ]
);
?>

<div class="sam-card sam-search-panel mb-4" data-initial-rid="<?= $initialRid ?>">
    <div class="sam-section-head mb-3">
        <h4 class="sam-section-title"><i class="bi bi-search me-2"></i>Student Search</h4>
        <div class="sam-inline-note">Search by student name, Student ID, or ERN.</div>
    </div>
    <div class="sam-search-wrap">
        <input id="studentSearchInput" type="text" class="form-control form-control-lg" placeholder="Type student name or ID">
        <div id="studentSearchDropdown" class="sam-search-dropdown d-none"></div>
    </div>
</div>

<div id="studentAnalyticsEmpty" class="sam-card sam-empty-hero">
    <h3 class="mb-2">No student selected</h3>
    <p class="mb-0">Use the search bar above. Autocomplete will suggest matches. Select one student to load the full analytics dashboard.</p>
</div>

<div id="studentAnalyticsPanel" class="d-none">
    <div class="sam-grid sam-grid-2 mb-4">
        <div class="sam-card sam-profile-card">
            <div class="sam-profile-row">
                <div id="studentProfileAvatar" class="sam-profile-avatar">S</div>
                <div class="sam-profile-meta">
                    <h3 id="studentProfileName" class="mb-1"></h3>
                    <div id="studentProfileId" class="sam-inline-note mb-2"></div>
                    <div class="sam-profile-badges">
                        <span id="studentClassBadge" class="sam-chip"></span>
                        <span id="studentDivisionBadge" class="sam-chip"></span>
                        <span id="studentRollBadge" class="sam-chip"></span>
                    </div>
                </div>
            </div>
            <div class="sam-divider"></div>
            <div class="sam-profile-grid">
                <div><strong>Class</strong><span id="studentClassText"></span></div>
                <div><strong>Division</strong><span id="studentDivisionText"></span></div>
                <div><strong>Roll No</strong><span id="studentRollText"></span></div>
                <div><strong>Contact</strong><span id="studentContactText"></span></div>
                <div><strong>Parent Contact</strong><span id="studentParentText"></span></div>
                <div><strong>Department</strong><span id="studentDepartmentText"></span></div>
            </div>
        </div>

        <div class="sam-card">
            <div class="sam-section-head">
                <h4 class="sam-section-title"><i class="bi bi-download me-2"></i>Export</h4>
                <div class="sam-inline-note">PDF report and CSV log export.</div>
            </div>
            <div class="sam-grid">
                <button id="exportPdfBtn" class="btn btn-primary"><i class="bi bi-filetype-pdf me-2"></i>Download PDF Report</button>
                <button id="exportCsvBtn" class="btn btn-outline-primary"><i class="bi bi-filetype-csv me-2"></i>Export CSV Log</button>
            </div>
            <div class="sam-divider"></div>
            <div class="sam-inline-note">Late cutoff used for analytics: <strong>07:30 AM</strong></div>
        </div>
    </div>

    <div id="summaryStats" class="sam-grid sam-grid-3 mb-4"></div>

    <div class="sam-grid sam-grid-2 mb-4">
        <div class="sam-card">
            <div class="sam-section-head">
                <h4 class="sam-section-title"><i class="bi bi-bar-chart-steps me-2"></i>Monthly Present / Absent / Late</h4>
            </div>
            <div class="sam-chart-wrap">
                <canvas id="monthlyBarChart"></canvas>
            </div>
        </div>
        <div class="sam-card">
            <div class="sam-section-head">
                <h4 class="sam-section-title"><i class="bi bi-pie-chart me-2"></i>Overall Ratio</h4>
            </div>
            <div class="sam-chart-wrap sam-chart-wrap-sm">
                <canvas id="ratioDonutChart"></canvas>
            </div>
        </div>
    </div>

    <details class="sam-card sam-collapse mb-4">
        <summary class="sam-collapse-head">
            <span><i class="bi bi-graph-up-arrow me-2"></i>Trend Charts</span>
            <small>Expand</small>
        </summary>
        <div class="sam-grid sam-grid-2">
            <div class="sam-card sam-card-inner">
                <div class="sam-section-head">
                    <h4 class="sam-section-title">Attendance Trend</h4>
                </div>
                <div class="sam-chart-wrap">
                    <canvas id="trendLineChart"></canvas>
                </div>
            </div>
            <div class="sam-card sam-card-inner">
                <div class="sam-section-head">
                    <h4 class="sam-section-title">Late Arrival Trend</h4>
                </div>
                <div class="sam-chart-wrap">
                    <canvas id="lateTrendChart"></canvas>
                </div>
            </div>
        </div>
    </details>

    <details class="sam-card sam-collapse mb-4">
        <summary class="sam-collapse-head">
            <span><i class="bi bi-calendar3 me-2"></i>Attendance Heatmap</span>
            <small id="heatmapYearLabel" class="sam-inline-note"></small>
        </summary>
        <div id="attendanceHeatmap" class="sam-heatmap"></div>
        <div class="sam-heatmap-legend">
            <span class="sam-heatmap-key none"></span><small>None</small>
            <span class="sam-heatmap-key present"></span><small>Present</small>
            <span class="sam-heatmap-key late"></span><small>Late</small>
            <span class="sam-heatmap-key absent"></span><small>Absent</small>
        </div>
    </details>

    <div class="sam-grid sam-grid-2 mb-4">
        <div class="sam-card">
            <div class="sam-section-head">
                <h4 class="sam-section-title"><i class="bi bi-stopwatch me-2"></i>Late Analysis</h4>
            </div>
            <div id="lateAnalysisGrid" class="sam-grid sam-grid-2"></div>
        </div>
        <div class="sam-card">
            <div class="sam-section-head">
                <h4 class="sam-section-title"><i class="bi bi-fire me-2"></i>Streaks</h4>
            </div>
            <div id="streakBadges" class="sam-profile-badges"></div>
        </div>
    </div>

    <div class="sam-card">
        <div class="sam-section-head">
            <h4 class="sam-section-title"><i class="bi bi-table me-2"></i>Attendance Log</h4>
            <div class="sam-log-controls">
                <select id="logMonthFilter" class="form-select"></select>
                <select id="logStatusFilter" class="form-select">
                    <option value="">All Status</option>
                    <option value="Present">Present</option>
                    <option value="Absent">Absent</option>
                    <option value="Late">Late</option>
                </select>
                <select id="logPageSize" class="form-select">
                    <option value="10">10 rows</option>
                    <option value="20" selected>20 rows</option>
                </select>
                <button id="logSortBtn" class="btn btn-outline-secondary"><i class="bi bi-sort-down me-2"></i>Date Desc</button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Date</th>
                        <th>Day</th>
                        <th>Status</th>
                        <th>Check-in Time</th>
                        <th>Check-out Time</th>
                        <th>Late (mins)</th>
                    </tr>
                </thead>
                <tbody id="analyticsLogBody"></tbody>
            </table>
        </div>
        <div class="sam-table-footer">
            <div id="logPagingMeta" class="sam-inline-note"></div>
            <div class="sam-toolbar-group">
                <button id="logPrevBtn" class="btn btn-outline-secondary btn-sm">Prev</button>
                <button id="logNextBtn" class="btn btn-outline-secondary btn-sm">Next</button>
            </div>
        </div>
    </div>
</div>

<?php
$scripts = <<<'HTML'
<script>
const analyticsState = {
    rid: Number(document.querySelector('[data-initial-rid]')?.dataset.initialRid || 0),
    payload: null,
    logPage: 1,
    pageSize: 20,
    sortDir: 'desc',
    month: '',
    status: '',
};

const chartRefs = {};
const searchInput = document.getElementById('studentSearchInput');
const searchDropdown = document.getElementById('studentSearchDropdown');

function debounce(fn, wait = 220) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), wait);
    };
}

async function fetchJson(url) {
    const response = await fetch(url, { credentials: 'same-origin' });
    const data = await response.json();
    if (!response.ok || data.error) {
        throw new Error(data.error || 'Request failed');
    }
    return data;
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (match) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    })[match]);
}

async function loadSuggestions(query) {
    if (query.trim().length < 2) {
        searchDropdown.classList.add('d-none');
        searchDropdown.innerHTML = '';
        return;
    }
    const data = await fetchJson(`student_analytics_api.php?action=suggest&q=${encodeURIComponent(query)}`);
    if (!data.items.length) {
        searchDropdown.classList.remove('d-none');
        searchDropdown.innerHTML = '<div class="sam-search-item disabled">No matches found</div>';
        return;
    }
    searchDropdown.classList.remove('d-none');
    searchDropdown.innerHTML = data.items.map((item) => `
        <button type="button" class="sam-search-item" data-rid="${item.RID}">
            <strong>${escapeHtml(item.STUDENT_NAME)}</strong>
            <span>${escapeHtml(item.STUDENT_ID || item.ERN_NO)} â€¢ ${escapeHtml(item.AYEAR || '-')} â€¢ ${escapeHtml(item.BATCH || '-')}</span>
        </button>
    `).join('');
}

async function loadStudent(rid) {
    analyticsState.rid = rid;
    analyticsState.logPage = 1;
    const payload = await fetchJson(`student_analytics_api.php?action=student&rid=${rid}`);
    analyticsState.payload = payload;
    renderPanel();
    history.replaceState({}, '', `student_analytics.php?rid=${rid}`);
}

function isObject(value) {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function isArray(value) {
    return Array.isArray(value);
}

function renderPanel() {
    const payload = analyticsState.payload;
    if (!payload) return;

    document.getElementById('studentAnalyticsEmpty').classList.add('d-none');
    document.getElementById('studentAnalyticsPanel').classList.remove('d-none');

    renderProfile(payload.profile || {});
    renderSummary(payload.summary || {});
    renderLateAnalysis(payload.late_analysis || {});
    renderMonthFilter(payload.months || []);
    renderLogTable();

    const charts = isObject(payload.charts) ? payload.charts : {};
    renderMonthlyChart(isObject(charts.monthly) ? charts.monthly : {});
    renderRatioChart(isObject(charts.ratio) ? charts.ratio : {});
    renderTrendChart(isArray(charts.trend) ? charts.trend : []);
    renderLateTrendChart(isArray(charts.late_trend) ? charts.late_trend : []);
    renderHeatmap(isObject(charts.heatmap) ? charts.heatmap : { year: new Date().getFullYear(), days: [] });
    requestAnimationFrame(resizeAllCharts);
}

function renderProfile(profile) {
    const avatar = document.getElementById('studentProfileAvatar');
    if (profile.photo) {
        avatar.innerHTML = `<img src="data:image/jpeg;base64,${profile.photo}" alt="${escapeHtml(profile.name)}">`;
    } else {
        avatar.textContent = profile.name.charAt(0).toUpperCase();
    }
    document.getElementById('studentProfileName').textContent = profile.name;
    document.getElementById('studentProfileId').textContent = `Student ID: ${profile.student_id} â€¢ ERN: ${profile.roll_no}`;
    document.getElementById('studentClassBadge').textContent = `Class ${profile.class || '-'}`;
    document.getElementById('studentDivisionBadge').textContent = `Division ${profile.division || '-'}`;
    document.getElementById('studentRollBadge').textContent = `Roll ${profile.roll_no || '-'}`;
    document.getElementById('studentClassText').textContent = profile.class || '-';
    document.getElementById('studentDivisionText').textContent = profile.division || '-';
    document.getElementById('studentRollText').textContent = profile.roll_no || '-';
    document.getElementById('studentContactText').textContent = profile.contact || '-';
    document.getElementById('studentParentText').textContent = profile.parent_contact || '-';
    document.getElementById('studentDepartmentText').textContent = profile.department || '-';
}

function renderSummary(summary) {
    const target = document.getElementById('summaryStats');
    const percentClass = summary.attendance_band === 'success'
        ? 'sam-analytics-stat success'
        : summary.attendance_band === 'warning'
            ? 'sam-analytics-stat warning'
            : 'sam-analytics-stat danger';

    target.innerHTML = `
        <div class="sam-analytics-stat"><span>Total Working Days</span><strong>${summary.total_working_days}</strong></div>
        <div class="sam-analytics-stat"><span>Days Present</span><strong>${summary.days_present}</strong></div>
        <div class="sam-analytics-stat danger"><span>Days Absent</span><strong>${summary.days_absent}</strong></div>
        <div class="sam-analytics-stat warning"><span>Days Late</span><strong>${summary.days_late}</strong></div>
        <div class="${percentClass}"><span>Attendance %</span><strong>${summary.attendance_percent}%</strong></div>
    `;
}

function renderLateAnalysis(analysis) {
    document.getElementById('lateAnalysisGrid').innerHTML = `
        <div class="sam-analytics-stat"><span>Avg Late Time</span><strong>${analysis.average_late_minutes} mins</strong></div>
        <div class="sam-analytics-stat"><span>Most Frequent Late Day</span><strong>${escapeHtml(analysis.most_frequent_late_day)}</strong></div>
    `;
    document.getElementById('streakBadges').innerHTML = `
        <span class="sam-chip sam-chip-warning">Current Late Streak: ${analysis.current_late_streak}</span>
        <span class="sam-chip sam-chip-danger">Current Absence Streak: ${analysis.current_absence_streak}</span>
        <span class="sam-chip">Longest Absence Streak: ${analysis.longest_absence_streak}</span>
    `;
}

function renderMonthFilter(months) {
    const select = document.getElementById('logMonthFilter');
    const currentValue = analyticsState.month;
    select.innerHTML = '<option value="">All Months</option>' + months.map((month) => `<option value="${month}">${month}</option>`).join('');
    select.value = currentValue;
}

function getFilteredLogRows() {
    let rows = [...(analyticsState.payload?.log || [])];
    if (analyticsState.month) rows = rows.filter((row) => row.month === analyticsState.month);
    if (analyticsState.status) rows = rows.filter((row) => row.status === analyticsState.status);
    rows.sort((a, b) => analyticsState.sortDir === 'desc' ? b.date.localeCompare(a.date) : a.date.localeCompare(b.date));
    return rows;
}

function renderLogTable() {
    const rows = getFilteredLogRows();
    const start = (analyticsState.logPage - 1) * analyticsState.pageSize;
    const paged = rows.slice(start, start + analyticsState.pageSize);
    const body = document.getElementById('analyticsLogBody');
    body.innerHTML = paged.length ? paged.map((row) => `
        <tr>
            <td>${row.date}</td>
            <td>${row.day}</td>
            <td><span class="sam-chip ${row.status === 'Late' ? 'sam-chip-warning' : row.status === 'Absent' ? 'sam-chip-danger' : 'sam-chip-success'}">${row.status}</span></td>
            <td>${row.check_in}</td>
            <td>${row.check_out}</td>
            <td>${row.late_minutes || '-'}</td>
        </tr>
    `).join('') : '<tr><td colspan="6" class="sam-empty-state">No log rows match current filters.</td></tr>';
    document.getElementById('logPagingMeta').textContent = rows.length ? `Showing ${start + 1}-${Math.min(start + analyticsState.pageSize, rows.length)} of ${rows.length}` : 'No rows';
    document.getElementById('logPrevBtn').disabled = analyticsState.logPage === 1;
    document.getElementById('logNextBtn').disabled = start + analyticsState.pageSize >= rows.length;
    document.getElementById('logSortBtn').innerHTML = analyticsState.sortDir === 'desc' ? '<i class="bi bi-sort-down me-2"></i>Date Desc' : '<i class="bi bi-sort-up me-2"></i>Date Asc';
}

function buildChart(id, config) {
    if (chartRefs[id]) chartRefs[id].destroy();
    chartRefs[id] = new Chart(document.getElementById(id), config);
}

function resizeAllCharts() {
    Object.values(chartRefs).forEach((chart) => chart?.resize());
}

function renderMonthlyChart(monthly) {
    const labels = Object.keys(monthly);
    buildChart('monthlyBarChart', {
        type: 'bar',
        data: {
            labels,
            datasets: [
                { label: 'Present', data: labels.map((l) => Number(monthly[l]?.Present ?? 0)), backgroundColor: '#16a34a' },
                { label: 'Absent', data: labels.map((l) => Number(monthly[l]?.Absent ?? 0)), backgroundColor: '#e11d48' },
                { label: 'Late', data: labels.map((l) => Number(monthly[l]?.Late ?? 0)), backgroundColor: '#d97706' },
            ],
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
}

function renderRatioChart(ratio) {
    const labels = Object.keys(ratio);
    buildChart('ratioDonutChart', {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{ data: labels.map((key) => Number(ratio[key] ?? 0)), backgroundColor: ['#16a34a', '#e11d48', '#d97706'] }],
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
}

function renderTrendChart(points) {
    buildChart('trendLineChart', {
        type: 'line',
        data: {
            labels: points.map((p) => p.label),
            datasets: [{
                label: 'Attendance %',
                data: points.map((p) => Number(p.percent ?? 0)),
                borderColor: '#0f766e',
                backgroundColor: 'rgba(15,118,110,0.15)',
                tension: 0.25,
                fill: true,
            }],
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { min: 0, max: 100 } } }
    });
}

function renderLateTrendChart(points) {
    buildChart('lateTrendChart', {
        type: 'line',
        data: {
            labels: points.map((p) => p.date),
            datasets: [{
                label: 'Late Minutes',
                data: points.map((p) => Number(p.minutes ?? 0)),
                borderColor: '#d97706',
                backgroundColor: 'rgba(217,119,6,0.15)',
                tension: 0.25,
                fill: true,
            }],
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
}

function renderHeatmap(heatmap) {
    const yearLabel = document.getElementById('heatmapYearLabel');
    const heatmapGrid = document.getElementById('attendanceHeatmap');
    if (!yearLabel || !heatmapGrid) return;

    const days = Array.isArray(heatmap.days) ? heatmap.days : [];
    yearLabel.textContent = `Year ${heatmap.year || new Date().getFullYear()}`;
    heatmapGrid.innerHTML = days.length ? days.map((day) => {
        const status = (day.status || 'Absent').toLowerCase();
        const safeDate = day.date || '';
        const title = `${safeDate}${safeDate && day.status ? ' â€¢ ' : ''}${day.status || 'Absent'}`;
        return `<div class="sam-heatmap-cell ${escapeHtml(status)}" title="${escapeHtml(title)}"></div>`;
    }).join('') : '<div class="sam-empty-state">No attendance heatmap data available.</div>';
}

function downloadCsv() {
    if (!analyticsState.rid) return;
    const params = new URLSearchParams({ action: 'csv', rid: String(analyticsState.rid), month: analyticsState.month, status: analyticsState.status });
    window.location.href = `student_analytics_api.php?${params.toString()}`;
}

function downloadPdf() {
    if (!analyticsState.payload || !window.jspdf) return;
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'mm', 'a4');
    const profile = analyticsState.payload.profile;
    const summary = analyticsState.payload.summary;
    const logRows = getFilteredLogRows();
    doc.setFontSize(18);
    doc.text('Student Attendance Report', 14, 16);
    doc.setFontSize(11);
    doc.text(`Name: ${profile.name}`, 14, 26);
    doc.text(`Student ID: ${profile.student_id}`, 14, 32);
    doc.text(`Class/Division: ${profile.class} / ${profile.division}`, 14, 38);
    doc.text(`Roll No: ${profile.roll_no}`, 14, 44);
    doc.text(`Parent Contact: ${profile.parent_contact}`, 14, 50);
    doc.text(`Working Days: ${summary.total_working_days}`, 14, 60);
    doc.text(`Present: ${summary.days_present}`, 60, 60);
    doc.text(`Absent: ${summary.days_absent}`, 96, 60);
    doc.text(`Late: ${summary.days_late}`, 130, 60);
    doc.text(`Attendance %: ${summary.attendance_percent}%`, 160, 60);

    let y = 70;
    ['monthlyBarChart', 'ratioDonutChart', 'trendLineChart', 'lateTrendChart'].forEach((id, index) => {
        const canvas = document.getElementById(id);
        if (!canvas) return;
        const img = canvas.toDataURL('image/png', 1.0);
        const x = index % 2 === 0 ? 14 : 110;
        if (index % 2 === 0 && index > 0) y += 52;
        doc.addImage(img, 'PNG', x, y, 86, 42);
    });

    doc.addPage();
    doc.autoTable({
        startY: 14,
        head: [['Date', 'Day', 'Status', 'Check-in', 'Check-out', 'Late Mins']],
        body: logRows.map((row) => [row.date, row.day, row.status, row.check_in, row.check_out, row.late_minutes || '-']),
        styles: { fontSize: 8 },
    });
    doc.save(`student-report-${profile.student_id || profile.roll_no}.pdf`);
}

document.getElementById('logMonthFilter').addEventListener('change', (event) => {
    analyticsState.month = event.target.value;
    analyticsState.logPage = 1;
    renderLogTable();
});
document.getElementById('logStatusFilter').addEventListener('change', (event) => {
    analyticsState.status = event.target.value;
    analyticsState.logPage = 1;
    renderLogTable();
});
document.getElementById('logPageSize').addEventListener('change', (event) => {
    analyticsState.pageSize = Number(event.target.value);
    analyticsState.logPage = 1;
    renderLogTable();
});
document.getElementById('logSortBtn').addEventListener('click', () => {
    analyticsState.sortDir = analyticsState.sortDir === 'desc' ? 'asc' : 'desc';
    renderLogTable();
});
document.getElementById('logPrevBtn').addEventListener('click', () => {
    if (analyticsState.logPage > 1) {
        analyticsState.logPage--;
        renderLogTable();
    }
});
document.getElementById('logNextBtn').addEventListener('click', () => {
    analyticsState.logPage++;
    renderLogTable();
});
document.getElementById('exportCsvBtn').addEventListener('click', downloadCsv);
document.getElementById('exportPdfBtn').addEventListener('click', downloadPdf);
document.querySelectorAll('.sam-collapse').forEach((node) => {
    node.addEventListener('toggle', () => {
        if (node.open) requestAnimationFrame(resizeAllCharts);
    });
});

searchDropdown.addEventListener('click', (event) => {
    const button = event.target.closest('[data-rid]');
    if (!button) return;
    searchInput.value = button.querySelector('strong')?.textContent || '';
    searchDropdown.classList.add('d-none');
    loadStudent(Number(button.dataset.rid)).catch((error) => alert(error.message));
});

document.addEventListener('click', (event) => {
    if (!event.target.closest('.sam-search-wrap')) searchDropdown.classList.add('d-none');
});

searchInput.addEventListener('input', debounce((event) => {
    loadSuggestions(event.target.value).catch((error) => {
        searchDropdown.classList.remove('d-none');
        searchDropdown.innerHTML = `<div class="sam-search-item disabled">${escapeHtml(error.message)}</div>`;
    });
}, 200));

if (analyticsState.rid > 0) {
    loadStudent(analyticsState.rid).catch((error) => alert(error.message));
}
</script>
HTML;

sam_page_end($scripts);
?>











