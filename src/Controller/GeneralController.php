<?php

namespace Drupal\module_template_import\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Gestiona el callback de los procesos de importación.
 */
class GeneralController extends ControllerBase {

  /**
   * Función addGeneralCallback().
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

    /* Vacío los archivos temporales */
    $tempstore = \Drupal::service('tempstore.private')->get('module_template_import');
    $tempstore->set('array_import_data', NULL);
    $tempstore->set('array_headers_data', NULL);

    /* Redirecciono al formulario de importación */
    $return_url = Url::fromRoute('module_template_import.import_data', []);
    $destination = $return_url->toString();
    $response = new RedirectResponse($destination, 301);
    return $response->send();
  }

}
