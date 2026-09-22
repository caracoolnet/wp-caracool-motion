<?php
/**
 * Caracool Motion — Módulo Fondo vivo
 * ─────────────────────────────────────────────────────────────────────
 * Archivo aparte y autorregistrado, como el resto de módulos.
 *
 * QUÉ HACE
 *  1. Añade en la pestaña ESTILO de los contenedores una sección
 *     «Caracool Motion · Fondo vivo»: un fondo de manchas de color que se
 *     mueven y se mezclan despacio. Es independiente del «Efecto de
 *     sección», así que un hero puede llevar a la vez la entrada escalonada
 *     y el fondo vivo.
 *  2. Inyecta data-cm-fondo y sus opciones en el contenedor.
 *  3. Imprime cm-fondo.css y cm-fondo.js SOLO en las páginas que lo usan.
 *     No depende de GSAP ni de ScrollTrigger: una página con fondo vivo y
 *     sin efectos de sección no carga ninguna librería de animación.
 *  4. Añade su pestaña de ajustes con un interruptor general.
 *
 * VARIANTES
 *  olas    — un lienzo pequeño (128 px de ancho) donde las manchas se
 *            funden y ondulan; el navegador lo estira y queda suave.
 *            ≈3 KB de JS, sin librerías.
 *  manchas — cinco manchas difuminadas en CSS que solo cambian de
 *            transform. Casi sin coste; se mueven, pero no se mezclan.
 *
 * COLORES
 *  Cinco controles de color nativos (admiten los globales del Kit). Los que
 *  se dejen vacíos se sacan del color Principal del Kit: el fondo en un tono
 *  muy claro, las manchas en tintes y sombras del mismo color. Con dejarlos
 *  todos vacíos ya sale un fondo de marca.
 *
 * LO QUE NO TOCA
 *  La imagen o el color de fondo que tenga el contenedor en Elementor se
 *  queda debajo y es lo primero que se pinta: el fondo vivo aparece encima
 *  cuando el navegador está libre. Si el visitante tiene reducido el
 *  movimiento, se pinta un fotograma quieto.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Caracool_Motion_Fondo {

	const OPTION_KEY = 'caracool_motion_fondo';

	/** Si algún contenedor de la página se ha pintado con fondo vivo. */
	private static $visto = false;

	public function __construct() {
		add_action( 'elementor/element/container/section_border/after_section_end', array( $this, 'controles_contenedor' ), 20, 2 );
		add_action( 'elementor/frontend/before_render', array( $this, 'inyectar_atributos' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'registrar_assets' ) );
		add_action( 'wp_footer', array( $this, 'imprimir_assets' ), 5 );

		add_action( 'caracool_motion_settings_panels', array( $this, 'panel' ) );
		add_action( 'admin_post_caracool_motion_save', array( $this, 'guardar' ), 5 );
	}

	public static function variantes() {
		return array(
			'olas'    => 'Olas (las manchas se funden y ondulan)',
			'manchas' => 'Manchas (se desplazan, más ligero)',
		);
	}

	/** Controles de color: clave => etiqueta. La variable CSS es --cm-fondo-<clave>. */
	public static function colores() {
		return array(
			'base' => 'Color de fondo',
			'c1'   => 'Mancha clara',
			'c2'   => 'Mancha media',
			'c3'   => 'Mancha intensa',
			'c4'   => 'Mancha de contraste',
		);
	}

	// ── 1) Controles en el panel de Elementor ───────────────────────────

	public function controles_contenedor( $element, $args ) {
		if ( ! class_exists( '\Elementor\Controls_Manager' ) ) {
			return;
		}

		$element->start_controls_section(
			'cm_fondo_seccion',
			array(
				'label' => 'Caracool Motion · Fondo vivo',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$element->add_control(
			'cm_fondo',
			array(
				'label'       => 'Fondo vivo',
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => array_merge( array( '' => '— Sin fondo vivo —' ), self::variantes() ),
				'default'     => '',
				'description' => 'Manchas de color que se mueven despacio detrás del contenido. Se combina con cualquier efecto de sección. Solo se ve en la web, no en el editor.',
			)
		);

		foreach ( self::colores() as $clave => $etiqueta ) {
			$element->add_control(
				'cm_fondo_' . $clave,
				array(
					'label'     => $etiqueta,
					'type'      => \Elementor\Controls_Manager::COLOR,
					'global'    => array( 'active' => true ),
					'selectors' => array( '{{WRAPPER}}' => '--cm-fondo-' . $clave . ': {{VALUE}};' ),
					'condition' => array( 'cm_fondo!' => '' ),
				)
			);
		}

		$element->add_control(
			'cm_fondo_ayuda_color',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => esc_html( 'Los colores vacíos salen del color Principal del Kit: el fondo en un tono muy claro y las manchas en tintes del mismo color.' ),
				'content_classes' => 'elementor-descriptor',
				'condition'       => array( 'cm_fondo!' => '' ),
			)
		);

		$element->add_control(
			'cm_fondo_velocidad',
			array(
				'label'     => 'Velocidad',
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'min'       => 0.2,
				'max'       => 3,
				'step'      => 0.1,
				'default'   => 1,
				'condition' => array( 'cm_fondo!' => '' ),
			)
		);

		$element->add_control(
			'cm_fondo_intensidad',
			array(
				'label'       => 'Intensidad',
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 0.3,
				'max'         => 1.6,
				'step'        => 0.1,
				'default'     => 1,
				'description' => 'Cuánto color ponen las manchas sobre el fondo. 1 es lo normal.',
				'condition'   => array( 'cm_fondo!' => '' ),
			)
		);

		$element->add_control(
			'cm_fondo_grano',
			array(
				'label'        => 'Grano',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Sí',
				'label_off'    => 'No',
				'return_value' => 'si',
				'default'      => 'si',
				'description'  => 'Una textura fina y fija encima, como de papel. Evita las bandas en los degradados.',
				'condition'    => array( 'cm_fondo!' => '' ),
			)
		);

		$element->end_controls_section();
	}

	// ── 2) Atributos en el HTML renderizado ─────────────────────────────

	public function inyectar_atributos( $element ) {
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_settings_for_display' ) ) {
			return;
		}
		if ( ! method_exists( $element, 'get_type' ) || 'container' !== $element->get_type() ) {
			return;
		}

		$ajustes  = $element->get_settings_for_display();
		$variante = isset( $ajustes['cm_fondo'] ) ? sanitize_key( $ajustes['cm_fondo'] ) : '';
		if ( ! isset( self::variantes()[ $variante ] ) ) {
			return;
		}

		$element->add_render_attribute( '_wrapper', 'data-cm-fondo', $variante );
		$element->add_render_attribute( '_wrapper', 'data-cm-fondo-vel', (string) self::acotar( isset( $ajustes['cm_fondo_velocidad'] ) ? $ajustes['cm_fondo_velocidad'] : null, 0.2, 3, 1 ) );
		$element->add_render_attribute( '_wrapper', 'data-cm-fondo-int', (string) self::acotar( isset( $ajustes['cm_fondo_intensidad'] ) ? $ajustes['cm_fondo_intensidad'] : null, 0.3, 1.6, 1 ) );
		$grano = isset( $ajustes['cm_fondo_grano'] ) ? (string) $ajustes['cm_fondo_grano'] : 'si';
		$element->add_render_attribute( '_wrapper', 'data-cm-fondo-grano', 'si' === $grano ? 'si' : 'no' );

		self::$visto = true;
	}

	// ── 3) Assets, solo en las páginas que lo usan ──────────────────────

	public function registrar_assets() {
		$base = CARACOOL_MOTION_URL . 'assets/';
		wp_register_style( 'cm-fondo', $base . 'cm-fondo.css', array(), caracool_motion_ver( 'cm-fondo.css' ) );
		wp_register_script( 'cm-fondo', $base . 'cm-fondo.js', array(), caracool_motion_ver( 'cm-fondo.js' ), true );
	}

	/**
	 * Mira lo pintado y, además, los datos guardados de cada documento de la
	 * página: con la caché de elementos de Elementor puesta, los contenedores
	 * no pasan por `inyectar_atributos()` y `$visto` se quedaría en falso.
	 */
	private static function pagina_lo_usa() {
		if ( self::$visto ) {
			return true;
		}
		foreach ( caracool_motion_documentos_de_la_pagina() as $id ) {
			$datos = get_post_meta( $id, '_elementor_data', true );
			if ( is_string( $datos ) && preg_match( '/"cm_fondo":"(olas|manchas)"/', $datos ) ) {
				return true;
			}
		}
		return false;
	}

	public function imprimir_assets() {
		if ( caracool_motion_en_editor() ) {
			return;
		}
		$c = self::get_settings();
		if ( 'si' !== $c['activo'] || ! self::pagina_lo_usa() ) {
			return;
		}
		wp_enqueue_style( 'cm-fondo' );
		wp_enqueue_script( 'cm-fondo' );
	}

	// ── 4) Ajustes del módulo ───────────────────────────────────────────

	public static function get_settings() {
		$guardado = get_option( self::OPTION_KEY, array() );
		return array(
			'activo' => ( isset( $guardado['activo'] ) && 'no' === $guardado['activo'] ) ? 'no' : 'si',
		);
	}

	/** El valor por defecto es SIEMPRE una constante, nunca lo último guardado. */
	private static function acotar( $valor, $min, $max, $defecto ) {
		if ( ! is_numeric( $valor ) ) {
			return $defecto;
		}
		$valor = (float) $valor;
		if ( $valor < $min || $valor > $max ) {
			return $defecto;
		}
		return $valor;
	}

	public function panel() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$c = self::get_settings();
		?>
		<section id="cm-fondo" class="cm-modulo" data-titulo="Fondo vivo">
			<div class="cm-card">
				<div class="cm-card-head">
					<div class="cm-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 15c3-4 6 2 9-2s6 2 9-2"/><path d="M3 9c3-4 6 2 9-2s6 2 9-2"/></svg></div>
					<h2>Fondo vivo</h2>
				</div>
				<p class="cm-card-desc">Manchas de color que se mueven y se mezclan despacio detrás del contenido de un contenedor. Se elige en Elementor, contenedor por contenedor: <strong>Estilo → Caracool Motion · Fondo vivo</strong>. Solo se carga en las páginas que lo usan (≈4 KB) y no necesita ninguna librería.</p>

				<div class="cm-campo">
					<label for="cm_fondo_activo">Permitir fondos vivos</label>
					<div>
						<label class="cm-sw"><input type="checkbox" name="cm_fondo[activo]" id="cm_fondo_activo" value="si" <?php checked( 'si', $c['activo'] ); ?>><span></span></label>
						<span class="cm-hint">Apagado, ninguna página lo carga aunque algún contenedor lo tenga elegido: se ve el fondo normal del contenedor. Sirve para descartarlo en toda la web de una vez.</span>
					</div>
				</div>

				<table class="cm-tabla">
					<thead><tr><th style="width:26%">Variante</th><th>Qué hace</th></tr></thead>
					<tbody>
						<tr><td><strong>Olas</strong><br><code>olas</code></td><td>Las manchas se funden y ondulan como tinta en agua. Se dibuja en un lienzo pequeño que el navegador estira, a 30 fotogramas por segundo.</td></tr>
						<tr><td><strong>Manchas</strong><br><code>manchas</code></td><td>Cinco manchas difuminadas que se desplazan y giran. Solo cambian de posición, así que el coste es casi nulo, pero no se mezclan entre sí.</td></tr>
					</tbody>
				</table>
				<p class="cm-card-desc" style="margin-top:14px">En las dos: la imagen o el color de fondo del contenedor se queda debajo y se pinta primero; la animación arranca cuando el navegador está libre, se para cuando el contenedor sale de pantalla o se cambia de pestaña, y se queda quieta si el visitante tiene reducido el movimiento.</p>
			</div>
		</section>
		<?php
	}

	public function guardar() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'caracool_motion_save' );
		$post = ( isset( $_POST['cm_fondo'] ) && is_array( $_POST['cm_fondo'] ) ) ? wp_unslash( $_POST['cm_fondo'] ) : array();
		update_option(
			self::OPTION_KEY,
			array(
				'activo' => ( isset( $post['activo'] ) && 'si' === $post['activo'] ) ? 'si' : 'no',
			)
		);
	}
}

new Caracool_Motion_Fondo();
