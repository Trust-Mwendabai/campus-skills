(function () {
  'use strict';
  var node = document.getElementById('chartData');
  if (!node || typeof Chart === 'undefined') return;
  var d = JSON.parse(node.textContent);

  var css = getComputedStyle(document.documentElement);
  var text = css.getPropertyValue('--muted').trim() || '#5a6685';
  var grid = css.getPropertyValue('--border').trim() || '#dde3f0';
  var PALETTE = ['#2563eb', '#16a34a', '#f59e0b', '#7c3aed', '#0891b2', '#db2777', '#ea580c', '#64748b', '#84cc16'];

  Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
  Chart.defaults.color = text;
  Chart.defaults.maintainAspectRatio = false;

  var axes = {
    x: { grid: { display: false } },
    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: grid } }
  };

  function line(id, datasets, fill) {
    new Chart(document.getElementById(id), {
      type: 'line',
      data: { labels: d.labels, datasets: datasets.map(function (s, i) {
        return { label: s.label, data: s.data, borderColor: PALETTE[i], backgroundColor: PALETTE[i] + '22', fill: !!fill, tension: .35, pointRadius: 3 };
      }) },
      options: { scales: axes, plugins: { legend: { display: datasets.length > 1, position: 'bottom' } } }
    });
  }
  function bars(id, src, horizontal) {
    new Chart(document.getElementById(id), {
      type: 'bar',
      data: { labels: src.labels, datasets: [{ data: src.data, backgroundColor: PALETTE[0], borderRadius: 6 }] },
      options: { indexAxis: horizontal ? 'y' : 'x', scales: horizontal ? { x: axes.y, y: axes.x } : axes, plugins: { legend: { display: false } } }
    });
  }

  line('chRegistrations', [{ label: 'New students', data: d.registrations }], true);
  line('chCompleted', [{ label: 'Completed exchanges', data: d.completed }], true);
  bars('chPopular', d.popular, true);
  bars('chRequested', d.requested, true);
  line('chActivity', [
    { label: 'Requests', data: d.requests },
    { label: 'Messages', data: d.messages },
    { label: 'Sessions', data: d.sessions }
  ], false);

  new Chart(document.getElementById('chCategories'), {
    type: 'doughnut',
    data: { labels: d.categories.labels, datasets: [{ data: d.categories.data, backgroundColor: PALETTE, borderWidth: 0 }] },
    options: { cutout: '62%', plugins: { legend: { position: 'right', labels: { boxWidth: 12 } } } }
  });
})();
