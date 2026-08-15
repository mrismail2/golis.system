/* =====================================================================
   Gollis University - small helpers for the public site and the portal.
   ===================================================================== */

/** Public site: open / close the mobile navigation. */
function toggleMenu() {
  var links = document.querySelector('.links');
  if (!links) return;
  var open = links.style.display === 'flex';
  links.style.display = open ? 'none' : 'flex';
  if (!open) {
    links.style.position = 'absolute';
    links.style.top = '86px';
    links.style.left = '0';
    links.style.right = '0';
    links.style.background = '#fff';
    links.style.padding = '20px 6%';
    links.style.flexDirection = 'column';
    links.style.alignItems = 'flex-start';
    links.style.boxShadow = '0 12px 20px rgba(0,0,0,.08)';
  }
}

/** Portal: slide the sidebar in and out on small screens. */
function toggleSidebar() {
  var sidebar = document.getElementById('sidebar');
  if (sidebar) sidebar.classList.toggle('open');
}

/** Filter the rows of a table from a search box. */
function filterTable(inputId, tableId) {
  var input = document.getElementById(inputId);
  var table = document.getElementById(tableId);
  if (!input || !table) return;

  var query = input.value.toLowerCase();
  var rows = table.querySelectorAll('tbody tr');
  var shown = 0;

  rows.forEach(function (row) {
    if (row.hasAttribute('data-empty')) return;
    var match = row.textContent.toLowerCase().indexOf(query) !== -1;
    row.style.display = match ? '' : 'none';
    if (match) shown++;
  });

  var counter = document.querySelector('[data-count-for="' + tableId + '"]');
  if (counter) counter.textContent = shown + ' record' + (shown === 1 ? '' : 's');
}

document.addEventListener('DOMContentLoaded', function () {
  /* Ask before following a delete link. */
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (event) {
      if (!window.confirm(el.getAttribute('data-confirm'))) event.preventDefault();
    });
  });

  /* Submit a filter form as soon as a drop-down changes. */
  document.querySelectorAll('[data-auto-submit] select').forEach(function (select) {
    select.addEventListener('change', function () {
      select.form.submit();
    });
  });

  /* Tick / untick every row of an attendance register. */
  var master = document.querySelector('[data-check-all]');
  if (master) {
    master.addEventListener('change', function () {
      var name = master.getAttribute('data-check-all');
      document.querySelectorAll('input[type="radio"][data-bulk="' + name + '"]').forEach(function (radio) {
        if (radio.value === master.value) radio.checked = true;
      });
    });
  }

  /* Hide flash messages after a few seconds. */
  window.setTimeout(function () {
    document.querySelectorAll('.alert').forEach(function (alert) {
      alert.style.transition = 'opacity .6s';
      alert.style.opacity = '0';
      window.setTimeout(function () { alert.remove(); }, 700);
    });
  }, 6000);
});
