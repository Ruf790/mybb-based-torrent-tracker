/* index.js — главная трекера (index.php): предпросмотр раздачи + графики Highcharts */
(function () {
  'use strict';

  const L = (typeof AGS_LANG === 'object' && AGS_LANG) ? AGS_LANG : {};
  function t(key, fallback, ...args) {
    let s = (typeof L[key] === 'string') ? L[key] : fallback;
    args.forEach((a, i) => {
      const n = i + 1;
      s = s.split('{' + n + '}').join(String(a)).split('%' + n + '$s').join(String(a));
    });
    return s;
  }

  function esc(s) {
    return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  /* ── Предпросмотр раздачи в модалке ─────────────────────────────────── */
  function initPreview() {
    const box = document.getElementById('torrentModalContent');
    if (!box) return;
    let ctrl = null;

    function setState(cls, text, spinner) {
      const wrap = document.createElement('div');
      wrap.className = 'text-center py-4 ' + cls;
      if (spinner) {
        const sp = document.createElement('div');
        sp.className = 'spinner-border text-primary';
        sp.setAttribute('role', 'status');
        wrap.appendChild(sp);
      }
      const p = document.createElement('p');
      p.className = spinner ? 'mt-2 mb-0' : 'mb-0';
      p.textContent = text;
      wrap.appendChild(p);
      box.replaceChildren(wrap);
    }

    document.querySelectorAll('.torrent-preview').forEach(el => {
      el.addEventListener('click', e => {
        e.preventDefault();
        const id = parseInt(el.dataset.id || '0', 10);
        if (!id) return;
        if (ctrl) ctrl.abort();
        ctrl = new AbortController();
        setState('text-body-secondary', t('preview_loading', 'Loading torrent details...'), true);
        fetch('torrent_preview.php?id=' + id, { signal: ctrl.signal, credentials: 'same-origin' })
          .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
          .then(html => { box.innerHTML = html; })
          .catch(err => {
            if (err && err.name === 'AbortError') return;
            setState('text-danger', t('preview_failed', 'Failed to load torrent preview.'), false);
          });
      });
    });
  }

  /* ── Графики ─────────────────────────────────────────────────────────── */
  function initCharts() {
    if (typeof Highcharts === 'undefined') return;
    const dataEl = document.getElementById('ix-chart-data');
    const page = document.querySelector('.ix-page');
    if (!dataEl || !page) return;

    let data;
    try { data = JSON.parse(dataEl.textContent || '{}'); } catch (e) { return; }

    const css = name => getComputedStyle(page).getPropertyValue(name).trim();
    const short = s => (s.length > 28 ? s.slice(0, 27) + '…' : s);

    function theme() {
      const text = css('--bs-body-color') || '#212529';
      const muted = css('--bs-secondary-color') || '#6c757d';
      const grid = css('--bs-border-color') || '#dee2e6';
      return {
        chart: { backgroundColor: 'transparent', style: { fontFamily: 'inherit' } },
        xAxis: { labels: { style: { color: text } }, lineColor: grid, tickColor: grid },
        yAxis: { labels: { style: { color: muted } }, gridLineColor: grid, title: { style: { color: muted } } },
        tooltip: { backgroundColor: css('--bs-body-bg') || '#fff', borderColor: grid, style: { color: text } }
      };
    }

    function build(id, set, yTitle, seriesName, suffix, colorVar) {
      const el = document.getElementById(id);
      if (!el || !set || !Array.isArray(set.values) || !set.values.length) return null;
      const th = theme();
      return Highcharts.chart(el, {
        chart: Object.assign({ type: 'bar' }, th.chart),
        title: { text: null },
        credits: { enabled: false },
        legend: { enabled: false },
        xAxis: Object.assign({
          categories: set.names,
          labels: Object.assign({ formatter() { return esc(short(String(this.value))); } }, th.xAxis.labels)
        }, { lineColor: th.xAxis.lineColor, tickColor: th.xAxis.tickColor }),
        yAxis: {
          min: 0, allowDecimals: false,
          title: Object.assign({ text: yTitle }, th.yAxis.title),
          labels: th.yAxis.labels, gridLineColor: th.yAxis.gridLineColor
        },
        tooltip: Object.assign({
          formatter() {
            return '<b>' + esc(String(this.x ?? this.key)) + '</b><br>' +
              esc(seriesName) + ': ' + Highcharts.numberFormat(this.y, 0, '', ' ') + ' ' + esc(suffix);
          }
        }, th.tooltip),
        plotOptions: { bar: { borderRadius: 4, borderWidth: 0, colorByPoint: false } },
        series: [{ name: seriesName, data: set.values, color: css(colorVar) || '#0d6efd' }]
      });
    }

    const charts = [
      build('popularTorrentsChart', data.popular, t('axis_hits', 'Hits'), t('series_hits', 'Hits'), t('suffix_hits', 'hits'), '--bs-warning'),
      build('activeTorrentsChart', data.active, t('axis_completed', 'Completed Times'), t('series_completed', 'Completed'), t('suffix_completed', 'completions'), '--bs-info')
    ].filter(Boolean);

    // Переключение светлой/тёмной темы — перекрашиваем графики
    if (charts.length && 'MutationObserver' in window) {
      new MutationObserver(() => {
        const th = theme();
        charts.forEach(c => c.update({
          xAxis: { labels: { style: th.xAxis.labels.style }, lineColor: th.xAxis.lineColor, tickColor: th.xAxis.tickColor },
          yAxis: { labels: th.yAxis.labels, gridLineColor: th.yAxis.gridLineColor, title: { style: th.yAxis.title.style } },
          tooltip: th.tooltip
        }));
      }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    initPreview();
    initCharts();
  });
})();
