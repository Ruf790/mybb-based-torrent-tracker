(() => { // tiny mapper: подбираем иконку по тексту/URL ссылки (текст приходит из ланга, EN или RU)
  const map = [
    // unban must come before ban, otherwise "Lift ban" never reaches it
    [/unban|liftban|снять\s*бан/i, 'bi-shield-check'],
    [/ban|бан/i,                   'bi-slash-circle'],
    [/edit|profil|редакт|измен/i,  'bi-pencil-square'],
    [/warn|предуп/i,               'bi-exclamation-triangle'],
    [/ip|whois/i,                  'bi-geo'],
    [/delete|purge|удал/i,         'bi-trash3'],
    [/note|замет/i,                'bi-journal-text'],
    [/merge|объед/i,               'bi-diagram-3'],
    [/spam|спам/i,                 'bi-bug']
  ];

  document.querySelectorAll('#mod-actions a').forEach(a => {
    const txt  = (a.textContent || '').trim();
    const href = a.getAttribute('href') || '';
    const rule = map.find(([re]) => re.test(txt) || re.test(href));
    const icon = (rule && rule[1]) || 'bi-gear';

    // built as DOM nodes: the label is lang text, never injected as HTML
    const i = document.createElement('i');
    i.className = 'bi ' + icon;
    i.setAttribute('aria-hidden', 'true');

    const label = document.createElement('span');
    label.className = 'visually-hidden';
    label.textContent = txt;

    a.replaceChildren(i, label);
    a.title = txt;
  });
})();
