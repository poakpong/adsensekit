<?php

namespace Drupal\adsensekit\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a Google AdSense ad unit block.
 *
 * Each block instance is configured with its own data-ad-slot, so editors
 * can place several different ad units in different regions via the normal
 * Block layout UI (admin/structure/block).
 *
 * @Block(
 *   id = "adsensekit_ad",
 *   admin_label = @Translation("AdSense ad unit"),
 *   category = @Translation("AdsenseKit")
 * )
 */
class AdsensekitAdBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'ad_slot' => '',
      'ad_format' => 'auto',
      'full_width_responsive' => TRUE,
      'custom_style' => 'display:block',
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
      '#description' => $this->t('The numeric <code>data-ad-slot</code> value from your AdSense ad unit, e.g. <code>5071204952</code>.'),
      '#default_value' => $config['ad_slot'] ?? '',
      '#required' => TRUE,
      '#size' => 30,
    ];

    $form['ad_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Ad format'),
      '#options' => [
        'auto' => $this->t('Auto'),
        'rectangle' => $this->t('Rectangle'),
        'horizontal' => $this->t('Horizontal'),
        'vertical' => $this->t('Vertical'),
        'fluid' => $this->t('Fluid'),
      ],
      '#default_value' => $config['ad_format'] ?? 'auto',
    ];

    $form['full_width_responsive'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Full width responsive'),
      '#default_value' => $config['full_width_responsive'] ?? TRUE,
    ];

    $form['custom_style'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Inline style'),
      '#description' => $this->t('Inline CSS for the <code>&lt;ins&gt;</code> tag. Default: <code>display:block</code>.'),
      '#default_value' => $config['custom_style'] ?? 'display:block',
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
    $this->configuration['custom_style'] = trim((string) $form_state->getValue('custom_style')) ?: 'display:block';
  }

  /**
   * {@inheritdoc}
   *
   * Hide the block entirely if AdSense is disabled or publisher ID is missing.
   */
  protected function blockAccess(AccountInterface $account) {
    $config = \Drupal::config('adsensekit.settings');
    if (!$config->get('enabled')) {
      return AccessResult::forbidden()
        ->addCacheableDependency($config);
    }
    $pub = adsensekit_normalize_publisher_id($config->get('publisher_id'));
    if ($pub === '') {
      return AccessResult::forbidden()
        ->addCacheableDependency($config);
    }
    return AccessResult::allowed()
      ->addCacheableDependency($config);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = \Drupal::config('adsensekit.settings');
    $publisher_id = adsensekit_normalize_publisher_id($config->get('publisher_id'));

    $build = [
      '#theme' => 'adsensekit_ad',
      '#publisher_id' => $publisher_id,
      '#ad_slot' => $this->configuration['ad_slot'],
      '#ad_format' => $this->configuration['ad_format'] ?: 'auto',
      '#full_width_responsive' => !empty($this->configuration['full_width_responsive']),
      '#custom_style' => $this->configuration['custom_style'] ?: 'display:block',
      '#attached' => [
        'library' => ['adsensekit/styles'],
      ],
      '#cache' => [
        'tags' => ['config:adsensekit.settings'],
      ],
    ];

    return $build;
  }

}
