<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * This file is responsible for cleaning up all plugin data from the database.
 * This includes user meta and any scheduled cron events.
 *
 * @package         TemporaryUserAccounts
 */

// If uninstall is not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// --- Importante: Definimos las constantes aquí porque el plugin principal no está cargado ---
// Es crucial que estos valores coincidan exactamente con los de tu plugin.
define( 'TUA_UNINSTALL_EXPIRY_META_KEY', '_tua_expiry_timestamp' );
define( 'TUA_UNINSTALL_TARGET_ROLE_META_KEY', '_tua_target_role' );
define( 'TUA_UNINSTALL_SETTING_DISPLAY_META_KEY', '_tua_setting_display' );
define( 'TUA_UNINSTALL_CRON_HOOK', 'tua_change_user_role_event' );

// 1. Limpiar todos los eventos de Cron programados por este plugin.
// Esto es más eficiente que buscar eventos individuales.
wp_clear_scheduled_hook( TUA_UNINSTALL_CRON_HOOK );

// 2. Eliminar los metadatos de usuario de TODOS los usuarios.
// Usamos una consulta directa para ser más eficientes en sitios con muchos usuarios,
// pero una forma más compatible es iterar sobre los usuarios.
// Por simplicidad y claridad, aquí iteramos.

// Obtiene solo los IDs de todos los usuarios para optimizar la memoria.
$all_user_ids = get_users( [ 'fields' => 'ID' ] );

if ( ! empty( $all_user_ids ) ) {
	foreach ( $all_user_ids as $user_id ) {
		delete_user_meta( $user_id, TUA_UNINSTALL_EXPIRY_META_KEY );
		delete_user_meta( $user_id, TUA_UNINSTALL_TARGET_ROLE_META_KEY );
		delete_user_meta( $user_id, TUA_UNINSTALL_SETTING_DISPLAY_META_KEY );
	}
}

// Si tuvieras opciones en la tabla `wp_options`, también las borrarías aquí:
// delete_option( 'tua_plugin_settings' );
