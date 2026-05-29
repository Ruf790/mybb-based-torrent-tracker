document.addEventListener('DOMContentLoaded', function() {

    // ── Traffic Chart ─────────────────────────────────────────────────────────
    var trafficEl = document.getElementById('trafficChart');
    if (trafficEl) {
        var ctx = trafficEl.getContext('2d');

        // Градиент для Download
        var dlGradient = ctx.createLinearGradient(0, 0, 0, 400);
        dlGradient.addColorStop(0, 'rgba(220,53,69,0.6)');
        dlGradient.addColorStop(1, 'rgba(220,53,69,0.05)');

        // Градиент для Upload
        var ulGradient = ctx.createLinearGradient(0, 0, 0, 400);
        ulGradient.addColorStop(0, 'rgba(25,135,84,0.6)');
        ulGradient.addColorStop(1, 'rgba(25,135,84,0.05)');

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: window.CHART_MONTHS || [],
                datasets: [
                    {
                        label: 'Downloaded (GB)',
                        data: window.CHART_DL || [],
                        backgroundColor: dlGradient,
                        borderColor: 'rgba(220,53,69,0.9)',
                        borderWidth: 0,
                        borderRadius: { topLeft: 6, topRight: 6 },
                        borderSkipped: false,
                    },
                    {
                        label: 'Uploaded (GB)',
                        data: window.CHART_UL || [],
                        backgroundColor: ulGradient,
                        borderColor: 'rgba(25,135,84,0.9)',
                        borderWidth: 0,
                        borderRadius: { topLeft: 6, topRight: 6 },
                        borderSkipped: false,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 20,
                            font: { size: 13 }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.85)',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(ctx) {
                                return ' ' + ctx.dataset.label + ': ' + ctx.parsed.y + ' GB';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 12 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            callback: function(v) { return v + ' GB'; },
                            font: { size: 12 }
                        }
                    }
                }
            }
        });
    }

    // ── Activity Chart ────────────────────────────────────────────────────────
    var activityEl = document.getElementById('activityChart');
    if (activityEl) {
        var ctx2 = activityEl.getContext('2d');

        var actGradient = ctx2.createLinearGradient(0, 0, 0, 300);
        actGradient.addColorStop(0, 'rgba(13,110,253,0.7)');
        actGradient.addColorStop(1, 'rgba(13,110,253,0.1)');

        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: window.CHART_DAYS || [],
                datasets: [{
                    label: 'Activity',
                    data: window.CHART_DAY_DATA || [],
                    backgroundColor: actGradient,
                    borderColor: 'rgba(13,110,253,0)',
                    borderWidth: 0,
                    borderRadius: { topLeft: 5, topRight: 5 },
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.85)',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(ctx) {
                                return ' Events: ' + ctx.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            maxTicksLimit: 10,
                            font: { size: 11 }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            precision: 0,
                            font: { size: 12 }
                        }
                    }
                }
            }
        });
    }
});
