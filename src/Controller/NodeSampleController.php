<?php

namespace Drupal\module_template_import\Controller;

use Drupal\Core\Controller\ControllerBase;

use Drupal\module_template_import\lib\NodeSample;
use Drupal\module_template_import\lib\general\FileFunctions;
use Drupal\module_template_import\lib\general\ResponseFunctions;

/**
 * Gestiona el callback de los procesos de importación (Formato CSV).
 */
class NodeSampleController extends ControllerBase {

  /**
   * Función addItem().
   */
  public static function addItem($item, $update_if_exists, &$context) {
    $context['sandbox']['current_item'] = $item;
    $message = t('Working...');
    self::createItem($item, $update_if_exists);
    $context['message'] = $message;
    $context['results'][] = $item;
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
   */
  private static function createItem(array $item, bool $update_if_exists) {
    $node = new NodeSample();
    $resultado = new ResponseFunctions();

    /* Creo/actualizo el nodo */
    $resultado = $node->add($item, $update_if_exists);

    if (FALSE == $resultado->getStatus()) {
      /* El archivo no cumple los parámetros => Creo un archivo con los errores que se han producido en la validación */
      $file_path = drupal_get_path('module', "module_template_import") . "/logs/create_" . date('Y-m-d') . '.log';
      FileFunctions::createFileLog($file_path, $resultado->getResponse('errores'));
    }
  }

}
