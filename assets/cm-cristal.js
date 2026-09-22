/*!
 * Caracool Motion — Botones de cristal: la luz sigue al cursor.
 * Solo en dispositivos con ratón. Un único escuchador para toda la página.
 */
(function () {
	'use strict';
	if (!window.matchMedia || !window.matchMedia('(hover: hover)').matches) { return; }
	document.addEventListener('pointermove', function (e) {
		var b = e.target && e.target.closest && e.target.closest('[data-cm-boton^="cristal"] .elementor-button');
		if (!b) { return; }
		var r = b.getBoundingClientRect();
		b.style.setProperty('--cm-x', (e.clientX - r.left) + 'px');
		b.style.setProperty('--cm-y', (e.clientY - r.top) + 'px');
	}, { passive: true });
})();
