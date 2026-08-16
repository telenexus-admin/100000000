/* Universal Hotspot portal — tabs/device pages disabled. */
(function () {
  try {
    if (!/login\.html$/i.test(location.pathname)) {
      location.replace('login.html' + (location.search || ''));
    }
  } catch (e) {}
})();
