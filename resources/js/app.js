import './bootstrap';

const sidebar = document.querySelector('#sidebar');
const menuToggle = document.querySelector('[data-menu-toggle]');

menuToggle?.addEventListener('click', () => sidebar?.classList.toggle('open'));

document.querySelector('[data-global-search]')?.addEventListener('input', (event) => {
    const query = event.target.value.toLowerCase().trim();

    document.querySelectorAll('[data-search]').forEach((item) => {
        item.hidden = query !== '' && !item.dataset.search.includes(query);
    });
});

document.querySelectorAll('[data-filter]').forEach((button) => {
    button.addEventListener('click', () => {
        document.querySelectorAll('[data-filter]').forEach((tab) => tab.classList.remove('active'));
        button.classList.add('active');

        document.querySelectorAll('[data-status]').forEach((card) => {
            card.hidden = button.dataset.filter !== 'all' && card.dataset.status !== button.dataset.filter;
        });
    });
});
