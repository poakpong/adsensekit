/**
 * @file
 * AdsenseKit sticky bottom ad: close button (no persistence) + GA4 tracking.
 *
 * The user must close the ad on every page load — we deliberately do NOT
 * remember the closed state in localStorage/sessionStorage/cookies.
 *
 * When the close button is clicked, a GA4 event is sent via gtag() if the
 * function is available on the page (i.e. GA4 snippet is loaded).
 * Event name : close_sticky_ad
 * Parameters : event_category = "AdsenseKit"
 *              event_label    = "sticky_bottom"
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
          // Hide the sticky ad for this page load.
          var wrapper = btn.closest('.adsensekit-sticky');
          if (wrapper) {
            wrapper.classList.add('adsensekit-sticky--closed');
          }

          // Send GA4 event if gtag is available.
          if (typeof gtag === 'function') {
            gtag('event', 'close_sticky_ad', {
              event_category: 'AdsenseKit',
              event_label: 'sticky_bottom',
            });
          }
        });
      });
    }
  };

})(Drupal, once);
