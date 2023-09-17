<?php

namespace Drupal\module_template_import\lib\handlers;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\Node;

use Drupal\module_template_import\lib\general\ResponseFunctions;
use Drupal\module_template_import\lib\general\ValidateFunctions;

use Drupal\module_template_import\lib\base\ImportBase;

/**
 * Gestiona todo lo relativo a ...
 *
 * Se recomienda usar nombres de clase como ArticleHandlers.php.
 */
class NodeSampleHandler extends ImportBase {

  use StringTranslationTrait;

  /**
   * Equivalencia CSV.
   *
   * Contiene los campos que debería contener el CSV y su orden.
   * El primer valor deberá ser un identificador único del usuario.
   */
  protected const CAMPOS = [
    'nid'         => 0,
    'title'       => 1,
    'body'        => 2,
    'field_tags'  => 3,
    'field_email' => 4,
    'field_image' => 5,
    'alias'       => 6,
    'langcode'    => 7,
    'created'     => 8,
    'changed'     => 9,
  ];

  /**
   * Contiene los campos obligatorios para la validación.
   *
   * Cada elemento del array se corresponderá con un valor del array
   * de CAMPOS.
   */
  protected const CAMPOS_OBLIGATORIOS = [
    0,
    1,
    3,
    7,
    8,
    9,
  ];

  /**
   * Contiene el nombre de máquina del contenido a crear / modificar.
   */
  protected const CONTENT_TYPE = 'article';

  private $remoteUrl = "";

  /**
   * Nombre del archivo que se está importando.
   *
   * @var string
   *   Nombre del archivo.
   */
  private $fileName = "";

  /**
   * Constructor de la clase.
   *
   * @param string $filename
   *   Nombre del archivo que estamos importando.
   */
  public function __construct(string $filename) {
    $this->fileName = $filename;

    /* TODO Añadir esto a la configuración del módulo */
    $this->remoteUrl = '';
  }

  /**
   * Valida que todos los datos proporcionados sean válidos.
   *
   * @param array $row
   *   Array con los datos a validar.
   *
   * @return \Drupal\module_template_import\lib\general\ResponseFunctions
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

        case self::CAMPOS['field_email']:
          /* Tiene que tener un formato válido */
          if ((!ValidateFunctions::isValidEmailFormat($value))) {
            $error = [
              'error' => $key . ': El correo electrónico debe tener un formato válido.',
            ];
            $returnValue->setStatus(FALSE);
            $returnValue->setResponse($error);
          }
          break;

        case self::CAMPOS['alias']:
          /* Si el alias es de tipo /node/XX lo elimino para no contemplarlo */
          $re = '/\/node\/[0-9]*/m';
          preg_match_all($re, $value, $matches, PREG_SET_ORDER, 0);
          if ($matches) {
            $value = '';
          }
          else {
            /* Elimino idioma del path */
            $value = str_replace('/gl/', '/', $value);
            $value = str_replace('/es/', '/', $value);
            $value = str_replace('/en/', '/', $value);
          }
          break;

        case self::CAMPOS['field_image']:
          /* Convierto la url en una url remota */
          $value = $this->remoteUrl . $value;
          break;

        case self::CAMPOS['created']:
          if (!is_numeric($value)) {
            $error = [
              'error' => $key . '-' . $value . ': La fecha de creación debe estar en formato time (numérico).',
            ];
            $returnValue->setStatus(FALSE);
            $returnValue->setResponse($error);
          }
          break;

        case self::CAMPOS['changed']:
          if (!is_numeric($value)) {
            $error = [
              'error' => $key . '-' . $value . ': La fecha de última actualización debe estar en formato time (numérico).',
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
   * @return \Drupal\module_template_import\lib\general\ResponseFunctions
   *   Si es válido getStatus() será TRUE.
   *   Si no, en getResponse('error') tendremos la descripción de error.
   */
  public function add(array $item, bool $updateIfExist = FALSE) {
    /* Valores de respuesta */
    $response = new ResponseFunctions();

    /* Variables auxiliares */
    $node = NULL;

    /* *************************************************************************
     * ACTUALIZACIÓN DEL NODO (si procede).
     * ************************************************************************/
    if ($nid = $this->searchNodeByTitle($item[self::CAMPOS['title']],
                                        self::CONTENT_TYPE)) {

      $node = Node::load($nid);
      if (is_object($node)) {
        if ($updateIfExist) {
          $node->set('created', $item[self::CAMPOS['created']]);
          $node->save();

          /* INFO: Si no se trabaja con traducciones entonces actualizar directamente el nodo. */
          /* Compruebo que también exista la traducción */
          if ($node->hasTranslation($item[self::CAMPOS['langcode']])) {
            /* Actualizo la traducción */
            $this->updateTranslation($node, $item);
            $response->setStatus(TRUE);
          }
          else {
            /* Creo la nueva traducción */
            $this->createTranslation($node, $item);
            $response->setStatus(TRUE);
          }
        }
        else {
          /* INFO: Si no se trabaja con traducciones entonces mostrar error directamente. */
          /* Compruebo si es una traducción y si ésta existe */
          if (!$node->hasTranslation($item[self::CAMPOS['langcode']])) {
            /* Creo la traducción */
            $this->createTranslation($node, $item);
            $response->setStatus(TRUE);
          }
          else {
            $errores = [
              'error' => 'Nodo ya existe: ' . $item[self::CAMPOS['title']],
            ];
            $response->setResponse(['errores' => $errores]);
          }
        }
      }
      else {
        $errores = [
          'error' => 'Error al obtener datos del nodo: ' . $item[self::CAMPOS['title']],
        ];
        $response->setResponse(['errores' => $errores]);
      }
    }

    /* *************************************************************************
     * GENERACIÓN DE NODO.
     * ************************************************************************/
    else {
      /* TODO: Genero el nodo */
      $node = Node::create([
        'type' => self::CONTENT_TYPE,
        'title' => $item[self::CAMPOS['title']],
        'body' => [
          'value' => $this->downloadFileAndUpdateContentFromBody($this->remoteUrl, $item[self::CAMPOS['body']], self::CONTENT_TYPE),
          'summary' => '',
          'format' => 'full_html',
        ],
        'field_email' => $item[self::CAMPOS['field_email']],
        'langcode' => $item[self::CAMPOS['langcode']],
        'created' => $item[self::CAMPOS['created']],
      ]);

      if (is_object($node)) {
        if ($item[self::CAMPOS['alias']]) {
          $this->createOrUpdateAlias($item[self::CAMPOS['alias']],
                                     "/node/" . $node->id(),
                                     $item[self::CAMPOS['langcode']]);
        }
        $node->save();

        /* Genero campos que dependen de que el nodo exista */
        $this->saveOtherElements($node, $item);
        $response->setStatus(TRUE);
      }
      else {
        $errores = [
          'error' => 'Error al crear el nodo: ' . $item[self::CAMPOS['title']],
        ];
        $response->setResponse(['errores' => $errores]);
      }
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
   * Realiza el guardado de los elementos que necesitan que el nodo exista.
   *
   * @param \Drupal\node\Entity\Node $node
   *   Nodo.
   * @param array $item
   *   Array con los datos a almacenar.
   *
   * @SuppressWarnings("CyclomaticComplexity")
   */
  private function saveOtherElements(Node $node, array $item) {
    /* Imágenes */
    $img_url = $item[self::CAMPOS['field_image']];
    if ($img_url) {
      /* Intento la descarga de la imagen */
      $img_id = $this->downloadFile($img_url, self::CONTENT_TYPE, FALSE);
      if ($img_id) {
        $node->set('field_image', [
          'target_id' => $img_id,
          'alt' => '',
        ]);
      }
      else {
        $header = "No se ha podido descargar la imagen: $img_url";
        $this->generateLog($item, $this->fileName, $header);
      }
    }

    /* Categorías */
    $cat = $item[self::CAMPOS['field_tags']];
    if ($cat) {
      /* Obtengo el id de la taxonomía */
      $id = $this->searchTermByName($cat, 'tags', TRUE);
      if ($id) {
        $node->set('field_tags', ['target_id' => $id]);
      }
      else {
        $header = "No se ha podido crear/encontrar la categoría $cat";
        $this->generateLog($item, $this->fileName, $header);
      }
    }

    /* Genero alias */
    if ($item[self::CAMPOS['alias']]) {
      $this->createOrUpdateAlias($item[self::CAMPOS['alias']],
                                 "/node/" . $node->id(),
                                 $item[self::CAMPOS['langcode']]);
    }

    /* Añado la fecha de última modificación */
    $node->set('changed', $item[self::CAMPOS['changed']]);
    $node->save();

    $node->save();
  }

  /**
   * Crea una nueva traducción para el nodo.
   *
   * @param \Drupal\node\Entity\Node $node
   *   Nodo.
   * @param array $item
   *   Array con los datos a almacenar.
   */
  private function createTranslation(Node $node, array $item) {
    /* Genero traducción y asigno título */
    $node_translation = $node->addTranslation($item[self::CAMPOS['langcode']]);
    $this->changeTranslation($node_translation, $item);
  }

  /**
   * Actualiza la traducción para el nodo.
   *
   * @param \Drupal\node\Entity\Node $node
   *   Nodo.
   * @param array $item
   *   Array con los datos a almacenar.
   */
  private function updateTranslation(Node $node, array $item) {
    /* Genero traducción y asigno título */
    $node_translation = $node->getTranslation($item[self::CAMPOS['langcode']]);
    $this->changeTranslation($node_translation, $item);
  }

  /**
   * Actualiza los campos traducibles del nodo.
   *
   * @param \Drupal\node\Entity\Node $node_translation
   *   Nodo.
   * @param array $item
   *   Array con los datos a almacenar.
   */
  private function changeTranslation(Node $node_translation, array $item) {
    $node_translation->setTitle($item[self::CAMPOS['title']]);

    /* Valido campos traducibles */
    $node_translation_fields = $node_translation->getTranslatableFields();
    foreach ($node_translation_fields as $name => $field) {
      if ('body' == $name) {
        $node_translation->set('body', [
          'value' => $this->downloadFileAndUpdateContentFromBody($this->remoteUrl, $item[self::CAMPOS['body']], self::CONTENT_TYPE),
          'summary' => $item[self::CAMPOS['body_resume']],
          'format' => 'full_html',
        ]);
      }

      if ('alias' == $name) {
        /* Genero alias */
        if ($item[self::CAMPOS['alias']]) {
          $this->createOrUpdateAlias($item[self::CAMPOS['alias']],
                                     "/node/" . $node_translation->id(),
                                     $item[self::CAMPOS['langcode']]);
        }
      }
    }

    $node_translation->save();

    /* TODO: Para estos elementos es necesario verificar si son traducibles también */
    // $this->saveOtherElements($node_translation, $item);
  }

}
