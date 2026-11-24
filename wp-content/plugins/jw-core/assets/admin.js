(function () {
  'use strict';
  let serial = 0;
  function renumber(repeater) {
    const rows = [...repeater.querySelector('.jw-rows').children];
    rows.forEach((row, index) => {
      row.querySelector('.jw-number').textContent = String(index + 1);
      row.querySelector('.jw-up').disabled = index === 0;
      row.querySelector('.jw-down').disabled = index === rows.length - 1;
    });
    repeater.querySelector('.jw-add').disabled = rows.length >= 20;
  }
  document.querySelectorAll('.jw-repeater').forEach(renumber);
  document.addEventListener('click', (event) => {
    const button = event.target.closest('button');
    if (!button) return;
    const repeater = button.closest('.jw-repeater');
    if (repeater && button.classList.contains('jw-add')) {
      const rows = repeater.querySelector('.jw-rows');
      if (rows.children.length >= 20) return;
      const template = repeater.querySelector('template');
      const host = document.createElement('div');
      host.innerHTML = template.innerHTML.replaceAll('__ROW__', `new${Date.now()}_${serial++}`);
      const row = host.firstElementChild;
      // Unique IDs are required even for duplicated fields/media pickers.
      row.querySelectorAll('[id]').forEach((input) => {
        const old = input.id;
        input.id = `${old}_${serial++}`;
        row.querySelectorAll('label').forEach((label) => { if (label.htmlFor === old) label.htmlFor = input.id; });
      });
      rows.append(row);
      renumber(repeater);
      row.querySelector('input:not([type=hidden]),textarea,select,button')?.focus();
    }
    const row = button.closest('.jw-row');
    if (row && repeater) {
      if (button.classList.contains('jw-remove')) { row.remove(); repeater.querySelector('.jw-add').focus(); }
      if (button.classList.contains('jw-up') && row.previousElementSibling) row.previousElementSibling.before(row);
      if (button.classList.contains('jw-down') && row.nextElementSibling) row.nextElementSibling.after(row);
      renumber(repeater);
    }
    const media = button.closest('.jw-media');
    if (media && button.classList.contains('jw-clear')) { media.querySelector('input').value = '0'; media.querySelector('img').hidden = true; }
    if (media && button.classList.contains('jw-pick')) {
      const frame = wp.media({ title: 'Selecionar imagem', library: { type: 'image' }, multiple: false });
      frame.on('select', () => {
        const attachment = frame.state().get('selection').first().toJSON();
        media.querySelector('input').value = attachment.id;
        const preview = media.querySelector('img');
        preview.src = attachment.sizes?.medium?.url || attachment.url;
        preview.hidden = false;
      });
      frame.open();
    }
  });
})();
