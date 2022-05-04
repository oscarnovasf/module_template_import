<?php

namespace Drupal\module_template_import\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

use Symfony\Component\HttpFoundation\RedirectResponse;

use Drupal\module_template_import\lib\general\StringFunctions;

/**
 * Formulario para la confirmación de la importación.
 */
class ConfirmForm extends ConfirmFormBase {

  /**
   * Se le asigna valor en la validación y si los campos cumplen criterios.
   *
   * @var array|bool
   *   Contiene los datos que vamos a importar.
   *   FALSE indica que ha ocurrido un error.
   */
  protected $arrayImportData = [];

  /**
   * Se le asigna valor en la validación y si los campos cumplen criterios.
   *
   * @var array|bool
   *   Contiene los encabezados de los datos que vamos a importar.
   *   FALSE indica que ha ocurrido un error.
   */
  protected $arrayHeadersData = [];

  /**
   * Extensión del archivo a importar, se obtiene en la validación.
   *
   * @var string
   *   Extensión del archivo que vamos a importar.
   */
  protected $fileExtension = "";

  /**
   * Tipo de contenido a importar, se obtiene en la validación.
   *
   * @var string
   *   Nombre del contenido a importar.
   */
  protected $contentType = "";

  /**
   * Indica si el archivo tiene cabeceras o no.
   *
   * @var bool
   *   TRUE si tiene cabeceras.
   */
  protected $hasHeaders = FALSE;

  /**
   * Indica si se debe actualizar en caso de existir el nodo.
   *
   * @var bool
   *   TRUE si tiene que actualizarse.
   */
  private $updateIfExists = FALSE;

  /**
   * {@inheritdoc}
   */
  public function getFormId() : string {
    return "module_template_import_confirm_import_form";
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form,
                            FormStateInterface $form_state,
                            string $content_type = NULL,
                            bool $has_headers = TRUE,
                            bool $update_if_exists = TRUE) {

    /* Primero genero el formulario por defecto */
    $form = parent::buildForm($form, $form_state);

    /* Obtengo el array con los datos pasados por post */
    $tempstore = \Drupal::service('tempstore.private')->get('module_template_import');
    $this->arrayImportData = $tempstore->get('array_import_data');
    $this->arrayHeadersData = $tempstore->get('array_headers_data');

    /* Obtengo el contenido con el que tengo que trabajar */
    $this->contentType = $content_type;
    $this->hasHeaders = $has_headers;
    $this->updateIfExists = $update_if_exists;

    $form['header'] = [
      '#markup' => $this->t('<h1>Content to import: @content_type</h1>', [
        '@content_type' => $content_type,
      ]),
      '#weight' => 1,
    ];

    /* Recorto los campos a 200 caracteres */
    $total = count($this->arrayImportData);
    $mostrando = $total >= 5 ? 5 : $total;
    $rows = array_slice($this->arrayImportData, 0, 5);
    foreach ($rows as &$row) {
      array_walk($row, [$this, 'truncate']);
    }

    /* Crear encabezados si estos no existen */
    switch ($this->contentType) {

      case 'articles':
        if (!$this->hasHeaders) {
          $this->arrayHeadersData = [NodeSample::getHeaders()];
          $tempstore->set('array_headers_data', $this->arrayHeadersData);
        }
        break;

    }

    $form['content'] = [
      '#type' => 'table',
      '#caption' => $this->t('Sample data (%mostrando of %total)', [
        '%mostrando' => $mostrando,
        '%total' => $total,
      ]),
      '#header' => $this->arrayHeadersData[0],
      '#rows' => $rows,
      '#attributes' => [
        'class' => [
          'my-custom-migrate-sample-data',
        ],
      ],
      '#empty' => $this->t('No data found'),
      '#weight' => 100,
    ];

    $form['actions']['#weight'] = 2;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /* Variables auxiliares */
    $operations = [];

    /* Recorro el array y voy importando línea por línea */
    foreach ($this->arrayImportData as $row) {

      /* Compruebo que tipo de contenido estoy importando */
      switch ($this->contentType) {

        case 'articles':
          $operations[] = [
            'Drupal\module_template_import\Controller\NodeSampleController::addItem',
            [$row, $this->updateIfExists],
          ];
          break;

        default:
          \Drupal::messenger()->addWarning($this->t('We have not received all the parameters.'));

      }
    }

    if (!empty($operations)) {
      $batch = [
        'title' => $this->t('Data Import'),
        'operations' => $operations,
        'init_message' => $this->t('Import is starting...'),
        'finished' => 'Drupal\module_template_import\Controller\GeneralController::addGeneralCallback',
      ];

      batch_set($batch);
    }
    else {
      /* Mostrar mensaje de ninguna operación a realizar. */
      \Drupal::messenger()->addWarning($this->t('No data found'));
      return $this->returnToCallerPage()->send();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute("custom_module.module_template_import.import_data", []);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    /* Construyo el mensaje (pregunta) de cabecera */
    $mensaje = $this->t('Data import');

    return $mensaje;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    /* Vacío la descripción del formulario pues ya se muestra en buildForm */
    return '';
  }

  /* ***************************************************************************
   * MÉTODOS PRIVADOS.
   * ************************************************************************ */

  /**
   * Función returnToCallerPage().
   *
   * Devuelve una redirección a la página que llamó al procedimiento.
   *
   * @return Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirección.
   */
  private function returnToCallerPage() {
    return new RedirectResponse("/admin/custom_modules/module_template_import/import-data");
  }

  /**
   * Recorta todos los datos de cada elemento para su visualización.
   */
  private function truncate(&$elemento, $clave) {
    /* Recorto el valor del title */
    if (!is_array($elemento) and !empty($elemento)) {
      $elemento = StringFunctions::truncate($elemento, 200);
    }
  }

}
