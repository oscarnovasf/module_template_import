<?php

namespace Drupal\module_template_import\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Render\Markup;

use Symfony\Component\HttpFoundation\RedirectResponse;

use Drupal\module_template_import\lib\general\FileFunctions;
use Drupal\module_template_import\lib\general\ResponseFunctions;

/**
 * Gestiona el callback de los procesos de importación.
 */
class GeneralController extends ControllerBase {

  /**
   * Función addItem().
   */
  public static function addItem($item,
                                 $update_if_exists,
                                 $content_class,
                                 $filename,
                                 &$context) {
    $context['sandbox']['current_item'] = $item;
    $message = t('Working...');
    self::createItem($item, $update_if_exists, $content_class, $filename);
    $context['message'] = $message;
    $context['results'][] = $item;
  }

  /**
   * Callback general para todos los procesos batch.
   */
  public static function addGeneralCallback($success, $results, $operations) {
    /* The 'success' parameter means no fatal PHP errors were detected. All
     * other error management should be handled using 'results'. */
    if ($success) {
      $message = \Drupal::translation()->formatPlural(
        count($results),
        'One item processed.', '@count items processed.'
      );
      \Drupal::messenger()->addStatus($message);
    }
    else {
      $message = t('Finished with an error.');
      \Drupal::messenger()->addError($message);
    }

    /* Compruebo si existen errores de mínima importancia */
    // TODO: Añadir esto como dependencia.
    $temp_store = \Drupal::service('tempstore.private')->get('module_template_import');
    if ($file_path = $temp_store->get('has_minimal_errors')) {
      $url_error = "<a href='/$file_path' target='_blank'>log</a>";
      $message = t('Please check the following import errors: @link', [
        '@link' => Markup::create($url_error),
      ]);
      \Drupal::messenger()->addWarning($message);
    }

    /* Vacío los archivos temporales */
    $temp_store->set('array_import_from_file', NULL);
    $temp_store->set('array_headers_data', NULL);
    $temp_store->set('has_minimal_errors', NULL);

    /* Redirecciono al formulario de importación */
    $return_url = Url::fromRoute('module_template_import.import_from_file', []);
    $destination = $return_url->toString();
    $response = new RedirectResponse($destination, 301);
    return $response->send();
  }

  /* ***************************************************************************
   * MÉTODOS PRIVADOS.
   * ************************************************************************ */

  /**
   * Función createItem().
   *
   * Crea un usuario según los datos de $item.
   *
   * @param array $item
   *   Array que contiene los campos para la creación
   *   del usuario.
   * @param bool $update_if_exists
   *   TRUE si se quiere actualizar el nodo en caso de existir.
   * @param string $content_class
   *   Clase que se usará para generar el contenido.
   * @param string $filename
   *   Nombre del archivo que estamos importando.
   */
  private static function createItem(array $item,
                                     bool $update_if_exists,
                                     string $content_class,
                                     string $filename) {
    $importador = new $content_class($filename);
    $resultado = new ResponseFunctions();

    /* Creo/actualizo el nodo */
    $resultado = $importador->add($item, $update_if_exists);

    if (FALSE == $resultado->getStatus()) {
      /* El archivo no cumple los parámetros => Creo un archivo con los errores que se han producido en la validación */
      // TODO: Añadir esto como dependencia.
      $file_path = \Drupal::service('extension.path.resolver')
        ->getPath'module', "module_template_import") . "/logs/create_" . date('Y-m-d') . '.log';
      FileFunctions::createFileLog($file_path, $resultado->getResponse('errores'));
    }
  }

}
