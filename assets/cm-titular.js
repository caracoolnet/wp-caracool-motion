/**
 * Caracool Motion — Titulares
 *
 * Entradas de un encabezado, línea a línea: por líneas, cortina de color y
 * relleno. Este archivo solo sabe partir un titular en sus líneas y
 * animarlo. Cuándo entra lo decide cm-scroll.js, que es quien vigila la
 * pantalla, espera a la intro y ordena el titular con el resto de piezas de
 * un bloque. Por eso se carga antes que él y no hace nada por su cuenta.
 *
 * EL HTML NO CAMBIA
 *  El titular se parte en el navegador y solo mientras dura la entrada. Los
 *  nodos originales se guardan aparte, se anima una copia y al terminar se
 *  devuelven tal cual, con sus enlaces y sus eventos. Lo que sirve la web y
 *  lo que queda en la página es el encabezado de siempre.
 *
 * SE PARTE AL ENTRAR, NO AL CARGAR
 *  Las líneas se miden justo antes de animar. Así da igual que la ventana
 *  haya cambiado de ancho o que la tipografía haya llegado tarde: se parten
 *  las líneas que el visitante tiene delante en ese momento.
 *
 *     CaracoolMotionTitular.es(widget)       → 'lineas' | 'cortina' | 'relleno' | ''
 *     CaracoolMotionTitular.esperar(widget)  → lo deja esperando su entrada
 *     CaracoolMotionTitular.entrar(widget, retraso) → lo anima; devuelve los
 *                                               segundos que conviene esperar
 *                                               antes de la pieza siguiente
 *     CaracoolMotionTitular.mostrar(widget)  → lo deja puesto ya, sin animar
 */
(function () {
	'use strict';

	var gsap = window.gsap;
	if (!gsap) { return; }

	var EFECTOS = ['lineas', 'cortina', 'relleno'];
	var SELECTOR = EFECTOS.map(function (e) { return '.cm-titular-' + e; }).join(', ');

	/* Los mismos multiplicadores que las velocidades de los efectos de sección. */
	var VELOCIDADES = { lenta: 1.6, media: 1, rapida: 0.6 };

	/* Cada efecto: cuánto dura una línea, cuánto se separa de la siguiente y
	   en qué momento de la última línea puede entrar ya lo que viene detrás.
	   Segundos a velocidad media. */
	var RITMO = {
		lineas:  { dura: 0.95, paso: 0.11, cede: 0.55 },
		cortina: { dura: 0.9,  paso: 0.13, cede: 0.6 },
		relleno: { dura: 0.75, paso: 0.13, cede: 0.75 }
	};

	var ESPACIOS = /([ \t\r\n\f]+)/;
	var SOLO_ESPACIOS = /^[ \t\r\n\f]+$/;

	function es(w) {
		if (!w || !w.classList) { return ''; }
		for (var i = 0; i < EFECTOS.length; i++) {
			if (w.classList.contains('cm-titular-' + EFECTOS[i])) {
				return w.querySelector('.elementor-heading-title') ? EFECTOS[i] : '';
			}
		}
		return '';
	}

	function velocidad(w) {
		if (w.classList.contains('cm-titular-vel-lenta')) { return VELOCIDADES.lenta; }
		if (w.classList.contains('cm-titular-vel-rapida')) { return VELOCIDADES.rapida; }
		return VELOCIDADES.media;
	}

	function caja(clase) {
		var s = document.createElement('span');
		s.className = clase;
		return s;
	}

	/**
	 * Parte el titular en sus líneas reales.
	 *
	 * Primero envuelve cada palabra, mira en qué línea ha caído cada una y
	 * vuelve a montar el titular con una caja por línea:
	 *
	 *     .cm-ln (bloque, la máscara) › .cm-lni (lo que se mueve) › .cm-tx (el texto)
	 *
	 * Los espacios duros no parten palabra, así que dos palabras unidas con
	 * uno siguen juntas. Las etiquetas de dentro (un enlace, una cursiva, un
	 * color) se conservan: cada tramo de línea lleva su copia.
	 */
	function partir(titulo) {
		var original = document.createDocumentFragment();
		while (titulo.firstChild) { original.appendChild(titulo.firstChild); }
		titulo.appendChild(original.cloneNode(true));

		var devuelto = false;
		function restaurar() {
			if (devuelto) { return; }
			devuelto = true;
			while (titulo.firstChild) { titulo.removeChild(titulo.firstChild); }
			titulo.appendChild(original);
		}

		var textos = [];
		var paseo = document.createTreeWalker(titulo, NodeFilter.SHOW_TEXT, null, false);
		while (paseo.nextNode()) { textos.push(paseo.currentNode); }

		var palabras = [];
		var hueco = false;
		textos.forEach(function (nodo) {
			var trozo = document.createDocumentFragment();
			nodo.nodeValue.split(ESPACIOS).forEach(function (t) {
				if (!t) { return; }
				if (SOLO_ESPACIOS.test(t)) {
					hueco = true;
					trozo.appendChild(document.createTextNode(t));
					return;
				}
				var w = caja('cm-w');
				w.textContent = t;
				palabras.push({ el: w, hueco: hueco });
				hueco = false;
				trozo.appendChild(w);
			});
			nodo.parentNode.replaceChild(trozo, nodo);
		});

		if (!palabras.length) { restaurar(); return null; }

		// En qué línea cae cada palabra: empieza línea nueva la que tiene su
		// centro por debajo de todo lo que llevaba la línea anterior.
		var grupos = [];
		var fondo = null;
		palabras.forEach(function (p) {
			var r = p.el.getBoundingClientRect();
			if (fondo === null || r.top + r.height / 2 > fondo) {
				grupos.push([]);
				fondo = r.bottom;
			} else if (r.bottom > fondo) {
				fondo = r.bottom;
			}
			grupos[grupos.length - 1].push(p);
		});

		// Las etiquetas que envuelven a cada palabra, de dentro afuera. Se
		// apuntan antes de desmontar la copia.
		palabras.forEach(function (p) {
			p.cadena = [];
			for (var x = p.el.parentNode; x && x !== titulo; x = x.parentNode) { p.cadena.push(x); }
		});

		while (titulo.firstChild) { titulo.removeChild(titulo.firstChild); }

		var lineas = grupos.map(function (grupo) {
			var ln = caja('cm-ln');
			var lni = caja('cm-lni');
			var tx = caja('cm-tx');
			var padreAnterior = null;
			var clonAnterior = null;

			grupo.forEach(function (p, j) {
				var espacio = (j > 0 && p.hueco) ? document.createTextNode(' ') : null;
				var padre = p.cadena[0] || null;

				// Misma etiqueta que la palabra anterior: comparten copia, para
				// que un enlace o un subrayado no se corte entre palabras.
				if (padre && padre === padreAnterior) {
					if (espacio) { clonAnterior.appendChild(espacio); }
					clonAnterior.appendChild(p.el);
					return;
				}

				if (espacio) { tx.appendChild(espacio); }
				var pieza = p.el;
				var interior = null;
				p.cadena.forEach(function (x, k) {
					var copia = x.cloneNode(false);
					copia.removeAttribute('id');
					copia.appendChild(pieza);
					if (k === 0) { interior = copia; }
					pieza = copia;
				});
				tx.appendChild(pieza);
				padreAnterior = padre;
				clonAnterior = interior;
			});

			lni.appendChild(tx);
			ln.appendChild(lni);
			titulo.appendChild(ln);
			return { ln: ln, lni: lni, tx: tx };
		});

		return { lineas: lineas, restaurar: restaurar };
	}

	/* Por líneas: cada línea sale desde debajo de su máscara. Sin fundido: la
	   letra aparece ya nítida. El recorrido es más que el alto de la línea
	   porque la máscara deja aire arriba y abajo para no cortar la letra. */
	function porLineas(tl, lineas, v) {
		var piezas = lineas.map(function (l) { return l.lni; });
		gsap.set(piezas, { yPercent: 135 });
		tl.to(piezas, { yPercent: 0, duration: RITMO.lineas.dura * v, ease: 'expo.out', stagger: RITMO.lineas.paso * v }, 0);
	}

	/* Cortina: una banda de color cruza la línea de izquierda a derecha, la
	   tapa entera, y al seguir deja el texto puesto. El texto aparece justo
	   cuando la banda lo cubre del todo. */
	function conCortina(tl, lineas, v) {
		var medio = RITMO.cortina.dura * v / 2;
		lineas.forEach(function (l, i) {
			var banda = caja('cm-banda');
			banda.setAttribute('aria-hidden', 'true');
			l.lni.appendChild(banda);
			gsap.set(l.tx, { opacity: 0 });
			gsap.set(banda, { scaleX: 0, transformOrigin: '0% 50%' });

			var t = i * RITMO.cortina.paso * v;
			tl.to(banda, { scaleX: 1, duration: medio, ease: 'power2.inOut' }, t);
			tl.set(l.tx, { opacity: 1 }, t + medio);
			tl.set(banda, { transformOrigin: '100% 50%' }, t + medio);
			tl.to(banda, { scaleX: 0, duration: medio, ease: 'power2.inOut' }, t + medio);
		});
	}

	/* Relleno: el titular está desde el principio, apagado, y cada línea se
	   llena de izquierda a derecha a paso constante. El apagado es una
	   máscara, no un color: vale igual sobre negro, sobre claro o sobre una
	   foto. La hoja lee el avance de --cm-lleno. */
	function conRelleno(tl, lineas, v) {
		var piezas = lineas.map(function (l) { return l.lni; });
		gsap.set(piezas, { '--cm-lleno': 0 });
		tl.to(piezas, { '--cm-lleno': 1, duration: RITMO.relleno.dura * v, ease: 'none', stagger: RITMO.relleno.paso * v }, 0);
	}

	var ANIMAR = { lineas: porLineas, cortina: conCortina, relleno: conRelleno };

	function esperar(w) {
		if (w._cmTitular) { return; }
		w._cmTitular = 'espera';
		w.classList.add('cm-titular-espera');
	}

	/* Lo deja puesto ya. Si estaba a media entrada, la termina de golpe y el
	   titular vuelve a su HTML original. */
	function mostrar(w) {
		var tl = w._cmTitularTl;
		w._cmTitular = 'hecho';
		w.classList.remove('cm-titular-espera');
		if (tl) { tl.progress(1); }
	}

	function entrar(w, retraso) {
		if (w._cmTitular === 'entra' || w._cmTitular === 'hecho') { return 0; }

		var efecto = es(w);
		var titulo = efecto ? w.querySelector('.elementor-heading-title') : null;
		if (!titulo) { mostrar(w); return 0; }

		var partes = null;
		try { partes = partir(titulo); } catch (e) { partes = null; }
		if (!partes) { mostrar(w); return 0; }

		var v = velocidad(w);
		var r = RITMO[efecto];
		w._cmTitular = 'entra';

		var tl = gsap.timeline({
			delay: retraso || 0,
			onComplete: function () {
				w._cmTitularTl = null;
				w._cmTitular = 'hecho';
				partes.restaurar();
			}
		});
		w._cmTitularTl = tl;

		try {
			ANIMAR[efecto](tl, partes.lineas, v);
		} catch (e) {
			tl.kill();
			w._cmTitularTl = null;
			partes.restaurar();
			mostrar(w);
			return 0;
		}

		// El estado de partida ya está puesto en cada línea: se puede quitar
		// la espera sin que el titular se vea entero ni un fotograma.
		w.classList.remove('cm-titular-espera');

		return ((partes.lineas.length - 1) * r.paso + r.cede) * v;
	}

	/* Teclado. Un titular con enlace que espera su entrada no se ve, y el
	   foco podía caer en él (WCAG 2.4.7). En cuanto algo de dentro recibe el
	   foco, el titular se muestra ya. */
	document.addEventListener('focusin', function (e) {
		var x = e.target;
		while (x && x.nodeType === 1 && x !== document.body) {
			if (x._cmTitular === 'espera' || x._cmTitular === 'entra') { mostrar(x); }
			x = x.parentElement;
		}
	});

	window.CaracoolMotionTitular = {
		SELECTOR: SELECTOR,
		es: es,
		esperar: esperar,
		entrar: entrar,
		mostrar: mostrar
	};
}());
