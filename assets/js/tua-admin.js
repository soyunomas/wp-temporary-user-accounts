/**
 * Lógica para la interfaz de administración de Temporary User Accounts.
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		const container = $('.tua-settings-table');
		if (container.length === 0) {
			return;
		}

		// Inicializar Datepicker de jQuery UI si está disponible.
		const datePicker = container.find('.tua-datepicker');
		if (typeof $.fn.datepicker === 'function' && datePicker.length > 0) {
			datePicker.datepicker({
				dateFormat: 'yy-mm-dd',
				changeMonth: true,
				changeYear: true,
				yearRange: 'c:c+10',
				minDate: 0,
				showButtonPanel: true,
				constrainInput: true,
			});
		} else {
			console.error('TUA: jQuery UI Datepicker script not loaded.');
		}

		/**
		 * Muestra u oculta los campos de configuración basados en la selección del radio button.
		 */
		function toggleExpiryOptions() {
			const selectedType = container.find('input[name="tua_expiry_type"]:checked').val();
			const relativeDiv = container.find('.tua_relative_options');
			const specificDiv = container.find('.tua_specific_options');
			const targetRoleRow = container.find('.tua_target_role_wrapper');

			// Resetear valores cuando se ocultan para no enviar datos innecesarios.
			if (selectedType !== 'relative') {
				relativeDiv.find('select').val('');
			}
			if (selectedType !== 'specific') {
				specificDiv.find('input').val('');
			}

			// Mostrar/Ocultar con un efecto suave.
			relativeDiv.toggle(selectedType === 'relative');
			specificDiv.toggle(selectedType === 'specific');
			targetRoleRow.toggle(selectedType === 'relative' || selectedType === 'specific');
		}

		// Ejecutar al cargar la página y al cambiar la opción.
		toggleExpiryOptions();
		container.on('change', 'input[name="tua_expiry_type"]', toggleExpiryOptions);
	});

})(jQuery);
