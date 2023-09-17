<?php

namespace Drupal\module_template_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\file\Entity\File;
use Drupal\Component\Serialization\Yaml;

use Drupal\Core\Url;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\module_template_import\lib\general\FileFunctions;
use Drupal\module_template_import\lib\general\ResponseFunctions;

/**
 * Formulario para importación de datos.
 */
class ImportForm extends FormBase {

  /**
   * Se le asigna valor en la validación y si los campos cumplen criterios.
   *
   * @var array|bool
   *   Contiene los datos que vamos a importar.
   *   FALSE indica que ha ocurrido un error.
   */
  private $arrayImportData = [];

  /**
   * Se le asigna valor en la validación y si los campos cumplen criterios.
   *
   * @var array|bool
   *   Contiene los encabezados de los datos que vamos a importar.
   *   FALSE indica que ha ocurrido un error.
   */
  private $arrayHeadersData = [];

  /**
   * Extensión del archivo a importar, se obtiene en la validación.
   *
   * @var string
   *   Extensión del archivo que vamos a importar.
   */
  private $fileExtension = "";

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
  private $contentType = "";

  /**
   * Namespace de las librerías.
   *
   * @var string
   *   Namespace de las librerías (sin el nombre de la librería).
   */
  private $libNameSpace = "\\Drupal\\module_template_import\\lib\\handlers\\";

  /**
   * Define las diferentes clases según el tipo de contenido a importar.
   *
   * @var array
   *   Listado de clases disponibles.
   *
   * @todo RELLENAR TODAS LAS CLASES QUE SE USARÁN.
   */
  private $contentTypeClasses = [
    'articles' => '\\Drupal\\module_template_import\\lib\\handlers\\NodeSampleHandler',
  ];

  /**
   * Indica si el archivo tiene cabeceras o no.
   *
   * @var bool
   *   TRUE si tiene cabeceras.
   */
  private $hasHeaders = FALSE;

  /**
   * Indica si se debe actualizar en caso de existir el nodo.
   *
   * @var bool
   *   TRUE si tiene que actualizarse.
   */
  private $updateIfExists = FALSE;

  /**
   * @var \Drupal\Core\Extension\ExtensionPathResolver
   */
  protected $pathResolver;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The module configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Current user.
   *
   * @var |Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Temp Store.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $privateTempStore;

  /**
   * Constructor para añadir dependencias.
   *
   * @param \Drupal\Core\Extension\ExtensionPathResolver $path_resolver
   *   Servicio PathResolver.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   Current user.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store
   *   Temp Store.
   */
  public function __construct(ExtensionPathResolver $path_resolver,
                              ConfigFactoryInterface $config_factory,
                              AccountProxyInterface $current_user,
                              PrivateTempStoreFactory $temp_store) {
    $this->pathResolver = $path_resolver;
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
    $this->privateTempStore = $temp_store;

    $this->config = $this->configFactory->get('custom_module.module_template_import.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('extension.path.resolver'),
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('tempstore.private'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'module_template_import_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    /*
     * Tipos de contenido que se pueden importar.
     * Formato de la clave:
     *  - extension-contenido
     */
    $options = [
      'csv-users' => $this->t('Users'),
      'csv-articles' => $this->t('Articles'),
    ];

    /* Usamos la key del array $options para establecer un valor por defecto */
    $default_option = '';

    $form['#attributes'] = [
      'class' => 'my-custom-migrate-import-form',
    ];

    $form['description'] = [
      '#markup' => '<h2>' . $this->t('Use this form to upload a YML or CSV file.') . '</h2>',
    ];

    $form['content_type_select'] = [
      '#type' => 'select',
      '#title' => $this->t('Select the type of content you want to import'),
      '#required' => TRUE,
      '#options' => $options,
      '#empty_option' => '-- Vacío --',
      '#empty_value' => '_none',
      '#default_value' => $default_option,
      '#attributes' => [
        'attr-name' => 'content_type_select',
      ],
    ];

    $form['has_headers'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('File has headers'),
      '#default_value' => TRUE,
    ];

    $form['update_if_exists'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Update if exists'),
      '#default_value' => TRUE,
    ];

    $form['import_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('File'),
      '#upload_location' => 'public://temporal/',
      '#default_value' => '',
      '#multiple' => FALSE,
      "#upload_validators" => [
        "file_validate_extensions" => [
          "yml csv",
        ],
      ],
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload and Import'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    parent::validateForm($form, $form_state);

    $module_path = $this->pathResolver
      ->getPath('module', "module_template_import");

    /* Almaceno el valor de si tiene encabezados */
    $this->hasHeaders = $form_state->getValue('has_headers');

    /* Almaceno el valor de si tiene que actualizarse en caso de existir. */
    $this->updateIfExists = $form_state->getValue('update_if_exists');

    /* Obtener el array del archivo almacenado temporalmente */
    $import_file = $form_state->getValue('import_file');
    if (!empty($import_file) and is_array($import_file)) {

      /* Leo el archivo a partir de su fid */
      $file = File::load($import_file[0]);
      $file_url = file_create_url($file->getFileUri());
      $file_path = DRUPAL_ROOT . str_replace($this->getRequest()->getSchemeAndHttpHost(), "", $file_url);

      /* Leo lo que quiero importar */
      $aux = $form_state->getValue('content_type_select');

      /* Obtengo el nombre del fichero */
      $filename = $file->getFilename();
      $this->fileName = substr($filename, 0, strlen($filename) - 4);

      /* Obtengo el tipo de archivo que necesito y lo que quiero importar */
      $this->fileExtension = substr($aux, 0, 3);
      $this->contentType = substr($aux, 4, strlen($aux) - 4);

      /* Compruebo que tenga la extensión que necesita este tipo de contenido */
      if (file_validate_extensions($file, $this->fileExtension)) {
        /* Extensión no válida */
        $form_state->setErrorByName('import_file', $this->t('Invalid extension'));
      }

      /* Compruebo que tenga la codificación correcta */
      elseif (mb_check_encoding(file_get_contents($file_path), 'UTF-8')) {

        /* Generar nuevo archivo preparado para importar */
        $resultado = new ResponseFunctions();
        $resultado = $this->generateArray($file->getFilename());

        if (FALSE == $resultado->getStatus()) {
          /* El archivo no cumple los parámetros => Creo un archivo con los errores que se han producido en la validación */
          $file_path = $module_path->getPath('module', "module_template_import") . "/logs/validate_" . date('Y-m-d') . '_' . $this->currentUser->id() . '.log';
          FileFunctions::createFileLog($file_path, $resultado->getResponse('errores'));
          $urlError = "<a href='/$file_path' target='_blank'>Ver log</a>";

          $form['import_file']['#value'] = [];
          $form_state->setErrorByName('import_file', Markup::create($this->t('Data validation errors have occurred.') . " $urlError"));
          $form_state->setValue('import_file', []);
          $form_state->setRebuild();
        }
        else {
          $this->arrayImportData = $resultado->getResponse('datos');
          $this->arrayHeadersData = $resultado->getResponse('headers');
        }
      }
      else {
        /* Archivo codificado en un formato no válido */
        $form['import_file']['#value'] = [];
        $form_state->setErrorByName('import_file', $this->t('Invalid file encoding.'));
        $form_state->setValue('import_file', []);
        $form_state->setRebuild();
      }
    }
    else {
      /* Error al leer el archivo */
      $form['import_file']['#value'] = [];
      $form_state->setErrorByName('import_file', $this->t('Error loading file.'));
      $form_state->setValue('import_file', []);
      $form_state->setRebuild();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    /* Almaceno el array con los datos en una variable de sesión */
    $temp_store = $this->privateTempStore->get('module_template_import');
    $temp_store->set('array_import_from_file', $this->arrayImportData);
    $temp_store->set('array_headers_data', $this->arrayHeadersData);

    /* Limpio el nombre de la clase porque me da problemas en entorno de pruebas Windows */
    $content_class = str_replace($this->libNameSpace, "", $this->contentTypeClasses[$this->contentType]);

    /* Redirecciono al formulario de confirmación */
    $form_state->setRedirectUrl(Url::fromRoute("custom_module.module_template_import.confirm_form", [
      'content_type'     => $this->contentType,
      'filename'         => $this->fileName,
      'has_headers'      => $this->hasHeaders,
      'update_if_exists' => $this->updateIfExists,
      'content_class'    => $content_class,
    ]));
  }

  /* ***************************************************************************
   * FUNCIONES PRIVADAS.
   * ************************************************************************ */

  /**
   * Obtención de datos desde el fichero.
   *
   * Genera un array con los datos necesarios para la importación.
   * IMPORTANTE: NO REALIZAR NINGUNA DESCARGA DE ARCHIVOS EN ESTA FUNCIÓN. Si se
   * hace con archivos largos puede causar error de TimeOut. Mejor realizar las
   * descargas dentro del proceso batch.
   *
   * @param string $filename
   *   Archivo a convertir en Array.
   *
   * @return Drupal\module_template_import\lib\general\ResponseFunctions
   *   Si el valor de status es TRUE devolverá el archivo preparado para
   *   su importación.
   *   Si el valor de status es FALSE devolverá un array con los errores
   *   encontrados.
   */
  private function generateArray(string $filename) {
    /* Variable de retorno */
    $returnValue = new ResponseFunctions();

    /* Crear la ruta completa al archivo */
    $filePath = "public://temporal/$filename";

    /* Generación de datos según el tipo de archivo */
    if ($this->fileExtension == 'csv') {
      $returnValue = $this->generateFromCsv($filePath);
    }
    elseif ($this->fileExtension == 'yml') {
      $returnValue = $this->generateFromYml($filePath);
    }

    return $returnValue;
  }

  /**
   * Obtención de datos desde el fichero CSV.
   *
   * Genera un array con los datos necesarios para la importación.
   * IMPORTANTE: NO REALIZAR NINGUNA DESCARGA DE ARCHIVOS EN ESTA FUNCIÓN. Si se
   * hace con archivos largos puede causar error de TimeOut. Mejor realizar las
   * descargas dentro del proceso batch.
   *
   * @param string $file_path
   *   Ruta al archivo a convertir en Array.
   *
   * @return Drupal\module_template_import\lib\general\ResponseFunctions
   *   Si el valor de status es TRUE devolverá el archivo preparado para
   *   su importación.
   *   Si el valor de status es FALSE devolverá un array con los errores
   *   encontrados.
   */
  private function generateFromCsv(string $file_path) {
    /* Variable de retorno */
    $returnValue = new ResponseFunctions();

    /* Corrección de los archivos con BOM */
    $bom = pack("CCC", 0xEF, 0xBB, 0xBF);

    /* Obtengo datos de configuración del módulo */
    $delimiter = $this->config->get('delimiter') ?? ';';
    $enclosure = $this->config->get('enclosure') ?? '"';
    $escape    = $this->config->get('escape') ?? '\\';

    /* Variables para los datos */
    $newFileData = [];
    $headers = [];
    $errores = [];
    $numFila = 0;

    /* Obtengo la clase según el tipo de contenido */
    $clase = $this->contentTypeClasses[$this->contentType];

    /* Leo el contenido del archivo */
    $file_csv = FileFunctions::readFile($file_path, ["csv"]);

    if ($file_csv) {
      /* Leo el fichero */
      $i = 0;
      while ($row = fgetcsv($file_csv, 0, $delimiter, $enclosure, $escape)) {

        /* Corrijo el BOM del primer elemento */
        $row[0] = str_replace($bom, '', $row[0]);

        /* Si tiene encabezados me salto la primera línea */
        if ($this->hasHeaders && $i == 0) {
          $headers[] = $row;
          $i++;
        }
        else {
          /* Validación de datos */
          $importador = new $clase($this->fileName);
          $error = $importador->validateData($row);
          if (FALSE == $error->getStatus()) {
            $errores[] = [
              'numFila' => $numFila,
              'error' => $error->getResponse('error'),
            ];
          }
          $numFila++;

          /* Almaceno los cambios para luego devolverlos la función */
          $newFileData[] = $row;
        }
      }

      if (empty($errores)) {
        $returnValue->setStatus(TRUE);
        $respuesta['datos'] = $newFileData;
        $respuesta['headers'] = $headers;
        $returnValue->setResponse($respuesta);
      }
      else {
        $respuesta['errores'] = $errores;
        $returnValue->setResponse($respuesta);
      }
    }

    return $returnValue;
  }

  /**
   * Obtención de datos desde el fichero YML.
   *
   * Genera un array con los datos necesarios para la importación.
   * IMPORTANTE: NO REALIZAR NINGUNA DESCARGA DE ARCHIVOS EN ESTA FUNCIÓN. Si se
   * hace con archivos largos puede causar error de TimeOut. Mejor realizar las
   * descargas dentro del proceso batch.
   *
   * @param string $file_path
   *   Ruta al archivo a convertir en Array.
   *
   * @return Drupal\module_template_import\lib\general\ResponseFunctions
   *   Si el valor de status es TRUE devolverá el archivo preparado para
   *   su importación.
   *   Si el valor de status es FALSE devolverá un array con los errores
   *   encontrados.
   */
  private function generateFromYml(string $file_path) {
    /* Variable de retorno */
    $returnValue = new ResponseFunctions();

    /* Variables para los datos */
    $newFileData = [];
    $headers = [];
    $errores = [];

    /* Leo el contenido del archivo */
    $file_yml = Yaml::decode(file_get_contents($file_path));

    if ($file_yml) {
      /* Leo el fichero */
      foreach ($file_yml as $row) {

        /* Compruebo que tipo de contenido estoy importando */
        switch ($this->contentType) {

          case 'articles':
            /* INFO Hacer estas comprobaciones en un archivo propio del tipo de contenido */
            /* Reparo algunos datos que vienen mal de origen */
            /* Hago las validaciones pertinentes sobre la fila según los datos que deba tener */
            /* Compruebo si el campo actual es obligatorio y por lo tanto debe tener datos */
            /* También puedo agregar algún campo nuevo */
            break;
        }

        /* Almaceno los cambios para luego devolverlos la función */
        $newFileData[] = trim($row);
      }

      if (empty($errores)) {
        $returnValue->setStatus(TRUE);
        $respuesta['datos'] = $newFileData;
        $respuesta['headers'] = $headers;
        $returnValue->setResponse($respuesta);
      }
      else {
        $respuesta['errores'] = $errores;
        $returnValue->setResponse($respuesta);
      }
    }

    return $returnValue;
  }

}
