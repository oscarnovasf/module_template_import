<?php

namespace Drupal\module_template_import\Form\config;

/**
 * @file
 * SettingsForm.php
 */

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;

use Drupal\module_template_import\lib\general\MarkdownParser;

/**
 * Formulario de configuración del módulo.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Implements getFormId().
   */
  public function getFormId() {
    return 'custom_module.module_template_import.settings';
  }

  /**
   * Implements getEditableConfigNames().
   */
  protected function getEditableConfigNames() {
    return ['custom_module.module_template_import.settings'];
  }

  /**
   * Implements buildForm().
   *
   * @SuppressWarnings(PHPMD)
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    /* Obtengo la configuración actual */
    /* $config = \Drupal::configFactory()->getEditable('custom_module.module_template_import.settings'); */
    $config = $this->config('custom_module.module_template_import.settings');

    /* SETTINGS FORM */
    $form['settings'] = [
      '#type' => 'vertical_tabs',
    ];

    $form['general_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('General'),
      '#open' => TRUE,
      '#group' => 'settings',
      '#description' => $this->t('<p><h2>General Settings</h2></p>'),
    ];

    $form['general_settings']['csv'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('CSV'),
    ];

    $form['general_settings']['csv']['delimiter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Delimiter'),
      '#default_value' => $config->get('delimiter') ?? ';',
      '#required' => TRUE,
      '#access' => TRUE,
    ];

    $form['general_settings']['csv']['enclosure'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Enclosure'),
      '#default_value' => $config->get('enclosure') ?? '"',
      '#required' => TRUE,
      '#access' => TRUE,
    ];

    $form['general_settings']['csv']['escape'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Escape'),
      '#default_value' => $config->get('escape') ?? '\\',
      '#required' => TRUE,
      '#access' => TRUE,
    ];

    /* *************************************************************************
     * INFORMACIÓN y AYUDA: CONTENIDO DE CHANGELOG.md y README.md
     * ************************************************************************/

    /* Datos auxiliares */
    $module_path = \Drupal::service('extension.path.resolver')
      ->getPath('module', "module_template_import");
    $parser = new MarkdownParser();

    /* Templates */
    $info_template = file_get_contents($module_path . "/templates/custom/info.html.twig");
    $help_template = file_get_contents($module_path . "/templates/custom/help.html.twig");

    /* Compruebo si existe y leo el contenido del archivo CHANGELOG.md */
    $changelog_ruta = $module_path . "/CHANGELOG.md";
    $contenido = '';
    if (file_exists($changelog_ruta)) {
      $contenido = file_get_contents($changelog_ruta);
      $contenido = Markup::create($parser->text($contenido));
    }

    if ($contenido) {
      $form['info'] = [
        '#type' => 'details',
        '#title' => $this->t('Info'),
        '#group' => 'settings',
        '#description' => '',
      ];

      $form['info']['info'] = [
        '#type' => 'inline_template',
        '#template' => $info_template,
        '#context' => [
          'changelog' => $contenido,
        ],
      ];
    }

    /* Compruebo si existe y leo el contenido del archivo README.md */
    $readme_ruta = $module_path . "/README.md";
    $contenido = '';
    if (file_exists($readme_ruta)) {
      $contenido = file_get_contents($readme_ruta);
      $contenido = Markup::create($parser->text($contenido));
    }

    if ($contenido) {
      $form['help'] = [
        '#type' => 'details',
        '#title' => $this->t('Help'),
        '#group' => 'settings',
        '#description' => '',
      ];

      $form['help']['help'] = [
        '#type' => 'inline_template',
        '#template' => $help_template,
        '#context' => [
          'readme' => $contenido,
        ],
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $config = $this->config('custom_module.module_template_import.settings');

    /* INFO: Indicar todos los campos a guardar */
    $list = [
      'delimiter',
      'enclosure',
      'escape',
    ];

    foreach ($list as $item) {
      $config->set($item, $form_state->getValue($item));
    }
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
