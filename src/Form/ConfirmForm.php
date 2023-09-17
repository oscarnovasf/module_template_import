<?php

namespace Drupal\module_template_import\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\TempStore\PrivateTempStoreFactory;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\module_template_import\lib\general\StringFunctions;

/**
 * Formulario para la confirmación de la importación.
 */
class ConfirmForm extends ConfirmFormBase {

  /**
   * Namespace de las librerías.
   *
   * @var string
   *   Namespace de las librerías (sin el nombre de la librería).
   */
  private $libNameSpace = "\\Drupal\\module_template_import\\lib\\handlers\\";

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
   * Nombre del archivo a importar, se obtiene en la validación.
   *
   * @var string
   *   Nombre del archivo que vamos a importar.
   */
  private $fileName = "";

  /**
   * Tipo de contenido a importar, se obtiene en la validación.
   *
   * @var string
   *   Nombre del contenido a importar.
   */
  protected $contentType = "";

  /**
   * Clase del contenido a importar, se obtiene en la validación.
   *
   * @var string
   *   Clase del contenido a importar.
   */
  private $contentClass = "";

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
   * Temp Store.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $privateTempStore;

  /**
   * Constructor para añadir dependencias.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store
   *   Temp Store.
   */
  public function __construct(PrivateTempStoreFactory $temp_store) {
    $this->privateTempStore = $temp_store;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
    );
  }

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
                            string $filename = NULL,
                            bool $has_headers = TRUE,
                            bool $update_if_exists = TRUE,
                            string $content_class = NULL) {

    /* Primero genero el formulario por defecto */
    $form = parent::buildForm($form, $form_state);

    /* Obtengo el array con los datos pasados por post */
    $temp_store = $this->privateTempStore->get('module_template_import');
    $this->arrayImportData = $temp_store->get('array_import_from_file');
    $this->arrayHeadersData = $temp_store->get('array_headers_data');

    /* Obtengo el contenido con el que tengo que trabajar */
    $this->contentType = $content_type;
    $this->hasHeaders = $has_headers;
    $this->updateIfExists = $update_if_exists;
    $this->contentClass = $content_class;
    $this->fileName = $filename;

    $form['header'] = [
      '#type' => 'container',
      '#weight' => 1,
    ];

    $form['header']['type'] = [
      '#markup' => $this->t('<h2>Selected type: @content_type</h2>', [
        '@content_type' => $content_type,
      ]),
    ];

    $form['header']['controller'] = [
      '#markup' => $this->t('<h3>Controller: @content_class</h3>', [
        '@content_class' => $content_class,
      ]),
    ];

    $form['header']['filename'] = [
      '#markup' => $this->t('<h3>File: @filename</h3>', [
        '@filename' => $filename,
      ]),
    ];

    /* Recorto los campos a 200 caracteres */
    $total = count($this->arrayImportData);
    $mostrando = $total >= 5 ? 5 : $total;
    $rows = array_slice($this->arrayImportData, 0, 5);
    foreach ($rows as &$row) {
      array_walk($row, [$this, 'truncate']);
    }

    /* Crear encabezados si estos no existen */
    if (!$this->hasHeaders) {
      $clase = $this->contentClass;
      $this->arrayHeadersData = [$clase::getHeaders()];
      $temp_store->set('array_headers_data', $this->arrayHeadersData);
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

      $library = $this->libNameSpace . $this->contentClass;

      $operations[] = [
        'Drupal\module_template_import\Controller\GeneralController::addItem',
        [$row, $this->updateIfExists, $library, $this->fileName],
      ];
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
      $this->messenger()->addWarning($this->t('No data found'));
      return $this->returnToCallerPage()->send();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute("module_template_import.import_from_file", []);
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
   *
   * @SuppressWarnings("unused")
   */
  private function truncate(&$elemento, $clave) {
    /* Recorto el valor del title */
    if (!is_array($elemento) and !empty($elemento)) {
      $elemento = StringFunctions::truncate($elemento, 200);
    }
  }

}
