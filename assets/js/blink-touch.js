/* ============================================================
   Blinks ONLY the button/link that the user touches or clicks.
   Works for mouse click, touch (mobile), and keyboard (Enter/Space).
   ============================================================ */
(function () {
  var SELECTOR = [
    "button", ".btn", ".button",
    'input[type="submit"]', 'input[type="button"]',
    "a.btn", "a.button", ".nav-item", ".sb-nav a",
    ".action-btn", ".card-btn", ".apply-btn",
    ".primary-btn", ".submit-btn"
  ].join(",");

  function blinkTarget(target) {
    var el = target.closest(SELECTOR);
    if (!el) return;

    // Restart animation cleanly even if already blinking
    el.classList.remove("blink-active");
    // Force reflow so the animation can be re-triggered
    void el.offsetWidth;
    el.classList.add("blink-active");
  }

  function clearBlink(e) {
    if (e.target.classList && e.target.classList.contains("blink-active")) {
      e.target.classList.remove("blink-active");
    }
  }

  document.addEventListener("click", function (e) {
    blinkTarget(e.target);
  });

  document.addEventListener("touchstart", function (e) {
    blinkTarget(e.target);
  }, { passive: true });

  document.addEventListener("animationend", clearBlink);
})();
