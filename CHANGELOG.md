# Histórico de cambios
---
Todos los cambios notables de este proyecto se documentarán en este archivo.

* ## [Sin versión]
  > Ver TODO.md

---
* ## [v2.1.0] - 2023-05-05
  > Revisión.

  * #### Añadido:
    - Librería ImportBase para unificar funciones que podrían repetirse en
      los diferentes tipos de contenido.
    - Formulario de configuración del módulo.
    - Soporte para Drupal 10.
    - Fecha de actualización y creación al ejemplo de importación.

  * #### Cambios:
    - Refactor de clases y controladores para facilitar la creación de nuevos
      importadores.
    - Mejora en la gestión de errores del ejemplo con Nodos.
    - Ajuste de permisos.
    - Ajuste de rutas.

---
* ## [v2.0.1] - 2022-05-04
  > Revisión.

  * #### Añadido:
    - Posibilidad de indicar si se quiere actualizar el nodo en caso de existir.

  * #### Errores:
    - Corregidos varios errores en las rutas provocados por la revisión
      anterior.

---
* ## [v2.0.0] - 2022-05-04
  > Revisión.

  * #### Añadido:
    - Se adjunta ejemplo de importación de un Node para poder usarlo como
      plantilla.
    - Generación de cabeceras para los archivos CSV cuando éstas no están
      presentes.
    - Plantilla para composer.json.
    - Nueva estructura de menús para todos los módulos custom.

  * #### Eliminado:
    - Se ha suprimido el script de instalación en favor del script
      iniciar-proyecto.sh que permite más opciones. Se elimina porque es
      muy difícil mantener actualizados los dos scripts.

  * #### Errores:
    - Errores ortográficos en algunas librerías.

---
* ## [v1.0.0] - 2021-22-11
  > Primera versión (no publicada).
