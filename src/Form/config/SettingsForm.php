<?php

namespace Drupal\module_template_import\Form\config;

/**
 * @file
 * SettingsForm.php
 */

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Config\Config;
use Drupal\Core\Extension\ExtensionPathResolver;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\module_template_import\lib\general\MarkdownParser;

/**
 * Formulario de configuración del módulo.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * @var \Drupal\Core\Extension\ExtensionPathResolver
   */
  protected $pathResolver;

  /**
   * Constructor para añadir dependencias.
   *
   * @param \Drupal\Core\Extension\ExtensionPathResolver $path_resolver
   *   Servicio PathResolver.
   */
  public function __construct(ExtensionPathResolver $path_resolver) {
    $this->pathResolver = $path_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('extension.path.resolver'),
    );
  }

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
     * CONTENIDO DE CHANGELOG.md, LICENSE.md y README.md
     * ************************************************************************/

    /* Datos auxiliares */
    $module_path = $this->pathResolver
      ->getPath('module', "module_template_import");

    /* Compruebo si existe y leo el contenido del archivo CHANGELOG.md */
    $contenido = $this->getChangeLogBuild($config, $module_path);
    if ($contenido) {
      $form['info'] = $contenido;
    }

    /* Compruebo si existe y leo el contenido del archivo LICENSE.md */
    $contenido = $this->getLicenseBuild($config, $module_path);
    if ($contenido) {
      $form['license'] = $contenido;
    }

    /* Compruebo si existe y leo el contenido del archivo README.md */
    $contenido = $this->getReadmeBuild($config, $module_path);
    if ($contenido) {
      $form['help'] = $contenido;
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

  /**
   * Obtiene el contenido del archivo CHANGELOG.md.
   *
   * @param \Drupal\Core\Config\Config $config
   *   Configuración del módulo.
   * @param string $module_path
   *   Path del módulo.
   *
   * @return array
   *   Array con el contenido a renderizar, si procede.
   */
  private function getChangeLogBuild(Config $config, string $module_path): array {
    $template = file_get_contents($module_path . "/templates/custom/info.html.twig");

    $ruta = $module_path . "/CHANGELOG.md";
    $contenido = $this->getMdContent($ruta);

    if ($contenido) {
      $form['info'] = [
        '#type' => 'details',
        '#title' => $this->t('Info'),
        '#group' => 'settings',
        '#description' => '',

        'info' => [
          '#type' => 'inline_template',
          '#template' => $template,
          '#context' => [
            'changelog' => Markup::create($contenido),
          ],
        ],
      ];

      return $form['info'];
    }

    return [];
  }

  /**
   * Obtiene el contenido del archivo LICENSE.md.
   *
   * @param \Drupal\Core\Config\Config $config
   *   Configuración del módulo.
   * @param string $module_path
   *   Path del módulo.
   *
   * @return array
   *   Array con el contenido a renderizar, si procede.
   */
  private function getLicenseBuild(Config $config, string $module_path): array {
    $template = file_get_contents($module_path . "/templates/custom/license.html.twig");

    $ruta = $module_path . "/LICENSE.md";
    $contenido = $this->getMdContent($ruta);

    if ($contenido) {
      $form['license'] = [
        '#type' => 'details',
        '#title' => $this->t('License'),
        '#group' => 'settings',
        '#description' => '',

        'license' => [
          '#type' => 'inline_template',
          '#template' => $template,
          '#context' => [
            'license' => Markup::create($contenido),
          ],
        ],
      ];

      return $form['license'];
    }

    return [];
  }

  /**
   * Obtiene el contenido del archivo LICENSE.md.
   *
   * @param \Drupal\Core\Config\Config $config
   *   Configuración del módulo.
   * @param string $module_path
   *   Path del módulo.
   *
   * @return array
   *   Array con el contenido a renderizar, si procede.
   */
  private function getReadmeBuild(Config $config, string $module_path): array {
    $template = file_get_contents($module_path . "/templates/custom/help.html.twig");

    $ruta = $module_path . "/README.md";
    $contenido = $this->getMdContent($ruta);

    if ($contenido) {
      $form['help'] = [
        '#type' => 'details',
        '#title' => $this->t('Help'),
        '#group' => 'settings',
        '#description' => '',

        'help' => [
          '#type' => 'inline_template',
          '#template' => $template,
          '#context' => [
            'readme' => Markup::create($contenido),
          ],
        ],
      ];

      return $form['help'];
    }

    return [];
  }

  /**
   * Obtiene el contenido de un archivo .md.
   *
   * @param string $path
   *   Ruta completa del archivo.
   *
   * @return string
   *   Contenido del archivo.
   */
  private function getMdContent(string $path): string {
    $parser = new MarkdownParser();

    $contenido = '';
    if (file_exists($path)) {
      $contenido = file_get_contents($path);
      $contenido = $parser->text($contenido);
    }

    return $contenido;
  }

}
