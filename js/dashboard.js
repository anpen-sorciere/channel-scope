document.addEventListener('DOMContentLoaded', () => {
    const dataRoot = document.getElementById('js-data');
    if (!dataRoot) {
        return;
    }

    let chartData = { labels: [], views: [], likes: [] };
    let weekdayData = { labels: [], avgViews: [], videoCounts: [] };
    let timeBandData = { labels: [], avgViews: [], videoCounts: [] };

    try {
        if (dataRoot.dataset.chart) {
            chartData = JSON.parse(dataRoot.dataset.chart);
        }
        if (dataRoot.dataset.weekday) {
            weekdayData = JSON.parse(dataRoot.dataset.weekday);
        }
        if (dataRoot.dataset.timeband) {
            timeBandData = JSON.parse(dataRoot.dataset.timeband);
        }
    } catch (e) {
        console.error('データのJSONパースに失敗しました', e);
    }

    // 再生数 / 高評価 推移
    const trendCanvas = document.getElementById('trendChart');
    if (trendCanvas && window.Chart) {
        const trendCtx = trendCanvas.getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: chartData.labels || [],
                datasets: [
                    {
                        label: '再生数',
                        data: chartData.views || [],
                        borderWidth: 2,
                        tension: 0.25,
                        yAxisID: 'y',
                    },
                    {
                        label: '高評価数',
                        data: chartData.likes || [],
                        borderWidth: 2,
                        borderDash: [4, 4],
                        tension: 0.25,
                        yAxisID: 'y1',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(148, 163, 184, 0.2)',
                        },
                        ticks: {
                            color: '#9ca3af',
                            maxTicksLimit: 8,
                        }
                    },
                    y: {
                        type: 'linear',
                        position: 'left',
                        grid: {
                            color: 'rgba(31, 41, 55, 0.6)',
                        },
                        ticks: {
                            color: '#9ca3af',
                        }
                    },
                    y1: {
                        type: 'linear',
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                        ticks: {
                            color: '#9ca3af',
                        }
                    }
                },
                plugins: {
                    legend: {
                        labels: {
                            color: '#e5e7eb'
                        }
                    },
                    tooltip: {
                        backgroundColor: '#020617',
                        titleColor: '#e5e7eb',
                        bodyColor: '#e5e7eb',
                        borderColor: '#1f2937',
                        borderWidth: 1,
                    }
                }
            }
        });
    }

    // 曜日別 平均再生数
    const weekdayCanvas = document.getElementById('weekdayChart');
    if (weekdayCanvas && window.Chart) {
        const weekdayCtx = weekdayCanvas.getContext('2d');
        new Chart(weekdayCtx, {
            type: 'bar',
            data: {
                labels: weekdayData.labels || [],
                datasets: [
                    {
                        label: '平均再生数',
                        data: weekdayData.avgViews || [],
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#9ca3af' }
                    },
                    y: {
                        grid: { color: 'rgba(31, 41, 55, 0.6)' },
                        ticks: { color: '#9ca3af' }
                    }
                },
                plugins: {
                    legend: {
                        labels: { color: '#e5e7eb' }
                    },
                    tooltip: {
                        backgroundColor: '#020617',
                        titleColor: '#e5e7eb',
                        bodyColor: '#e5e7eb',
                        borderColor: '#1f2937',
                        borderWidth: 1,
                        callbacks: {
                            afterBody: function(context) {
                                if (!context || !context.length) return;
                                const idx = context[0].dataIndex;
                                const counts = weekdayData.videoCounts || [];
                                const count = counts[idx] || 0;
                                return '動画数: ' + count + ' 本';
                            }
                        }
                    }
                }
            }
        });
    }

    // 時間帯別 平均再生数
    const timeBandCanvas = document.getElementById('timeBandChart');
    if (timeBandCanvas && window.Chart) {
        const timeBandCtx = timeBandCanvas.getContext('2d');
        new Chart(timeBandCtx, {
            type: 'bar',
            data: {
                labels: timeBandData.labels || [],
                datasets: [
                    {
                        label: '平均再生数',
                        data: timeBandData.avgViews || [],
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#9ca3af', maxRotation: 0 }
                    },
                    y: {
                        grid: { color: 'rgba(31, 41, 55, 0.6)' },
                        ticks: { color: '#9ca3af' }
                    }
                },
                plugins: {
                    legend: {
                        labels: { color: '#e5e7eb' }
                    },
                    tooltip: {
                        backgroundColor: '#020617',
                        titleColor: '#e5e7eb',
                        bodyColor: '#e5e7eb',
                        borderColor: '#1f2937',
                        borderWidth: 1,
                        callbacks: {
                            afterBody: function(context) {
                                if (!context || !context.length) return;
                                const idx = context[0].dataIndex;
                                const counts = timeBandData.videoCounts || [];
                                const count = counts[idx] || 0;
                                return '動画数: ' + count + ' 本';
                            }
                        }
                    }
                }
            }
        });
    }
});
