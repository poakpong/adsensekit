<?php

namespace Drupal\adsensekit\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a fixed-bottom sticky AdSense ad with a close button.
 *
 * Place this block in any region (typically a page-bottom region). It will
 * render as a fixed-position bar at the bottom of the viewport. The close
 * button hides it for the current page load only — the closed state is NOT
 * persisted in any storage, so reloading shows the ad again.
 *
 * @Block(
 *   id = "adsensekit_sticky",
 *   admin_label = @Translation("AdSense sticky bottom ad"),
 *   category = @Translation("AdsenseKit")
 * )
 */
class AdsensekitStickyBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'ad_slot' => '',
      'ad_format' => 'auto',
      'full_width_responsive' => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();

    $form['ad_slot'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Ad slot ID'),
      '#description' => $this->t('Numeric <code>data-ad-slot</code> from AdSense.'),
      '#default_value' => $config['ad_slot'] ?? '',
      '#required' => TRUE,
      '#size' => 30,
    ];

    $form['ad_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Ad format'),
      '#options' => [
        'auto' => $this->t('Auto'),
        'horizontal' => $this->t('Horizontal'),
        'fluid' => $this->t('Fluid'),
      ],
      '#default_value' => $config['ad_format'] ?? 'auto',
    ];

    $form['full_width_responsive'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Full width responsive'),
      '#default_value' => $config['full_width_responsive'] ?? TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    $slot = trim((string) $form_state->getValue('ad_slot'));
    if (!preg_match('/^\d{6,}$/', $slot)) {
      $form_state->setErrorByName('ad_slot', $this->t('Ad slot must be a numeric ID (at least 6 digits).'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['ad_slot'] = trim((string) $form_state->getValue('ad_slot'));
    $this->configuration['ad_format'] = $form_state->getValue('ad_format');
    $this->configuration['full_width_responsive'] = (bool) $form_state->getValue('full_width_responsive');
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    $config = \Drupal::config('adsensekit.settings');
    if (!$config->get('enabled')) {
      return AccessResult::forbidden()->addCacheableDependency($config);
    }
    if (adsensekit_normalize_publisher_id($config->get('publisher_id')) === '') {
      return AccessResult::forbidden()->addCacheableDependency($config);
    }
    return AccessResult::allowed()->addCacheableDependency($config);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = \Drupal::config('adsensekit.settings');
    $publisher_id = adsensekit_normalize_publisher_id($config->get('publisher_id'));

    return [
      '#theme' => 'adsensekit_sticky',
      '#publisher_id' => $publisher_id,
      '#ad_slot' => $this->configuration['ad_slot'],
      '#ad_format' => $this->configuration['ad_format'] ?: 'auto',
      '#full_width_responsive' => !empty($this->configuration['full_width_responsive']),
      '#attached' => [
        'library' => [
          'adsensekit/styles',
          'adsensekit/sticky',
        ],
      ],
      '#cache' => [
        'tags' => ['config:adsensekit.settings'],
      ],
    ];
  }

}
