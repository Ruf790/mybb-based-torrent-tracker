(function () {
  if (!window.flatpickr) return;

  const baseOpts = {
    dateFormat:    'Y-m-d',
    altInput:      true,
    altFormat:     'd F Y',
    allowInput:    true,
    locale:        flatpickr.l10ns.ru,
    disableMobile: true,
    static:        true
  };

  const fpAdded   = flatpickr('#added',       baseOpts);
  const fpRegTo   = flatpickr('#reg_to',      baseOpts);
  const fpActFrom = flatpickr('#active_from', baseOpts);
  const fpActTo   = flatpickr('#active_to',   baseOpts);

  function linkRange(fromFP, toFP) {
    if (!fromFP || !toFP) return;
    fromFP.config.onChange.push(sel => {
      toFP.set('minDate', sel && sel[0] ? sel[0] : null);
    });
    toFP.config.onChange.push(sel => {
      fromFP.set('maxDate', sel && sel[0] ? sel[0] : null);
    });
    if (fromFP.input.value) toFP.set('minDate', fromFP.selectedDates[0] || fromFP.input.value);
    if (toFP.input.value)   fromFP.set('maxDate', toFP.selectedDates[0] || toFP.input.value);
  }

  linkRange(fpAdded,   fpRegTo);
  linkRange(fpActFrom, fpActTo);

  document.querySelectorAll('[data-clear]').forEach(btn => {
    btn.addEventListener('click', () => {
      const el = document.querySelector(btn.getAttribute('data-clear'));
      if (!el || !el._flatpickr) return;
      el._flatpickr.clear();
      [fpAdded, fpRegTo, fpActFrom, fpActTo].forEach(fp => {
        if (fp) { fp.set('minDate', null); fp.set('maxDate', null); }
      });
    });
  });
})();