const password = document.getElementById('login-password');
const toggle = document.querySelector('.login-password-toggle');
if (password && toggle) {
  toggle.hidden = false;
  const setVisible = visible => {
    password.type = visible ? 'text' : 'password';
    toggle.textContent = visible ? 'Hide' : 'Show';
    toggle.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
    toggle.setAttribute('aria-pressed', String(visible));
  };
  toggle.addEventListener('click', () => setVisible(password.type === 'password'));
  window.addEventListener('pageshow', () => setVisible(false));
  document.addEventListener('visibilitychange', () => { if (document.hidden) setVisible(false); });
}
