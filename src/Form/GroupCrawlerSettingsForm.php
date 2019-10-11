<?php

namespace Drupal\btj_scrapper\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\group\Entity\Group;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class GroupCrawlerSettingsForm.
 */
class GroupCrawlerSettingsForm extends ConfigFormBase {

  const FORM_ID = 'btj_scrapper.group_crawler.settings_form';

  const CONFIG_ID = 'btj_scrapper.group_crawler.settings';

  protected $entityFieldManager;

  /**
   * GroupCrawlerSettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityFieldManagerInterface $entityFieldManager) {
    $this->entityFieldManager = $entityFieldManager;

    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return self::FORM_ID;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      self::CONFIG_ID,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Group $group = NULL) {
    $config = $this
      ->config(self::CONFIG_ID)
      ->get($this->buildSettingsKey($group));

    $form['scrapper_settings'] = [
      '#tree' => TRUE,
    ];

    $form_state->setTemporaryValue('group_entity', $group);

    $entity = 'events';
    $label = $this->t('Events');

    $form['scrapper_settings'][$entity] = [
      '#type' => 'details',
      '#title' => $label,
    ];

    $form['scrapper_settings'][$entity]['crawler'] = [
      '#type' => 'details',
      '#title' => $this->t('Crawler settings'),
    ];

    $eventSettingsElements = &$form['scrapper_settings'][$entity]['crawler'];
    $eventConfig = $config[$entity];

    $eventSettingsElements['uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('@label collection path', ['@label' => $label]),
      '#description' => $this->t('URI path component to append to main url. This is where the list of the entities are located.'),
      '#default_value' => $eventConfig['crawler']['uri'] ?? '',
    ];

    $eventSettingsElements['link'] = [
      '#type' => 'textfield',
      '#title' => $this->t('@label link', ['@label' => $label]),
      '#description' => $this->t('CSS selector for the main link'),
      '#default_value' => $eventConfig['crawler']['link'] ?? '',
    ];

    $eventSettingsElements['pager_prev'] = [
      '#type' => 'textfield',
      '#title' => $this->t('@label pager previous link', ['@label' => $label]),
      '#description' => $this->t('CSS selector for pager previous link'),
      '#default_value' => $eventConfig['crawler']['pager_prev'] ?? '',
    ];

    $eventSettingsElements['pager_next'] = [
      '#type' => 'textfield',
      '#title' => $this->t('@label pager next link', ['@label' => $label]),
      '#description' => $this->t('CSS selector for pager next link'),
      '#default_value' => $eventConfig['crawler']['pager_next'] ?? '',
    ];

    $form['scrapper_settings'][$entity]['field_mapping'] = [
      '#type' => 'details',
      '#title' => $this->t('Field mapping'),
    ];

    $nodeFieldsDefinitions = $this->entityFieldManager->getFieldDefinitions(
      'node',
      'ding_event'
    );

    $nodeFields = [];
    foreach ($nodeFieldsDefinitions as $nodeFieldDefinition) {
      if (!$nodeFieldDefinition instanceof FieldConfig && 'title' != $nodeFieldDefinition->getName()) {
        continue;
      }

      $fieldName = $nodeFieldDefinition->getName();
      $fieldLabel = (string) $nodeFieldDefinition->getLabel();

      $nodeFields[$fieldName] = $fieldLabel;
    }

    asort($nodeFields);

    $event_field_mapping_elements = &$form['scrapper_settings'][$entity]['field_mapping'];
    foreach ($nodeFields as $name => $label) {
      $event_field_mapping_elements[$name] = [
        'source_field' => [
          '#type' => 'textfield',
          '#title' => $label,
          '#default_value' => $eventConfig['field_mapping'][$name] ?? '',
          '#description' => $this->t('CSS selector for <em>@label</em> field value', ['@label' => $label]),
        ],
      ];
    }

    return parent::buildForm(
      $form,
      $form_state
    );
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $key = $this->buildSettingsKey($form_state->getTemporaryValue('group_entity'));
    $this->config(self::CONFIG_ID)
      ->set($key, $values['scrapper_settings'])
      ->save();

    parent::submitForm(
      $form,
      $form_state
    );
  }

  /**
   * Builds unique config key to store settings per group.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group entity.
   *
   * @return string
   *   Config key.
   */
  private function buildSettingsKey(Group $group) {
    return 'group_' . $group->id() . '_crawler_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm(
      $form,
      $form_state
    );
  }

  /**
   * Route custom title callback.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group entity.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Route link title.
   */
  public function title(Group $group) {
    return $this->t('Edit crawler settings for <em>@label</em>', [
      '@label' => $group->label(),
    ]);
  }
}

