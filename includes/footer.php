<footer class="footer py-4 text-center" id="footer">
  <div class="container">
    <p class="mb-1">&copy; 2026 Art's Gym. All Rights Reserved.</p>
    <p class="mb-0">
      <a href="https://facebook.com" target="_blank" class="footer-link">Facebook</a> |
      <a href="contact.php" class="footer-link">Contact Us</a>
    </p>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<style>
:root {
  --footer-bg: #56585c;
  --footer-color: #f5f5f5;
  --footer-link-hover: #ffd700;
  --sidebar-width: 220px;
  --sidebar-collapsed-width: 70px;
}

footer.footer {
  background: var(--footer-bg);
  color: var(--footer-color);
  font-family: 'Poppins', sans-serif;
  font-size: 0.95rem;
  letter-spacing: 0.5px;
  padding: 1rem;
  position: fixed;
  bottom: 0;
  left: var(--sidebar-width);
  width: calc(100% - var(--sidebar-width));
  transition: left 0.3s, width 0.3s;
  z-index: 10;
}

footer.footer a.footer-link {
  color: var(--footer-color);
  text-decoration: none;
  margin: 0 8px;
  transition: color 0.3s;
}

footer.footer a.footer-link:hover {
  color: var(--footer-link-hover);
  text-decoration: underline;
}

footer.footer .container {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}

@media (min-width: 576px) {
  footer.footer .container {
    flex-direction: row;
    justify-content: space-between;
  }
}

/* Adjust footer when sidebar is collapsed */
footer.footer.collapsed {
  left: var(--sidebar-collapsed-width);
  width: calc(100% - var(--sidebar-collapsed-width));
}
</style>

<script>
// Toggle footer when sidebar collapses/expands
function updateFooter() {
    const main = document.getElementById('main');
    const footer = document.getElementById('footer');
    if(main.classList.contains('expanded')){
        footer.classList.add('collapsed');
    } else {
        footer.classList.remove('collapsed');
    }
}

// Example: call this when your sidebar toggles
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('main').classList.toggle('expanded');
    updateFooter();
}

// Run on page load in case sidebar is already collapsed
updateFooter();
</script>
