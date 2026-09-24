/**
 * WIP Management Portal - Simple Dashboard Logic
 */

document.addEventListener('DOMContentLoaded', () => {
  const menuToggleBtn = document.getElementById('menuToggleBtn');
  const sidebar = document.getElementById('sidebar');
  const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
  const sidebarBackdrop = document.getElementById('sidebarBackdrop');
  const sheetSearch = document.getElementById('sheetSearch');
  const tableRows = document.querySelectorAll('#sheetsTable tbody tr');
  const addSheetBtn = document.getElementById('addSheetBtn');
  const logoutLink = document.getElementById('logoutLink');

  // Sidebar toggle for mobile
  const openSidebar = () => {
    if (sidebar) sidebar.classList.add('open');
    if (sidebarBackdrop) sidebarBackdrop.classList.add('active');
  };

  const closeSidebar = () => {
    if (sidebar) sidebar.classList.remove('open');
    if (sidebarBackdrop) sidebarBackdrop.classList.remove('active');
  };

  if (menuToggleBtn) menuToggleBtn.addEventListener('click', openSidebar);
  if (sidebarCloseBtn) sidebarCloseBtn.addEventListener('click', closeSidebar);
  if (sidebarBackdrop) sidebarBackdrop.addEventListener('click', closeSidebar);

  // Simple real-time search
  if (sheetSearch) {
    sheetSearch.addEventListener('input', () => {
      const q = sheetSearch.value.toLowerCase().trim();
      tableRows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(q) ? '' : 'none';
      });
    });
  }

  // Simple add sheet button action
  if (addSheetBtn) {
    addSheetBtn.addEventListener('click', () => {
      const name = prompt('Enter new WIP Sheet name (e.g., Weekly_Report.xlsx):');
      if (name && name.trim()) {
        alert(`New sheet "${name.trim()}" added to WIP queue!`);
      }
    });
  }

  // Logout confirmation
  if (logoutLink) {
    logoutLink.addEventListener('click', (e) => {
      if (!confirm('Do you want to log out?')) {
        e.preventDefault();
      }
    });
  }
});
