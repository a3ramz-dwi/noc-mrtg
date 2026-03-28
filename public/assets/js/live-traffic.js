/**
 * NOC Dashboard - Live Traffic Chart (auto-update)
 */
const LiveTrafficChart = (function () {
  'use strict';

  const MAX_POINTS = 60;

  function create(canvasId, targetType, targetId) {
    const instance = {
      canvasId: canvasId,
      targetType: targetType,
      targetId: targetId,
      chart: null,
      timer: null,
      running: false,
      labels: [],
      inData: [],
      outData: [],
      lastTimestamp: null,
    };

    instance.init = function () {
      const canvas = document.getElementById(canvasId);
      if (!canvas || typeof Chart === 'undefined') {
        console.warn('[LiveTraffic] Canvas or Chart.js not found:', canvasId);
        return instance;
      }
      NOCCharts.applyDarkDefaults();
      instance.chart = new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
          labels: [],
          datasets: [
            {
              label: 'In',
              data: [],
              borderColor: NOCCharts.colors.green,
              backgroundColor: NOCCharts.colors.greenAlpha,
              borderWidth: 2,
              pointRadius: 0,
              tension: 0.4,
              fill: true,
            },
            {
              label: 'Out',
              data: [],
              borderColor: NOCCharts.colors.orange,
              backgroundColor: NOCCharts.colors.orangeAlpha,
              borderWidth: 2,
              pointRadius: 0,
              tension: 0.4,
              fill: true,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: { duration: 300 },
          interaction: { mode: 'index', intersect: false },
          scales: {
            x: {
              grid: { color: NOCCharts.colors.gridColor },
              ticks: { color: NOCCharts.colors.textColor, maxTicksLimit: 10, maxRotation: 0 },
            },
            y: {
              grid: { color: NOCCharts.colors.gridColor },
              ticks: {
                color: NOCCharts.colors.textColor,
                callback: function (v) { return instance.formatBandwidth(v); },
              },
              beginAtZero: true,
            },
          },
          plugins: {
            legend: { position: 'top', align: 'end' },
            tooltip: {
              callbacks: {
                label: function (ctx) {
                  return ctx.dataset.label + ': ' + instance.formatBandwidth(ctx.parsed.y);
                },
              },
            },
          },
        },
      });
      return instance;
    };

    instance.start = function (interval) {
      interval = interval || 5000;
      if (instance.running) return instance;
      instance.running = true;
      instance.fetchLatest();
      instance.timer = setInterval(function () {
        instance.fetchLatest();
      }, interval);
      return instance;
    };

    instance.stop = function () {
      instance.running = false;
      if (instance.timer) {
        clearInterval(instance.timer);
        instance.timer = null;
      }
      return instance;
    };

    instance.fetchLatest = function () {
      const url = '/monitoring/chart-data/' + encodeURIComponent(targetType) + '/' + encodeURIComponent(targetId);
      NOCApp.get(url)
        .then(function (data) {
          if (data && data.success) {
            instance.updateChart(data);
          }
        })
        .catch(function (err) {
          console.warn('[LiveTraffic] Fetch error:', err);
        });
    };

    instance.updateChart = function (data) {
      if (!instance.chart) return;
      const now = new Date();
      const timeLabel = now.getHours().toString().padStart(2, '0') + ':' +
        now.getMinutes().toString().padStart(2, '0') + ':' +
        now.getSeconds().toString().padStart(2, '0');

      if (data.labels && data.in_data && data.out_data) {
        // Full dataset replacement
        instance.labels = data.labels.slice(-MAX_POINTS);
        instance.inData = data.in_data.slice(-MAX_POINTS);
        instance.outData = data.out_data.slice(-MAX_POINTS);
      } else {
        // Sliding window - append latest point
        instance.labels.push(timeLabel);
        instance.inData.push(data.in || 0);
        instance.outData.push(data.out || 0);
        if (instance.labels.length > MAX_POINTS) {
          instance.labels.shift();
          instance.inData.shift();
          instance.outData.shift();
        }
      }

      instance.chart.data.labels = instance.labels;
      instance.chart.data.datasets[0].data = instance.inData;
      instance.chart.data.datasets[1].data = instance.outData;
      instance.chart.update('none');

      // Update bandwidth display elements if present
      const inEl = document.getElementById('live-bw-in-' + targetId);
      const outEl = document.getElementById('live-bw-out-' + targetId);
      if (inEl) inEl.textContent = instance.formatBandwidth(data.in || (instance.inData[instance.inData.length - 1] || 0));
      if (outEl) outEl.textContent = instance.formatBandwidth(data.out || (instance.outData[instance.outData.length - 1] || 0));
    };

    instance.formatBandwidth = function (bps) {
      if (!bps || bps === 0) return '0 bps';
      const k = 1000;
      const sizes = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];
      const i = Math.floor(Math.log(Math.abs(bps)) / Math.log(k));
      return parseFloat((bps / Math.pow(k, Math.min(i, sizes.length - 1))).toFixed(2)) + ' ' + sizes[Math.min(i, sizes.length - 1)];
    };

    instance.destroy = function () {
      instance.stop();
      if (instance.chart) {
        instance.chart.destroy();
        instance.chart = null;
      }
    };

    instance.resize = function () {
      if (instance.chart) instance.chart.resize();
    };

    instance.setTarget = function (type, id) {
      instance.targetType = type;
      instance.targetId = id;
      instance.labels = [];
      instance.inData = [];
      instance.outData = [];
      if (instance.chart) {
        instance.chart.data.labels = [];
        instance.chart.data.datasets[0].data = [];
        instance.chart.data.datasets[1].data = [];
        instance.chart.update('none');
      }
    };

    return instance;
  }

  // Auto-init from data attributes
  function autoInit() {
    document.querySelectorAll('[data-live-chart]').forEach(function (canvas) {
      const type = canvas.dataset.targetType || 'interface';
      const id = canvas.dataset.targetId;
      const interval = parseInt(canvas.dataset.interval || '5000', 10);
      if (!id) return;
      create(canvas.id, type, id).init().start(interval);
    });
  }

  return {
    create: create,
    autoInit: autoInit,
  };
})();

document.addEventListener('DOMContentLoaded', function () {
  LiveTrafficChart.autoInit();
});
