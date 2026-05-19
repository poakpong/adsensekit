<?php

namespace Drupal\adsensekit\Render;

use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Trusted #post_render callback host for the in-content ad injector.
 *
 * Drupal requires #post_render / #pre_render / #lazy_builder callbacks to
 * either be anonymous functions or be declared on a class that implements
 * TrustedCallbackInterface. See https://www.drupal.org/node/2966725.
 */
class AdInjector implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['postRender'];
  }

  /**
   * #post_render callback: inject ads into rendered HTML after every N <p>.
   *
   * @param string|\Drupal\Component\Render\MarkupInterface $html
   *   The rendered HTML produced by the body field.
   * @param array $element
   *   The render element (unused, kept for API compatibility).
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   The HTML with ads injected, or the original HTML if injection is
   *   disabled or misconfigured.
   */
  public static function postRender($html, array $element) {
    $config = \Drupal::config('adsensekit.settings');
    $publisher_id = adsensekit_normalize_publisher_id($config->get('publisher_id'));
    if ($publisher_id === '') {
      return $html;
    }

    $ad_slot = trim((string) $config->get('injector.ad_slot'));
    if ($ad_slot === '') {
      return $html;
    }

    $every_n = max(1, (int) $config->get('injector.every_n_paragraphs'));
    $max_inserts = max(0, (int) $config->get('injector.max_inserts'));
    $ad_format = $config->get('injector.ad_format') ?: 'auto';
    $full_width = $config->get('injector.full_width_responsive') ? 'true' : 'false';

    $html_str = (string) $html;

    $ad_markup = adsensekit_build_ad_markup($publisher_id, $ad_slot, $ad_format, $full_width);

    return adsensekit_inject_after_paragraphs($html_str, $ad_markup, $every_n, $max_inserts);
  }

}
