<?php

namespace Drupal\btj_scrapper\Form;

use BTJ\Scrapper\Container\EventContainer;
use BTJ\Scrapper\Container\LibraryContainer;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupInterface;
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
      ->get(self::buildSettingsKey($group));

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
    $eventConfig = $config[$entity] ?? [];

    $eventSettingsElements['uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('@label collection path', ['@label' => $label]),
      '#description' => $this->t('URI path component to append to main url. This is where the list of the entities are located.'),
      '#default_value' => $eventConfig['crawler']['uri'] ?? '',
    ];

    $eventSettingsElements['link_selector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('@label link', ['@label' => $label]),
      '#description' => $this->t('CSS selector for the main link'),
      '#default_value' => $eventConfig['crawler']['link_selector'] ?? '',
    ];

    $eventSettingsElements['pager_next_selector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('@label pager next link', ['@label' => $label]),
      '#description' => $this->t('CSS selector for pager next link'),
      '#default_value' => $eventConfig['crawler']['pager_next_selector'] ?? '',
    ];

    $form['scrapper_settings'][$entity]['field_mapping'] = [
      '#type' => 'details',
      '#title' => $this->t('Field mapping'),
    ];

    $event_field_mapping_elements = &$form['scrapper_settings'][$entity]['field_mapping'];
    $event_field_mapping_elements['mapping_table'] = $this->buildFieldMappingTable(
      EventContainer::class,
      $eventConfig
    );

    $entity = 'library';
    $label = $this->t('Library');

    $form['scrapper_settings']['scraping_limit'] = [
      '#type' => 'select',
      '#title' => $this->t('Items to enqueue'),
      '#description' => $this->t('Define how many items of each type to put in queue for processing.'),
      '#options' => [
        1 => 1,
        5 => 5,
        10 => 10,
        25 => 25,
        50 => 50,
        100 => 100,
      ],
      '#default_value' => $config['scraping_limit'] ?? 50,
      '#weight' => -1,
    ];

    $form['scrapper_settings'][$entity] = [
      '#type' => 'details',
      '#title' => $label,
    ];

    $form['scrapper_settings'][$entity]['crawler'] = [
      '#type' => 'details',
      '#title' => $this->t('Crawler settings'),
    ];

    $librarySettingsElements = &$form['scrapper_settings'][$entity]['crawler'];
    $libraryConfig = $config[$entity] ?? [];

    $librarySettingsElements['links'] = [
      '#type' => 'textarea',
      '#title' => $this->t('@label links', ['@label' => $label]),
      '#description' => $this->t('List of library links to scrap on. One entry per line.'),
      '#default_value' => $libraryConfig['crawler']['links'] ?? '',
    ];

    $form['scrapper_settings'][$entity]['field_mapping'] = [
      '#type' => 'details',
      '#title' => $this->t('Field mapping'),
    ];

    $library_field_mapping_elements = &$form['scrapper_settings'][$entity]['field_mapping'];
    $library_field_mapping_elements['mapping_table'] = $this->buildFieldMappingTable(
      LibraryContainer::class,
      $libraryConfig
    );

    return parent::buildForm(
      $form,
      $form_state
    );
  }

  private function buildFieldMappingTable(string $className, array $config) {
    $containerReflection = new \ReflectionClass($className);
    /** @var \ReflectionProperty[] $containerFields */
    $containerFields = $containerReflection->getProperties();
    $containerFields = array_merge(
      $containerFields,
      $containerReflection->getParentClass()->getProperties()
    );

    usort($containerFields, function ($left, $right) {
      return strcmp($left->getName(), $right->getName());
    });

    $table = [
      '#type' => 'table',
      '#header' => [
        $this->t('CSS selector'),
        $this->t('Regex filter'),
      ],
    ];

    foreach ($containerFields as $containerField) {
      $name = $containerField->getName();
      $label = ucfirst($name);

      $table[$name] = [
        'selector' => [
          '#type' => 'textfield',
          '#title' => $label,
          '#default_value' => $config['field_mapping']['mapping_table'][$name]['selector'] ?? '',
          '#description' => $this->t('CSS selector for <em>@label</em> field value.', ['@label' => $label]),
        ],
        'regex' => [
          '#type' => 'textfield',
          '#description' => $this->t('Apply regular expression filter for <em>@label</em> field.', ['@label' => $label]),
          '#default_value' => $config['field_mapping']['mapping_table'][$name]['regex'] ?? '',
        ],
      ];
    }

    return $table;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues()['scrapper_settings'];

    foreach ($values as $k => $v) {
      foreach ($v['field_mapping']['mapping_table'] ?? [] as $field => $mapping) {
        // Hence the '@' - we only outline the respective erroneous field.
        if (!empty($mapping['regex']) && FALSE === @preg_match("{$mapping['regex']}", NULL)) {
          $form_state->setError(
            $form['scrapper_settings'][$k]['field_mapping']['mapping_table'][$field]['regex'],
            $this->t('Invalid regular expression for field @label.', ['@label' => ucfirst($field)])
          );
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $key = self::buildSettingsKey($form_state->getTemporaryValue('group_entity'));
    $this->config(self::CONFIG_ID)
      ->set($key, $values['scrapper_settings'])
      ->save();

    parent::submitForm(
      $form,
      $form_state
    );
  }

  /**
   * Builds unique config key to store settings for respective group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group entity.
   *
   * @return string
   *   Config key.
   */
  public static function buildSettingsKey(GroupInterface $group) {
    return 'group_' . $group->id() . '_crawler_settings';
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

