/**
 * NOC Dashboard - Chart.js Configurations and Helpers
 */
const NOCCharts = (function () {
  'use strict';

  const colors = {
    green: '#3fb950',
    greenAlpha: 'rgba(63,185,80,0.15)',
    orange: '#d29922',
    orangeAlpha: 'rgba(210,153,34,0.15)',
    blue: '#58a6ff',
    blueAlpha: 'rgba(88,166,255,0.15)',
    red: '#f85149',
    redAlpha: 'rgba(248,81,73,0.15)',
    purple: '#bc8cff',
    purpleAlpha: 'rgba(188,140,255,0.15)',
    gridColor: 'rgba(48,54,61,0.8)',
    textColor: '#8b949e',
  };

  function applyDarkDefaults() {
    if (typeof Chart === 'undefined') return;
    Chart.defaults.color = colors.textColor;
    Chart.defaults.borderColor = colors.gridColor;
    Chart.defaults.font.family = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.plugins.legend.labels.boxWidth = 12;
    Chart.defaults.plugins.legend.labels.padding = 16;
    Chart.defaults.plugins.tooltip.backgroundColor = '#1c2128';
    Chart.defaults.plugins.tooltip.borderColor = '#30363d';
    Chart.defaults.plugins.tooltip.borderWidth = 1;
    Chart.defaults.plugins.tooltip.titleColor = '#e6edf3';
    Chart.defaults.plugins.tooltip.bodyColor = '#8b949e';
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 6;
  }

  function createTrafficChart(canvasId, labels, inData, outData) {
    const canvas = document.getElementById(canvasId);
    if (!canvas || typeof Chart === 'undefined') return null;
    applyDarkDefaults();
    return new Chart(canvas.getContext('2d'), {
      type: 'line',
      data: {
        labels: labels,
        datasets: [
          {
            label: 'Traffic In',
            data: inData,
            borderColor: colors.green,
            backgroundColor: colors.greenAlpha,
            borderWidth: 2,
            pointRadius: 2,
            pointHoverRadius: 5,
            tension: 0.4,
            fill: true,
          },
          {
            label: 'Traffic Out',
            data: outData,
            borderColor: colors.orange,
            backgroundColor: colors.orangeAlpha,
            borderWidth: 2,
            pointRadius: 2,
            pointHoverRadius: 5,
            tension: 0.4,
            fill: true,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        scales: {
          x: {
            grid: { color: colors.gridColor },
            ticks: { color: colors.textColor, maxTicksLimit: 12 },
          },
          y: {
            grid: { color: colors.gridColor },
            ticks: {
              color: colors.textColor,
              callback: function (value) { return formatBytesForChart(value); },
            },
            beginAtZero: true,
          },
        },
        plugins: {
          legend: { position: 'top', align: 'end' },
          tooltip: {
            callbacks: {
              label: function (ctx) {
                return ctx.dataset.label + ': ' + formatBytesForChart(ctx.parsed.y);
              },
            },
          },
        },
      },
    });
  }

  function createBarChart(canvasId, labels, data) {
    const canvas = document.getElementById(canvasId);
    if (!canvas || typeof Chart === 'undefined') return null;
    applyDarkDefaults();
    return new Chart(canvas.getContext('2d'), {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          {
            label: 'Bandwidth',
            data: data,
            backgroundColor: labels.map(function (_, i) {
              return i % 2 === 0 ? colors.blueAlpha : colors.greenAlpha;
            }),
            borderColor: labels.map(function (_, i) {
              return i % 2 === 0 ? colors.blue : colors.green;
            }),
            borderWidth: 1,
            borderRadius: 4,
          },
        ],
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: {
            grid: { color: colors.gridColor },
            ticks: {
              color: colors.textColor,
              callback: function (v) { return formatBytesForChart(v); },
            },
            beginAtZero: true,
          },
          y: {
            grid: { display: false },
            ticks: { color: colors.textColor },
          },
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (ctx) {
                return ' ' + formatBytesForChart(ctx.parsed.x);
              },
            },
          },
        },
      },
    });
  }

  function updateChart(chart, labels, inData, outData) {
    if (!chart) return;
    chart.data.labels = labels;
    if (chart.data.datasets[0]) chart.data.datasets[0].data = inData;
    if (chart.data.datasets[1]) chart.data.datasets[1].data = outData;
    chart.update('none');
  }

  function formatBytesForChart(bytes) {
    if (!bytes || bytes === 0) return '0';
    const k = 1000;
    const sizes = ['bps', 'K', 'M', 'G', 'T'];
    const i = Math.floor(Math.log(Math.abs(bytes)) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + sizes[Math.min(i, sizes.length - 1)];
  }

  function createGaugeChart(canvasId, value, max, label) {
    const canvas = document.getElementById(canvasId);
    if (!canvas || typeof Chart === 'undefined') return null;
    applyDarkDefaults();
    const pct = Math.min(value / max, 1);
    const used = pct;
    const free = 1 - pct;
    const gaugeColor = pct > 0.85 ? colors.red : pct > 0.65 ? colors.orange : colors.green;
    return new Chart(canvas.getContext('2d'), {
      type: 'doughnut',
      data: {
        datasets: [{
          data: [used, free],
          backgroundColor: [gaugeColor, 'rgba(48,54,61,0.5)'],
          borderWidth: 0,
          circumference: 180,
          rotation: -90,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '75%',
        plugins: {
          legend: { display: false },
          tooltip: { enabled: false },
        },
      },
      plugins: [{
        id: 'gaugeText',
        afterDatasetsDraw: function (chart) {
          const ctx = chart.ctx;
          const cx = chart.width / 2;
          const cy = chart.height * 0.75;
          ctx.save();
          ctx.font = 'bold 20px sans-serif';
          ctx.fillStyle = '#e6edf3';
          ctx.textAlign = 'center';
          ctx.fillText(Math.round(pct * 100) + '%', cx, cy);
          ctx.font = '11px sans-serif';
          ctx.fillStyle = '#8b949e';
          ctx.fillText(label, cx, cy + 18);
          ctx.restore();
        },
      }],
    });
  }

  return {
    colors: colors,
    createTrafficChart: createTrafficChart,
    createBarChart: createBarChart,
    updateChart: updateChart,
    formatBytesForChart: formatBytesForChart,
    createGaugeChart: createGaugeChart,
    applyDarkDefaults: applyDarkDefaults,
  };
})();

document.addEventListener('DOMContentLoaded', function () {
  NOCCharts.applyDarkDefaults();
});
