const filters = { direction: 'all', trainer: 'all', group: 'all' };
const groups = [...document.querySelectorAll('[data-filter-group]')];
const reset = document.querySelector('[data-schedule-reset]');
const empty = document.querySelector('[data-schedule-empty]');
const status = document.querySelector('[data-schedule-status]');

function applyFilters() {
  const rows = [...document.querySelectorAll('[data-schedule-row]')];
  let visible = 0;
  rows.forEach(row => {
    const matches = Object.entries(filters).every(([key, value]) => value === 'all' || row.dataset[key] === value);
    row.hidden = !matches;
    if (matches) visible++;
  });
  groups.forEach(group => group.querySelectorAll('[data-filter-value]').forEach(button => {
    button.setAttribute('aria-pressed', String(button.dataset.filterValue === filters[group.dataset.filterGroup]));
  }));
  reset.hidden = Object.values(filters).every(value => value === 'all');
  empty.hidden = visible > 0;
  empty.querySelector('h3').textContent = rows.length ? 'По выбранным фильтрам занятий нет' : 'Уточните время занятий';
  empty.querySelector('p').textContent = rows.length
    ? 'Попробуйте другую группу, направление или тренера. Также можно уточнить время по телефону.'
    : 'Позвоните в ZUBR GYM — подскажем актуальное расписание вашей группы.';
  status.textContent = rows.length ? `Найдено занятий: ${visible}` : 'Время занятий можно уточнить по телефону.';
}

groups.forEach(group => group.addEventListener('click', event => {
  const button = event.target.closest('[data-filter-value]');
  if (!button || !group.contains(button)) return;
  filters[group.dataset.filterGroup] = button.dataset.filterValue;
  applyFilters();
}));
reset.addEventListener('click', () => {
  Object.keys(filters).forEach(key => { filters[key] = 'all'; });
  applyFilters();
});
applyFilters();
