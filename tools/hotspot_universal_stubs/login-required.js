/* Universal Hotspot portal — always use login.html (no device-specific pages). */
(function () {
  try {
    if (!/login\.html$/i.test(location.pathname)) {
      location.replace('login.html' + (location.search || ''));
    }
  } catch (e) {}
})();
