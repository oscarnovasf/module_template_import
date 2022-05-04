<?php

namespace Drupal\module_template_import\lib;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

use Drupal\module_template_import\lib\general\ResponseFunctions;
use Drupal\module_template_import\lib\general\ValidateFunctions;

/**
 * Gestiona todo lo relativo a ...
 */
class NodeSample {

  use StringTranslationTrait;

  /**
   * Equivalencia CSV.
   *
   * Contiene los campos que debería contener el CSV y su orden.
   * El primer valor deberá ser un identificador único del usuario.
   */
  const CAMPOS = [
    'title' => 0,
    'body' => 1,
    'tags' => 2,
    'email' => 3,
  ];

  /**
   * Contiene los campos obligatorios para la validación.
   *
   * Cada elemento del array se corresponderá con un valor del array
   * de CAMPOS.
   */
  public const CAMPOS_OBLIGATORIOS = [
    0,
    1,
    3,
  ];

  /**
   * Valida que todos los datos proporcionados sean válidos.
   *
   * @param array $row
   *   Array con los datos a validar.
   *
   * @return Drupal\module_template_import\lib\general\ResponseFunctions
   *   Si es válido getStatus() será TRUE.
   *   Si no, en getResponse('error') tendremos la descripción de error.
   */
  public function validateData(array &$row) {
    /* Valor de retorno */
    $returnValue = new ResponseFunctions();
    $returnValue->setStatus(TRUE);

    /* Array para los campos que son obligatorios */
    $camposObligatorios = self::CAMPOS_OBLIGATORIOS;

    /* Reparo algunos datos que vienen mal de origen */
    foreach ($row as $key => &$value) {

      /* Elimino espacios en blanco */
      $value = trim($value);

      /* Compruebo si el campo actual es obligatorio */
      if (in_array($key, $camposObligatorios)) {
        /* Compruebo que tenga datos */
        if (is_null($value) or ($value == '')) {
          $error = [
            'error' => $key . ': Este valor es obligatorio.',
          ];
          $returnValue->setStatus(FALSE);
          $returnValue->setResponse($error);
        }
      }

      /* Hago las validaciones pertinentes sobre la fila según los datos que deba tener */
      switch ($key) {

        case self::CAMPOS['email']:
          /* Tiene que tener un formato válido */
          if ((!ValidateFunctions::isValidEmailFormat($value)) and ($value != 'email')) {
            $error = [
              'error' => $key . ': El correo electrónico debe tener un formato válido.',
            ];
            $returnValue->setStatus(FALSE);
            $returnValue->setResponse($error);
          }
          break;

      }
    }

    /* Devuelvo el resultado obtenido */
    return $returnValue;
  }

  /**
   * Genera un nuevo elemento.
   *
   * @param array $item
   *   Array con el contenido a añadir.
   * @param bool $updateIfExist
   *   Si es TRUE se actualizan los datos en caso de existir.
   *
   * @return Drupal\module_template_import\lib\general\ResponseFunctions
   *   Si es válido getStatus() será TRUE.
   *   Si no, en getResponse('error') tendremos la descripción de error.
   */
  public function add(array $item, bool $updateIfExist = FALSE) {
    /* Valores de respuesta */
    $response = new ResponseFunctions();

    /* Variables auxiliares */
    $node = NULL;

    /* TODO Compruebo si existe el nodo */
    $existe = FALSE;

    /* TODO: Compruebo si existe el nodo */
    if ($existe) {
      if ($updateIfExist) {
        /* TODO: Actualizo el nodo */
        $node = Node::load($existe);
        if (is_object($node)) {
        }
      }
    }
    else {
      /* Genero el nodo */
      $node = Node::create([
        'type' => 'article',
        'title' => $item[self::CAMPOS['title']],
        'body' => [
          '#value' => $item[self::CAMPOS['body']],
          '#format' => 'full_html',
        ],
        'field_email' => $item[self::CAMPOS['email']],
      ]);

      if ($item[self::CAMPOS['tags']]) {
        /* Obtengo el id de la taxonomía */
        $id = $this->searchTerm($item[self::CAMPOS['tags']], 'tags', TRUE);
        $node->set('field_tags', ['target_id' => $id]);
      }
    }

    if (is_object($node)) {
      $node->save();
      $response->setStatus(TRUE);
    }
    else {
      /* TODO: Añadir info del item con error */
      $response->setReponse(['error' => 'Error al crear el nodo']);
    }

    return $response;
  }

  /**
   * Obtiene un array con los nombres de los campos.
   *
   * Estos campos se usarán como cabecera a mostrar en el formulario de
   * confirmación en caso de no existir headers propios.
   *
   * @return array
   *   Array con los nombres de los campos.
   */
  public static function getHeaders() {
    return array_keys(self::CAMPOS);
  }

  /* ***************************************************************************
   * MÉTODOS PRIVADOS.
   * **************************************************************************/

  /**
   * Obtiene el id de la taxonomía a partir del nombre y el tipo.
   *
   * Esta función, en caso de no encontrar nada, crea un nuevo término.
   *
   * @param string $name
   *   Nombre que buscamos.
   * @param string $vid
   *   Nombre de la taxonomía dónde se buscará.
   * @param bool $create_new
   *   Si el valor es TRUE, creará un término nuevo en caso de no encontrarlo.
   *
   * @return int|null
   *   Id de la taxonomía o NULL si no la encuentra y se establece que no se
   *   cree una nueva.
   */
  private function searchTerm(string $name, string $vid, bool $create_new = TRUE) {
    /* Variable de retorno */
    $respuesta = NULL;

    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['name' => $name, 'vid' => $vid]);

    foreach ($terms as $term) {
      $respuesta = $term->id();
    }

    if (!$respuesta && $create_new) {
      /* Creo un nuevo término */
      $term = Term::create([
        'name' => $name,
        'vid' => $vid,
      ]);
      $term->save();

      $respuesta = $term->id();
    }

    return $respuesta;
  }

  /**
   * Comprueba si el nodo ya ha sido creado previamente.
   *
   * @param string $title
   *   Título del nodo para verificar.
   * @param string $type
   *   Tipo de nodo.
   *
   * @return bool|int
   *   FALSE si no existe y el nid en caso de existir.
   */
  private function searchNodeByTitle(string $title, string $type) {
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'type' => $type,
        'title' => $title,
      ]);

    $nodes = reset($nodes);
    return empty($nodes) ? FALSE : $nodes->id();
  }

}
