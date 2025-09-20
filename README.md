# WordPress Temporary User Accounts

[![Licencia](https://img.shields.io/badge/Licencia-GPLv2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Versión](https://img.shields.io/badge/Versión-2.0.0-brightgreen.svg)](https://github.com/soyunomas/wp-temporary-user-accounts)
[![Requiere PHP](https://img.shields.io/badge/PHP-7.4+-blueviolet.svg)](https://www.php.net/)
[![Requiere WordPress](https://img.shields.io/badge/WordPress-5.8+-informational.svg)](https://wordpress.org/)

Un plugin para WordPress que permite asignar roles temporales a los usuarios, cambiándolos automáticamente a un rol predefinido después de un tiempo o en una fecha específica. Ideal para gestionar membresías, accesos de prueba o cuentas con privilegios limitados en el tiempo.

## ✨ Características Principales

*   **Expiración Flexible:** Configura la expiración de un rol de usuario de dos maneras:
    *   **Relativa:** Después de un periodo de tiempo (ej. 1 hora, 7 días, 1 mes).
    *   **Específica:** En una fecha concreta (ej. 31 de diciembre de 2027).
*   **Rol de Destino:** Elige a qué rol cambiará el usuario una vez que su acceso temporal expire.
*   **Integración Nativa:** La configuración se integra directamente en las páginas de "Añadir nuevo usuario" y en el perfil de cada usuario existente.
*   **Gestión Visual:** Una nueva columna en la tabla de usuarios del panel de administración muestra el estado de expiración de cada cuenta.
*   **Seguridad:** Diseñado con la seguridad como prioridad. No se aplica a los administradores para evitar bloqueos accidentales.
*   **Automatizado:** Utiliza el sistema de Cron de WordPress (WP-Cron) para gestionar los cambios de rol de forma fiable en segundo plano.

## 🚀 Instalación

1.  Descarga la última versión del plugin desde la [página de Releases](https://github.com/soyunomas/wp-temporary-user-accounts/releases).
2.  Ve a tu panel de administración de WordPress > `Plugins` > `Añadir nuevo`.
3.  Haz clic en `Subir plugin` y selecciona el archivo `.zip` que descargaste.
4.  Activa el plugin.

¡Listo! Ahora verás las opciones de configuración en los perfiles de usuario.

## ⚙️ ¿Cómo se usa?

Una vez instalado y activado, el uso es muy intuitivo:

1.  **Para un nuevo usuario:**
    *   Ve a `Usuarios` > `Añadir nuevo`.
    *   Debajo de los campos habituales, encontrarás la sección "Configuración de Cuenta Temporal".
    *   Elige el tipo de expiración y el rol de destino.
    *   Completa el resto de datos y haz clic en `Añadir nuevo usuario`.

2.  **Para un usuario existente:**
    *   Ve a `Usuarios` > `Todos los usuarios` y haz clic en "Editar" en el usuario que desees modificar.
    *   Busca la sección "Configuración de Cuenta Temporal".
    *   Ajusta la configuración y haz clic en `Actualizar usuario` al final de la página.

**Nota:** Por motivos de seguridad, estas opciones no se mostrarán ni aplicarán a usuarios con el rol de `Administrador`.

## 📸 Capturas de Pantalla

*(Sugerencia: Añade aquí imágenes para mostrar cómo se ve la interfaz)*

**Configuración en el perfil de un usuario:**
![Configuración de Expiración](URL_A_TU_IMAGEN_1.png)

**Columna de estado en la lista de usuarios:**
![Columna de Estado](URL_A_TU_IMAGEN_2.png)


## 📜 Licencia

Este plugin está licenciado bajo la GPLv2 o posterior. Consulta el archivo `LICENSE` para más detalles.
