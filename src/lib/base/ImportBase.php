<?php

namespace Drupal\module_template_import\lib\base;

use Drupal\Core\File\FileSystemInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\path_alias\Entity\PathAlias;

use Drupal\module_template_import\lib\general\FileFunctions;

/**
 * Conjunto de funciones para usar en las importaciones.
 *
 * @SuppressWarnings("unused")
 */
class ImportBase {

  /**
   * Realiza la descarga de un fichero en nuestra carpeta public.
   *
   * @param string $url
   *   URL completa del fichero.
   * @param string $content_type
   *   Tipo de contenido para asociarlo a una carpeta.
   * @param bool $private
   *   Si se establece a TRUE se guarda el archivo como privado.
   *
   * @return int|null
   *   Id del fichero generado o null si no se ha podido guardar/descargar.
   */
  protected function downloadFile(string $url, string $content_type, bool $private = FALSE) {
    /* Compruebo la existencia de los directorios necesarios para la importación */
    $directory = "public://imported/$content_type/";
    if ($private) {
      $directory = "private://imported/$content_type/";
    }
    \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

    /* Obtengo información de la url del fichero */
    $parse = parse_url($url);
    $ref = $parse['scheme'] . '//' . $parse['host'];

    /* Inicio la descarga del fichero */
    $ch = curl_init();

    // FOLLOWLOCATION cannot be used when safe_mode/open_basedir are on.
    curl_setopt_array($ch, [
      CURLOPT_URL => $url,
      CURLOPT_REFERER => $ref,
      CURLOPT_BINARYTRANSFER => 1,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_TIMEOUT => 120,
      CURLOPT_FOLLOWLOCATION => TRUE,
      CURLOPT_SSL_VERIFYPEER => FALSE,
    ]);

    $content = curl_exec($ch);
    curl_close($ch);

    /* Guardo el fichero en Drupal */
    $file = \Drupal::service('file.repository')
      ->writeData($content, $directory . FileFunctions::sanitizeFilename(basename($url)), FileSystemInterface::EXISTS_RENAME);

    if (is_object($file)) {
      return $file->id();
    }

    return NULL;
  }

  /**
   * Prepara el body y descarga los archivos que contenga.
   *
   * @param string $url_base
   *   URL para añadir a la descarga del fichero.
   * @param string $body
   *   Texto que contiene los ficheros a descargar.
   * @param string $content_type
   *   Tipo de contenido para asociarlo a una carpeta.
   *
   * @return string
   *   Cadena formateada con los enlaces a los archivos descargados.
   */
  protected function downloadFileAndUpdateContentFromBody(string $url_base,
                                                          string $body,
                                                          string $content_type) {
    $new_body = $body;

    $re_src = '/(<img.*?)(src=)"(.*?)"/m';
    $re_external = '/(https?:)(.*?)/m';
    preg_match_all($re_src, $body, $matches, PREG_SET_ORDER, 0);
    foreach ($matches as $src_data) {
      /* El fichero siempre estará en la cuarta posición */
      $file = $src_data[3];

      /* Compruebo que no se trate de una imagen externa */
      preg_match_all($re_external, $file, $matches_external, PREG_SET_ORDER, 0);
      if (count($matches_external) == 0) {
        /* Preparo para la descarga y descargo el archivo */
        $file_download = $url_base . $file;
        $downloaded = $this->downloadFile($file_download, $content_type, FALSE);
        if ($downloaded) {
          /* Modifico el body con la url del nuevo fichero */
          $new_body = str_replace($file, '/sites/default/files/' . $content_type . '/' . FileFunctions::sanitizeFilename(basename($file)), $new_body);
        }
      }
    }
    return $new_body;
  }

  /**
   * Genera un archivo de log.
   *
   * @param array $values
   *   Datos que se han intentado almacenar.
   * @param string $filename
   *   Nombre del archivo que se está importando y da nombre al log.
   * @param string $header
   *   Cabecera del log.
   */
  protected function generateLog(array $values, string $filename, string $header) {
    /* Obtengo ruta para los logs */
    $directory = \Drupal::service('extension.path.resolver')
      ->getPath('module', "module_template_import") . "/logs";

    /* Preparo el directorio de destiono */
    \Drupal::service('file_system')
      ->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

    /* Genero el log */
    $file_path = $directory . '/' . $filename . '.log';
    $log_file = fopen($file_path, "a+");

    /* Cabecera del error */
    fwrite($log_file, $header . "\n");
    fwrite($log_file, "Datos:\n");

    foreach ($values as $key => $value) {
      fwrite($log_file, "  - " . $key . " => " . $value . "\n");
    }

    fwrite($log_file, "\n\n");
    fclose($log_file);

    /* Establezco que se han encontrado errores */
    $temp_store = \Drupal::service('tempstore.private')
      ->get('module_template_import');
    $temp_store->set('has_minimal_errors', $file_path);
  }

  /**
   * Crea o actualiza un alias del sistema.
   *
   * @param string $alias
   *   Alias a comprobar.
   * @param string $path
   *   Path a añadir o modificar.
   * @param string $langcode
   *   Idioma del alias.
   */
  protected function createOrUpdateAlias(string $alias,
                                         string $path,
                                         string $langcode) {
    $path_alias_repository = \Drupal::service('path_alias.repository');

    $old_alias = $path_alias_repository->lookupByAlias($alias, $langcode);
    if (is_array($old_alias)) {
      $path = PathAlias::load($old_alias['id']);
      $path->set('path', $path);
      $path->set('alias', $alias);
      $path->set('langcode', $langcode);
      $path->save();
    }
    else {
      PathAlias::create([
        'path' => $path,
        'alias' => $alias,
        'langcode' => $langcode,
      ])->save();
    }
  }

  /* ***************************************************************************
   * BUSCADORES.
   * ************************************************************************ */

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
  protected function searchTermByName(string $name,
                                    string $vid,
                                    bool $create_new = TRUE) {
    /* Variable de retorno */
    $respuesta = NULL;

    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['name' => $name, 'vid' => $vid]);

    foreach ($terms as $term) {
      $respuesta = $term->id();
    }

    if (!$respuesta && $create_new && $name) {
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
   * La búsqueda se realiza por el título del nodo.
   *
   * @param string $title
   *   Título del nodo para verificar.
   * @param string $type
   *   Tipo de nodo.
   *
   * @return bool|int
   *   FALSE si no existe y el nid en caso de existir.
   */
  protected function searchNodeByTitle(string $title, string $type) {
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'type' => $type,
        'title' => $title,
      ]);

    $nodes = reset($nodes);
    return empty($nodes) ? FALSE : $nodes->id();
  }

  /**
   * Comprueba si el nodo ya ha sido creado previamente.
   *
   * La búsqueda se realiza por un campo concreto que contendrá el ID del nodo
   * original.
   *
   * @param string $old_id
   *   Id de la web antigua del nodo para verificar.
   * @param string $type
   *   Tipo de nodo.
   * @param string $field_name
   *   Nombre máquina del campo que contiene el ID.
   *
   * @return bool|int
   *   FALSE si no existe y el nid en caso de existir.
   */
  protected function searchNodeByOldId(string $old_id,
                                       string $type,
                                       string $field_name) {
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'type' => $type,
        $field_name => $old_id,
      ]);

    $nodes = reset($nodes);
    return empty($nodes) ? FALSE : $nodes->id();
  }

}
