/* Universal Hotspot portal — no device branching; login.html is the only UI. */
(function () {
  try {
    if (!/login\.html$/i.test(location.pathname)) {
      location.replace('login.html' + (location.search || ''));
    }
  } catch (e) {}
})();
