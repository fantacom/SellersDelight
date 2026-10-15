'use strict';

document.addEventListener('DOMContentLoaded', () => {
  // Mobile Navigation
  const toggle = document.querySelector('.nav-toggle');
  const nav = document.querySelector('.main-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', () => {
      const open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', String(open));
    });
  }

  // Sicherheitsabfrage für destruktive Aktionen
  document.addEventListener('submit', (e) => {
    const msg = e.target.dataset.confirm;
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  // Produkte dynamisch hinzufügen/entfernen
  const rows = document.querySelector('[data-product-rows]');
  const tpl = document.getElementById('product-row-template');
  const addBtn = document.querySelector('[data-add-product]');
  if (rows && tpl && addBtn) {
    addBtn.addEventListener('click', () => {
      rows.appendChild(tpl.content.cloneNode(true));
      rows.lastElementChild.querySelector('input')?.focus();
    });
    rows.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-remove-product]');
      if (btn) btn.closest('.product-row').remove();
    });
  }

  // Rechte-Checkboxen bei Zuweisungen sofort speichern
  document.querySelectorAll('form[data-autosubmit] input[type=checkbox]').forEach((cb) => {
    cb.addEventListener('change', () => cb.form.requestSubmit());
  });
});
