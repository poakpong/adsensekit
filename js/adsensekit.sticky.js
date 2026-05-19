/**
 * @file
 * AdsenseKit sticky bottom ad: close button (no persistence).
 *
 * The user must close the ad on every page load — we deliberately do NOT
 * remember the closed state in localStorage/sessionStorage/cookies.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.adsensekitSticky = {
    attach: function (context) {
      var buttons = once(
        'adsensekit-sticky-close',
        '.adsensekit-sticky__close',
        context
      );

      buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          var wrapper = btn.closest('.adsensekit-sticky');
          if (wrapper) {
            wrapper.classList.add('adsensekit-sticky--closed');
          }
        });
      });
    }
  };

})(Drupal, once);
