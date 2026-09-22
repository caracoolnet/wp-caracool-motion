<?php
/**
 * Caracool Motion — Módulo Pie: telón y mirada
 * ─────────────────────────────────────────────────────────────────────
 * Archivo aparte y autorregistrado, como el resto de módulos.
 *
 * QUÉ HACE
 *  1. Añade en la pestaña ESTILO de los contenedores una sección
 *     «Caracool Motion · Pie y mirada» con dos piezas independientes:
 *
 *     TELÓN — para el contenedor raíz de la plantilla de pie. La página
 *     termina con las esquinas redondeadas y una sombra, y al llegar al
 *     final sube como un telón y deja ver el pie, que espera quieto debajo.
 *     Es CSS (position: sticky); el JavaScript solo decide si cabe: se
 *     apaga por debajo del ancho elegido y cuando el pie es más alto que la
 *     pantalla, porque entonces taparía su propia parte de arriba.
 *
 *     MIRADA — para cualquier contenedor que tenga dentro un SVG en línea
 *     (un widget HTML con el dibujo). El elemento marcado con la clase
 *     `cm-pupila` se desplaza hacia el cursor y el grupo `cm-parpado`
 *     (o el SVG entero, si no hay grupo) parpadea de vez en cuando. En
 *     pantallas táctiles la pupila sigue al scroll.
 *
 *  2. Inyecta data-cm-telon / data-cm-mirada y sus opciones en el contenedor.
 *  3. Imprime cm-pie.css y cm-pie.js SOLO en las páginas que lo usan
 *     (≈3 KB entre los dos, sin librerías).
 *  4. Añade su pestaña de ajustes con un interruptor general.
 *
 * Con movimiento reducido: el telón se mantiene (no es animación, es la
 * página deslizándose con el scroll normal); la mirada no se mueve ni
 * parpadea.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Caracool_Motion_Pie {

	const OPTION_KEY = 'caracool_motion_pie';

	private static $visto = false;

	public function __construct() {
		add_action( 'elementor/element/container/section_border/after_section_end', array( $this, 'controles_contenedor' ), 25, 2 );
		add_action( 'elementor/frontend/before_render', array( $this, 'inyectar_atributos' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'registrar_assets' ) );
		add_action( 'wp_footer', array( $this, 'imprimir_assets' ), 5 );

		add_action( 'caracool_motion_settings_panels', array( $this, 'panel' ) );
		add_action( 'admin_post_caracool_motion_save', array( $this, 'guardar' ), 5 );
	}

	// ── 1) Controles en el panel de Elementor ───────────────────────────

	public function controles_contenedor( $element, $args ) {
		if ( ! class_exists( '\Elementor\Controls_Manager' ) ) {
			return;
		}

		$element->start_controls_section(
			'cm_pie_seccion',
			array(
				'label' => 'Caracool Motion · Pie y mirada',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$element->add_control(
			'cm_telon',
			array(
				'label'        => 'Telón',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Sí',
				'label_off'    => 'No',
				'return_value' => 'si',
				'default'      => '',
				'description'  => 'Solo en el contenedor raíz de la plantilla de pie. La página sube como un telón y deja ver el pie quieto debajo. Solo se ve en la web.',
			)
		);

		$element->add_control(
			'cm_telon_radio',
			array(
				'label'     => 'Esquinas del telón (px)',
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'min'       => 0,
				'max'       => 120,
				'step'      => 1,
				'default'   => 44,
				'condition' => array( 'cm_telon' => 'si' ),
			)
		);

		$element->add_control(
			'cm_telon_desde',
			array(
				'label'       => 'Activo a partir de (px de ancho)',
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 2000,
				'step'        => 1,
				'default'     => 1025,
				'description' => 'Por debajo, el pie aparece al bajar, como siempre. En cualquier ancho se apaga solo si el pie no cabe en la pantalla.',
				'condition'   => array( 'cm_telon' => 'si' ),
			)
		);

		$element->add_control(
			'cm_mirada',
			array(
				'label'        => 'Mirada',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Sí',
				'label_off'    => 'No',
				'return_value' => 'si',
				'default'      => '',
				'separator'    => 'before',
				'description'  => 'Para un dibujo SVG en línea dentro de este contenedor: el elemento con la clase cm-pupila sigue al cursor y el grupo cm-parpado parpadea.',
			)
		);

		$element->add_control(
			'cm_mirada_recorrido',
			array(
				'label'       => 'Recorrido de la pupila (%)',
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 1,
				'max'         => 20,
				'step'        => 0.5,
				'default'     => 5,
				'description' => 'Cuánto se mueve, en porcentaje del ancho del dibujo.',
				'condition'   => array( 'cm_mirada' => 'si' ),
			)
		);

		$element->add_control(
			'cm_mirada_parpadeo',
			array(
				'label'        => 'Parpadeo',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Sí',
				'label_off'    => 'No',
				'return_value' => 'si',
				'default'      => 'si',
				'condition'    => array( 'cm_mirada' => 'si' ),
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
		$a = $element->get_settings_for_display();

		if ( isset( $a['cm_telon'] ) && 'si' === $a['cm_telon'] ) {
			$element->add_render_attribute( '_wrapper', 'data-cm-telon', 'si' );
			$element->add_render_attribute( '_wrapper', 'data-cm-telon-radio', (string) self::acotar( isset( $a['cm_telon_radio'] ) ? $a['cm_telon_radio'] : null, 0, 120, 44 ) );
			$element->add_render_attribute( '_wrapper', 'data-cm-telon-desde', (string) self::acotar( isset( $a['cm_telon_desde'] ) ? $a['cm_telon_desde'] : null, 0, 2000, 1025 ) );
			self::$visto = true;
		}
		if ( isset( $a['cm_mirada'] ) && 'si' === $a['cm_mirada'] ) {
			$element->add_render_attribute( '_wrapper', 'data-cm-mirada', 'si' );
			$element->add_render_attribute( '_wrapper', 'data-cm-mirada-recorrido', (string) self::acotar( isset( $a['cm_mirada_recorrido'] ) ? $a['cm_mirada_recorrido'] : null, 1, 20, 5 ) );
			$parpadeo = isset( $a['cm_mirada_parpadeo'] ) ? (string) $a['cm_mirada_parpadeo'] : 'si';
			$element->add_render_attribute( '_wrapper', 'data-cm-mirada-parpadeo', 'si' === $parpadeo ? 'si' : 'no' );
			self::$visto = true;
		}
	}

	// ── 3) Assets, solo en las páginas que lo usan ──────────────────────

	public function registrar_assets() {
		$base = CARACOOL_MOTION_URL . 'assets/';
		wp_register_style( 'cm-pie', $base . 'cm-pie.css', array(), caracool_motion_ver( 'cm-pie.css' ) );
		wp_register_script( 'cm-pie', $base . 'cm-pie.js', array(), caracool_motion_ver( 'cm-pie.js' ), true );
	}

	/** Lo pintado o, con la caché de elementos puesta, los datos guardados. */
	private static function pagina_lo_usa() {
		if ( self::$visto ) {
			return true;
		}
		foreach ( caracool_motion_documentos_de_la_pagina() as $id ) {
			$datos = get_post_meta( $id, '_elementor_data', true );
			if ( is_string( $datos ) && preg_match( '/"cm_(telon|mirada)":"si"/', $datos ) ) {
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
		wp_enqueue_style( 'cm-pie' );
		wp_enqueue_script( 'cm-pie' );
	}

	// ── 4) Ajustes del módulo ───────────────────────────────────────────

	public static function get_settings() {
		$guardado = get_option( self::OPTION_KEY, array() );
		return array(
			'activo' => ( isset( $guardado['activo'] ) && 'no' === $guardado['activo'] ) ? 'no' : 'si',
		);
	}

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
		<section id="cm-pie" class="cm-modulo" data-titulo="Pie y mirada">
			<div class="cm-card">
				<div class="cm-card-head">
					<div class="cm-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"/><circle cx="12" cy="12" r="2.6"/></svg></div>
					<h2>Pie y mirada</h2>
				</div>
				<p class="cm-card-desc">Dos piezas para el final de la página. Se eligen en Elementor, en el contenedor: <strong>Estilo → Caracool Motion · Pie y mirada</strong>. Solo se cargan en las páginas que las usan (≈3 KB) y no necesitan ninguna librería.</p>

				<div class="cm-campo">
					<label for="cm_pie_activo">Permitir telón y mirada</label>
					<div>
						<label class="cm-sw"><input type="checkbox" name="cm_pie[activo]" id="cm_pie_activo" value="si" <?php checked( 'si', $c['activo'] ); ?>><span></span></label>
						<span class="cm-hint">Apagado, el pie aparece al bajar como siempre y los dibujos se quedan quietos, aunque estén elegidos en Elementor.</span>
					</div>
				</div>

				<table class="cm-tabla">
					<thead><tr><th style="width:26%">Pieza</th><th>Cómo se monta</th></tr></thead>
					<tbody>
						<tr><td><strong>Telón</strong><br><code>cm_telon</code></td><td>En el contenedor raíz de la plantilla de pie. La página termina con esquinas redondeadas y sombra y sube dejando ver el pie. Se apaga por debajo del ancho elegido y cuando el pie no cabe en la pantalla.</td></tr>
						<tr><td><strong>Mirada</strong><br><code>cm_mirada</code></td><td>En el contenedor que tenga dentro un SVG en línea. En el SVG, la clase <code>cm-pupila</code> marca lo que sigue al cursor y <code>cm-parpado</code> lo que parpadea (si no hay, parpadea el SVG entero).</td></tr>
					</tbody>
				</table>
			</div>
		</section>
		<?php
	}

	public function guardar() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'caracool_motion_save' );
		$post = ( isset( $_POST['cm_pie'] ) && is_array( $_POST['cm_pie'] ) ) ? wp_unslash( $_POST['cm_pie'] ) : array();
		update_option(
			self::OPTION_KEY,
			array(
				'activo' => ( isset( $post['activo'] ) && 'si' === $post['activo'] ) ? 'si' : 'no',
			)
		);
	}
}

new Caracool_Motion_Pie();
