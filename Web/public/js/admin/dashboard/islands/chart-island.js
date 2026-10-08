/**
 * Chart Island
 * Menginisialisasi grafik tren kehadiran 7 hari terakhir menggunakan Chart.js.
 */
export function initWeeklyChart() {
    const canvas = document.getElementById('weeklyChart');
    if (!canvas || typeof window.Chart === 'undefined') return;

    let chartData = { labels: [], ontime: [], late: [] };

    const dataScript = document.getElementById('weekly-chart-data');
    if (dataScript) {
        try {
            chartData = JSON.parse(dataScript.textContent);
        } catch (e) {
            console.error('Gagal parsing data grafik mingguan:', e);
        }
    } else if (canvas.dataset.labels) {
        try {
            chartData.labels = JSON.parse(canvas.dataset.labels);
            chartData.ontime = JSON.parse(canvas.dataset.ontime || '[]');
            chartData.late = JSON.parse(canvas.dataset.late || '[]');
        } catch (e) {
            console.error('Gagal parsing dataset canvas:', e);
        }
    }

    new window.Chart(canvas, {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [
                {
                    label: 'Tepat Waktu',
                    data: chartData.ontime,
                    backgroundColor: '#2563eb',
                    hoverBackgroundColor: '#1d4ed8',
                    borderRadius: 6,
                    barPercentage: 0.6,
                    categoryPercentage: 0.7,
                },
                {
                    label: 'Terlambat',
                    data: chartData.late,
                    backgroundColor: '#f59e0b',
                    hoverBackgroundColor: '#d97706',
                    borderRadius: 6,
                    barPercentage: 0.6,
                    categoryPercentage: 0.7,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f172a',
                    borderColor: '#e2e8f0',
                    borderWidth: 1,
                    titleColor: '#ffffff',
                    bodyColor: '#cbd5e1',
                    padding: 10,
                    cornerRadius: 8,
                }
            },
            scales: {
                x: {
                    stacked: false,
                    grid: { display: false },
                    ticks: { color: '#64748b', font: { size: 11 } }
                },
                y: {
                    stacked: false,
                    grid: { color: '#f1f5f9' },
                    ticks: { color: '#64748b', font: { size: 11 }, stepSize: 1, precision: 0 },
                    beginAtZero: true
                }
            }
        }
    });
}

