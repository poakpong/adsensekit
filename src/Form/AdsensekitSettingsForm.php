<?php

namespace Drupal\adsensekit\Form;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * AdsenseKit main settings form.
 */
class AdsensekitSettingsForm extends ConfigFormBase {

  /**
   * Bundle info service for listing content types.
   */
  protected EntityTypeBundleInfoInterface $bundleInfo;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->bundleInfo = $container->get('entity_type.bundle.info');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['adsensekit.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'adsensekit_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('adsensekit.settings');

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable AdSense'),
      '#description' => $this->t('Output the AdSense loader script and allow ad blocks/injector to render. Turn off to disable all AdsenseKit output site-wide.'),
      '#default_value' => $config->get('enabled'),
    ];

    $form['publisher_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Publisher ID'),
      '#description' => $this->t('Enter your AdSense Publisher ID in the form <code>pub-XXXXXXXXXXXXXXXX</code>. The module will automatically prepend <code>ca-</code> when outputting the script and ad units (Google requires <code>ca-pub-...</code>).'),
      '#default_value' => $config->get('publisher_id'),
      '#size' => 40,
      '#maxlength' => 64,
      '#placeholder' => 'pub-5980973323473366',
    ];

    // ---- Injector ----
    $form['injector'] = [
      '#type' => 'details',
      '#title' => $this->t('Content injector'),
      '#open' => TRUE,
      '#tree' => TRUE,
      '#description' => $this->t('Automatically insert an AdSense ad unit inside node body content after every N paragraphs.'),
    ];

    $form['injector']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable in-content injector'),
      '#default_value' => $config->get('injector.enabled'),
    ];

    $form['injector']['ad_slot'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Ad slot ID'),
      '#description' => $this->t('The numeric <code>data-ad-slot</code> value from AdSense, e.g. <code>5071204952</code>.'),
      '#default_value' => $config->get('injector.ad_slot'),
      '#size' => 30,
      '#states' => [
        'required' => [
          ':input[name="injector[enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['injector']['ad_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Ad format'),
      '#options' => [
        'auto' => $this->t('Auto'),
        'rectangle' => $this->t('Rectangle'),
        'horizontal' => $this->t('Horizontal'),
        'vertical' => $this->t('Vertical'),
        'fluid' => $this->t('Fluid'),
      ],
      '#default_value' => $config->get('injector.ad_format') ?: 'auto',
    ];

    $form['injector']['full_width_responsive'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Full width responsive'),
      '#default_value' => $config->get('injector.full_width_responsive'),
    ];

    $form['injector']['every_n_paragraphs'] = [
      '#type' => 'number',
      '#title' => $this->t('Insert after every N paragraphs'),
      '#min' => 1,
      '#default_value' => $config->get('injector.every_n_paragraphs') ?: 3,
    ];

    $form['injector']['max_inserts'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum ads per node'),
      '#min' => 0,
      '#description' => $this->t('Hard cap on injected ads in a single node. Set 0 for unlimited.'),
      '#default_value' => $config->get('injector.max_inserts') ?: 3,
    ];

    // Content type checkboxes.
    $node_bundles = [];
    if ($this->bundleInfo) {
      foreach ($this->bundleInfo->getBundleInfo('node') as $bundle => $info) {
        $node_bundles[$bundle] = $info['label'];
      }
    }
    $form['injector']['node_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Apply to content types'),
      '#description' => $this->t('Leave all unchecked to apply to every content type.'),
      '#options' => $node_bundles,
      '#default_value' => $config->get('injector.node_types') ?: [],
    ];

    $form['injector']['view_modes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Apply on view modes'),
      '#options' => [
        'full' => $this->t('Full content'),
        'teaser' => $this->t('Teaser'),
      ],
      '#default_value' => $config->get('injector.view_modes') ?: ['full'],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $pub = trim((string) $form_state->getValue('publisher_id'));
    if ($pub !== '' && !preg_match('/^(ca-)?pub-\d{6,}$/', $pub)) {
      $form_state->setErrorByName('publisher_id', $this->t('Publisher ID must look like <code>pub-XXXXXXXXXXXXXXXX</code> (digits only after the dash).'));
    }

    if ($form_state->getValue('enabled') && $pub === '') {
      $form_state->setErrorByName('publisher_id', $this->t('Publisher ID is required when AdSense is enabled.'));
    }

    $injector = $form_state->getValue('injector');
    if (!empty($injector['enabled']) && trim((string) ($injector['ad_slot'] ?? '')) === '') {
      $form_state->setErrorByName('injector][ad_slot', $this->t('Ad slot is required when injector is enabled.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $injector = $form_state->getValue('injector');

    // Filter checkboxes: only keep selected values.
    $node_types = array_values(array_filter($injector['node_types'] ?? []));
    $view_modes = array_values(array_filter($injector['view_modes'] ?? []));

    // Strip "ca-" if user pasted the full form, store the canonical "pub-XXXX".
    $publisher_id = trim((string) $form_state->getValue('publisher_id'));
    if (strpos($publisher_id, 'ca-pub-') === 0) {
      $publisher_id = substr($publisher_id, 3);
    }

    $this->config('adsensekit.settings')
      ->set('enabled', (bool) $form_state->getValue('enabled'))
      ->set('publisher_id', $publisher_id)
      ->set('injector.enabled', (bool) ($injector['enabled'] ?? FALSE))
      ->set('injector.ad_slot', trim((string) ($injector['ad_slot'] ?? '')))
      ->set('injector.ad_format', $injector['ad_format'] ?? 'auto')
      ->set('injector.full_width_responsive', (bool) ($injector['full_width_responsive'] ?? TRUE))
      ->set('injector.every_n_paragraphs', max(1, (int) ($injector['every_n_paragraphs'] ?? 3)))
      ->set('injector.max_inserts', max(0, (int) ($injector['max_inserts'] ?? 3)))
      ->set('injector.node_types', $node_types)
      ->set('injector.view_modes', $view_modes)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
