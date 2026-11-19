<?php
/**
 * Caracool Motion — Módulo Texto
 * ─────────────────────────────────────────────────────────────────────
 * Archivo aparte y autorregistrado, como el resto de módulos.
 *
 * QUÉ HACE
 *  Añade al widget Encabezado de Elementor (Estilo → Caracool Motion) dos
 *  ajustes, que se pueden usar juntos o por separado:
 *
 *  1. Al pasar el cursor: el texto se rellena de color de izquierda a
 *     derecha y se vacía al salir.
 *  2. Al entrar en pantalla: el titular entra línea a línea. Por líneas
 *     (cada una sale de debajo de una máscara), con cortina de color (una
 *     banda cruza la línea y deja el texto puesto) o con relleno (el
 *     titular está apagado y se llena de izquierda a derecha).
 *
 *  Cada cosa carga sus archivos solo en las páginas que la usan.
 *
 * AL PASAR EL CURSOR NO HAY JAVASCRIPT
 *  El relleno es un degradado de dos mitades recortado con la forma de las
 *  letras (background-clip: text) que se desplaza con una transición. Lo
 *  hace el navegador solo. Solo actúa en encabezados con enlace: el
 *  degradado se mide sobre la caja del enlace, que es justo el ancho del
 *  texto. Un encabezado sin enlace es un bloque del ancho del contenedor, y
 *  el relleno cruzaría la palabra en una fracción del tiempo.
 *
 * AL ENTRAR EN PANTALLA SÍ, Y LO REPARTEN DOS ARCHIVOS
 *  `cm-titular.js` parte el titular en sus líneas y lo anima. Cuándo entra
 *  lo decide `cm-scroll.js`, que ya vigila la pantalla, espera a la intro y
 *  ordena las piezas de un bloque con «Entrada escalonada». Por eso este
 *  módulo registra los archivos y dice si la página los necesita, y es el
 *  módulo Scroll quien los encola (ver `imprimir_assets()` allí).
 *
 *  El titular se parte en el navegador y solo mientras dura la entrada. Al
 *  terminar vuelve a su HTML original. Lo que sirve la web no cambia.
 *
 * POR QUÉ NO HAY COLORES AQUÍ
 *  El color de reposo es el que el encabezado ya tiene en Elementor. Los
 *  del relleno al pasar y de la cortina se eligen en el propio encabezado
 *  con el selector nativo y los globales del Kit; vacíos, son el color de
 *  Énfasis. Cada control escribe una variable CSS en el widget y la hoja la
 *  lee de ahí.
 *
 * No tiene ajustes de sitio: no guarda ninguna opción.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Caracool_Motion_Texto {

	public function __construct() {
		add_action( 'elementor/element/heading/section_title_style/after_section_end', array( $this, 'controles_encabezado' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'registrar_assets' ) );
		// Prioridad 11: las ventanas emergentes de Elementor se pintan en
		// wp_footer con la 10, y hasta entonces su hoja no está en la cola.
		// Los estilos encolados aquí salen con los del pie, en la 20.
		add_action( 'wp_footer', array( $this, 'imprimir_assets' ), 11 );

		add_action( 'caracool_motion_settings_panels', array( $this, 'panel' ) );
	}

	// ── Catálogos ───────────────────────────────────────────────────────

	/** Efectos al pasar el cursor. */
	public static function efectos() {
		return array(
			'relleno' => array(
				'etiqueta' => 'Relleno de izquierda a derecha',
				'ayuda'    => 'El texto se rellena de color de izquierda a derecha al pasar el cursor y se vacía al salir.',
			),
		);
	}

	/** Segundos de cada velocidad al pasar el cursor. «media» es la de la hoja y no escribe nada. */
	public static function velocidades() {
		return array(
			'lenta'  => '.7s',
			'rapida' => '.3s',
		);
	}

	/** Entradas del titular. Las claves son las mismas que entiende `cm-titular.js`. */
	public static function entradas() {
		return array(
			'lineas'  => array(
				'etiqueta' => 'Por líneas',
				'ayuda'    => 'Cada línea del titular sale desde debajo de una máscara, una detrás de otra. No hay fundido: la letra aparece ya nítida.',
			),
			'cortina' => array(
				'etiqueta' => 'Cortina de color',
				'ayuda'    => 'Una banda de color cruza cada línea de izquierda a derecha y, al seguir, deja el texto puesto.',
			),
			'relleno' => array(
				'etiqueta' => 'Relleno de izquierda a derecha',
				'ayuda'    => 'El titular está desde el principio, apagado, y cada línea se llena de izquierda a derecha.',
			),
		);
	}

	// ── 1) Controles en cada encabezado de Elementor ────────────────────

	public function controles_encabezado( $element, $args ) {
		if ( ! class_exists( '\Elementor\Controls_Manager' ) ) {
			return;
		}

		$opciones = array( '' => 'Nada' );
		foreach ( self::efectos() as $clave => $efecto ) {
			$opciones[ $clave ] = $efecto['etiqueta'];
		}

		$entradas = array( '' => 'Nada' );
		foreach ( self::entradas() as $clave => $entrada ) {
			$entradas[ $clave ] = $entrada['etiqueta'];
		}

		$element->start_controls_section(
			'cm_texto_seccion',
			array(
				'label' => 'Caracool Motion',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		// Al pasar el cursor.

		$element->add_control(
			'cm_texto',
			array(
				'label'        => 'Al pasar el cursor',
				'type'         => \Elementor\Controls_Manager::SELECT,
				'options'      => $opciones,
				'default'      => '',
				'prefix_class' => 'cm-texto-',
				'description'  => 'Solo actúa si el encabezado tiene enlace. Deja vacío el color al pasar del propio encabezado (Estilo → Título): los dos cambios a la vez se pisan.',
			)
		);

		$element->add_control(
			'cm_texto_color',
			array(
				'label'       => 'Color del relleno',
				'type'        => \Elementor\Controls_Manager::COLOR,
				'global'      => array( 'active' => true ),
				'selectors'   => array( '{{WRAPPER}}' => '--cm-texto-relleno: {{VALUE}};' ),
				'description' => 'Vacío: el color de Énfasis del Kit.',
				'condition'   => array( 'cm_texto!' => '' ),
			)
		);

		$element->add_control(
			'cm_texto_velocidad',
			array(
				'label'                => 'Velocidad',
				'type'                 => \Elementor\Controls_Manager::SELECT,
				'options'              => array(
					''       => 'Media',
					'lenta'  => 'Lenta',
					'rapida' => 'Rápida',
				),
				'default'              => '',
				'selectors'            => array( '{{WRAPPER}}' => '--cm-texto-vel: {{VALUE}};' ),
				'selectors_dictionary' => self::velocidades(),
				'condition'            => array( 'cm_texto!' => '' ),
			)
		);

		// Al entrar en pantalla. Las dos clases las lee cm-titular.js del
		// propio widget: no hay atributos ni datos aparte.

		$element->add_control(
			'cm_titular',
			array(
				'label'        => 'Al entrar en pantalla',
				'type'         => \Elementor\Controls_Manager::SELECT,
				'options'      => $entradas,
				'default'      => '',
				'separator'    => 'before',
				'prefix_class' => 'cm-titular-',
				'description'  => 'El titular entra línea a línea cuando llega a la pantalla. Se ve en la página publicada, no en el editor. Dentro de un contenedor con «Entrada escalonada» va en su turno, y las piezas de detrás esperan a que termine.',
			)
		);

		$element->add_control(
			'cm_titular_color',
			array(
				'label'       => 'Color de la cortina',
				'type'        => \Elementor\Controls_Manager::COLOR,
				'global'      => array( 'active' => true ),
				'selectors'   => array( '{{WRAPPER}}' => '--cm-titular-color: {{VALUE}};' ),
				'description' => 'Vacío: el color de Énfasis del Kit. Sobre un fondo de ese mismo color la cortina no se vería: elige otro.',
				'condition'   => array( 'cm_titular' => 'cortina' ),
			)
		);

		$element->add_control(
			'cm_titular_velocidad',
			array(
				'label'        => 'Velocidad de la entrada',
				'type'         => \Elementor\Controls_Manager::SELECT,
				'options'      => array(
					''       => 'Media',
					'lenta'  => 'Lenta',
					'rapida' => 'Rápida',
				),
				'default'      => '',
				'prefix_class' => 'cm-titular-vel-',
				'condition'    => array( 'cm_titular!' => '' ),
			)
		);

		$element->end_controls_section();
	}

	// ── 2) Los archivos, solo donde hacen falta ─────────────────────────

	public function registrar_assets() {
		$base = CARACOOL_MOTION_URL . 'assets/';
		wp_register_style( 'cm-texto', $base . 'cm-texto.css', array(), caracool_motion_ver( 'cm-texto.css' ) );
		// Los de las entradas se registran aquí y los encola el módulo Scroll,
		// como dependencia de su script.
		wp_register_style( 'cm-titular', $base . 'cm-titular.css', array(), caracool_motion_ver( 'cm-titular.css' ) );
		wp_register_script( 'cm-titular', $base . 'cm-titular.js', array( 'cm-gsap' ), caracool_motion_ver( 'cm-titular.js' ), true );
	}

	/** Si algún encabezado de esta página lleva guardado uno de esos valores
	 *  en ese ajuste. Se mira en los datos de cada documento, porque con la
	 *  caché de elementos de Elementor no se pasa por el render. */
	private static function pagina_lleva( $ajuste, $valores ) {
		foreach ( caracool_motion_documentos_de_la_pagina() as $id ) {
			$datos = get_post_meta( $id, '_elementor_data', true );
			if ( ! is_string( $datos ) ) {
				continue;
			}
			foreach ( $valores as $clave ) {
				if ( false !== strpos( $datos, '"' . $ajuste . '":"' . $clave . '"' ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/** Si algún encabezado de esta página lleva un efecto al pasar el cursor. */
	public static function pagina_usa_texto() {
		return self::pagina_lleva( 'cm_texto', array_keys( self::efectos() ) );
	}

	/**
	 * Si algún encabezado de esta página lleva una entrada propia.
	 *
	 * Lo pregunta el módulo Scroll al imprimir sus archivos, con prioridad 5
	 * en `wp_footer`. Las ventanas emergentes se pintan después, así que un
	 * titular dentro de una no cuenta: ahí el encabezado se ve sin entrada.
	 */
	public static function pagina_usa_titular() {
		return self::pagina_lleva( 'cm_titular', array_keys( self::entradas() ) );
	}

	public function imprimir_assets() {
		// En el editor se carga siempre, para ver el efecto al elegirlo.
		if ( caracool_motion_en_editor() || self::pagina_usa_texto() ) {
			wp_enqueue_style( 'cm-texto' );
		}
	}

	// ── 3) Pestaña en el panel de Caracool Motion ───────────────────────

	public function panel() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<section id="cm-texto" class="cm-modulo" data-titulo="Texto">

			<div class="cm-card">
				<div class="cm-card-head">
					<div class="cm-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 6h14"/><path d="M12 6v12"/><path d="M4 21h9"/></svg></div>
					<h2>Al pasar el cursor</h2>
				</div>
				<p class="cm-card-desc">Al pasar el cursor por un encabezado con enlace, el texto se rellena de color de izquierda a derecha y se vacía al salir. Está pensado para los enlaces de un menú o de una lista. Se elige encabezado por encabezado en Elementor: <strong>Estilo → Caracool Motion → Al pasar el cursor</strong>.</p>
				<table class="cm-tabla">
					<thead><tr><th style="width:34%">Ajuste</th><th>Qué hace</th></tr></thead>
					<tbody>
						<tr><td><strong>Color del relleno</strong></td><td>El color con el que se rellena el texto, elegido entre los globales del Kit. Vacío, el de Énfasis. El color de reposo es el que el encabezado ya tiene.</td></tr>
						<tr><td><strong>Velocidad</strong></td><td>Lenta (0,7 s), media (0,45 s) o rápida (0,3 s).</td></tr>
					</tbody>
				</table>
				<p class="cm-card-desc" style="margin-top:14px">Solo actúa si el encabezado tiene enlace. No lleva JavaScript y su hoja de estilos (unos 2 KB) solo se carga en las páginas que lo usan. Con el movimiento reducido el color cambia sin animación, y con alto contraste o al imprimir el texto se ve normal.</p>
			</div>

			<div class="cm-card">
				<div class="cm-card-head">
					<div class="cm-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M4 12h11"/><path d="M4 17h14"/></svg></div>
					<h2>Al entrar en pantalla</h2>
				</div>
				<p class="cm-card-desc">El titular entra línea a línea cuando llega a la pantalla. Se elige encabezado por encabezado en Elementor: <strong>Estilo → Caracool Motion → Al entrar en pantalla</strong>. Viene sin poner.</p>
				<table class="cm-tabla">
					<thead><tr><th style="width:34%">Entrada</th><th>Qué hace</th></tr></thead>
					<tbody>
						<?php foreach ( self::entradas() as $entrada ) : ?>
							<tr><td><strong><?php echo esc_html( $entrada['etiqueta'] ); ?></strong></td><td><?php echo esc_html( $entrada['ayuda'] ); ?></td></tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<table class="cm-tabla" style="margin-top:14px">
					<thead><tr><th style="width:34%">Ajuste</th><th>Qué hace</th></tr></thead>
					<tbody>
						<tr><td><strong>Color de la cortina</strong></td><td>Solo con la cortina. Se elige entre los globales del Kit; vacío, el de Énfasis. Sobre un fondo de ese mismo color hay que elegir otro, o no se vería.</td></tr>
						<tr><td><strong>Velocidad de la entrada</strong></td><td>Lenta, media o rápida, como en los efectos de sección.</td></tr>
					</tbody>
				</table>
				<p class="cm-card-desc" style="margin-top:14px">Dentro de un contenedor con «Entrada escalonada», el titular entra en su turno y las piezas que van detrás esperan a que termine. Fuera de uno, entra solo al llegar a él. Dentro de una cortina lateral o de «La foto crece» no se anima.</p>
				<p class="cm-card-desc">El titular se parte en líneas en el navegador y solo mientras dura la entrada: al terminar vuelve a ser el encabezado de siempre, así que el HTML de la página no cambia. Con el movimiento reducido, o si el script no llegara a cargar, el titular se ve puesto. Sus archivos solo viajan a las páginas con algún encabezado que lo use.</p>
			</div>

		</section>
		<?php
	}
}

new Caracool_Motion_Texto();
