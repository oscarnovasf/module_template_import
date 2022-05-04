<?php

namespace Drupal\module_template_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\file\Entity\File;
use Drupal\Component\Serialization\Yaml;

use Drupal\Core\Url;

use Drupal\module_template_import\lib\general\FileFunctions;
use Drupal\module_template_import\lib\general\ResponseFunctions;

use Drupal\module_template_import\lib\NodeSample;

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
   * Tipo de contenido a importar, se obtiene en la validación.
   *
   * @var string
   *   Nombre del contenido a importar.
   */
  private $contentType = "";

  /**
   * Indica si el archivo tiene cabeceras o no.
   *
   * @var bool
   *   TRUE si tiene cabeceras.
   */
  private $hasHeaders = FALSE;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'module_template_import_import_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['module_template_import_import_form.config'];
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
      '#default_value' => '',
      '#attributes' => [
        'attr-name' => 'content_type_select',
      ],
    ];

    $form['has_headers'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('File has headers'),
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

    /* Almaceno el valor de si tiene encabezados */
    $this->hasHeaders = $form_state->getValue('has_headers');

    /* Obtener el array del archivo almacenado temporalmente */
    $import_file = $form_state->getValue('import_file');
    if (!empty($import_file) and is_array($import_file)) {

      /* Leo el archivo a partir de su fid */
      $file = File::load($import_file[0]);
      $file_url = file_create_url($file->getFileUri());
      $file_path = DRUPAL_ROOT . str_replace(\Drupal::request()->getSchemeAndHttpHost(), "", $file_url);

      /* Leo lo que quiero importar */
      $aux = $form_state->getValue('content_type_select');

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
          $file_path = drupal_get_path('module', "module_template_import") . "/logs/validate_" . date('Y-m-d') . '_' . \Drupal::currentUser()->id() . '.log';
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
    $tempstore = \Drupal::service('tempstore.private')->get('module_template_import');
    $tempstore->set('array_import_data', $this->arrayImportData);
    $tempstore->set('array_headers_data', $this->arrayHeadersData);

    /* Redirecciono al formulario de confirmación */
    $form_state->setRedirectUrl(Url::fromRoute("module_template_import.confirm_form", [
      'content_type' => $this->contentType,
      'has_headers' => $this->hasHeaders,
    ]));
  }

  /* ***************************************************************************
   * FUNCIONES PRIVADAS.
   * ************************************************************************ */

  /**
   * Función generateArray().
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

    /* Corrección de los archivos con BOM */
    $bom = pack("CCC", 0xEF, 0xBB, 0xBF);

    /* Variables para los datos */
    $newFileData = [];
    $headers = [];
    $errores = [];
    $numFila = 0;

    /* Crear la ruta completa al archivo */
    $filePath = "public://temporal/$filename";

    /* *************************************************************************
     * Archivo es un YML.
     * ********************************************************************** */
    if ($this->fileExtension == 'yml') {

      /* Leo el contenido del archivo */
      $fileYML = Yaml::decode(file_get_contents($filePath));

      if ($fileYML) {
        /* Leo el fichero */
        foreach ($fileYML as $row) {

          /* Compruebo que tipo de contenido estoy importando */
          switch ($this->contentType) {

            case 'users':
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

        if (is_empty($errores)) {
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
    }

    /* *************************************************************************
     * Archivo es un CSV.
     * ********************************************************************** */
    elseif ($this->fileExtension == 'csv') {

      /* Leo el contenido del archivo */
      $fileCSV = FileFunctions::readFile($filePath, ["csv"]);

      if ($fileCSV) {
        /* Leo el fichero */
        $i = 0;
        while ($row = fgetcsv($fileCSV, 0, ';')) {

          /* Corrijo el BOM del primer elemento */
          $row[0] = str_replace($bom, '', $row[0]);

          /* Si tiene encabezados me salto la primera línea */
          if ($this->hasHeaders && $i == 0) {
            $headers[] = $row;
            $i++;
          }
          else {

            /* Añado cabeceras provisionales */
            if (!$this->hasHeaders && $i == 0) {
              $temporal = [];
              for ($contador = 1; $contador <= count($row); $contador++) {
                $temporal[] = 'Header ' . $contador;
              }
              $headers[] = $temporal;
              $i++;
            }

            /* Compruebo que tipo de contenido estoy importando */
            switch ($this->contentType) {

              case 'users':
                $user = new NodeSample();
                $error = $user->validateData($row);
                if (FALSE == $error->getStatus()) {
                  $errores[] = [
                    'numFila' => $numFila,
                    'error' => $error->getResponse('error'),
                  ];
                }
                $numFila++;
                break;
            }

            /* INFO: Aquí puedo añadir nuevos campos al CSV (en la fila) */

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
    }

    return $returnValue;
  }

}
