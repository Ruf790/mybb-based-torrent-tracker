(()=>{ // tiny mapper: подбираем иконку по тексту/URL
  const map = [
    [/ban/i,        'bi-slash-circle'],
    [/unban/i,      'bi-shield-check'],
    [/edit|profil/i,'bi-pencil-square'],
    [/warn|предуп/i,'bi-exclamation-triangle'],
    [/ip|whois/i,   'bi-geo'],
    [/delete|удал/i,'bi-trash3'],
    [/note|замет/i, 'bi-journal-text'],
    [/merge|объед/i,'bi-diagram-3'],
    [/spam/i,       'bi-bug']
  ];

  document.querySelectorAll('#mod-actions a').forEach(a=>{
    const txt = (a.textContent || '').trim();
    const href = a.getAttribute('href') || '';
    const rule = map.find(([re]) => re.test(txt) || re.test(href));
    const icon = (rule && rule[1]) || 'bi-gear';

    a.innerHTML = `<i class="bi ${icon}" aria-hidden="true"></i>
                   <span class="visually-hidden">${txt}</span>`;
    a.title = txt;
  });
})();