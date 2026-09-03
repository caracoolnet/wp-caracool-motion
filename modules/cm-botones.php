<?php
/**
 * Caracool Motion — Módulo Botones
 * ─────────────────────────────────────────────────────────────────────
 * Archivo aparte y autorregistrado, como el resto de módulos.
 *
 * QUÉ HACE
 *  1. Añade un interruptor de sitio en el panel de Caracool Motion:
 *     animación al pasar el cursor por los botones, con su variante y
 *     su velocidad. Se enciende una vez y vale para toda la web.
 *  2. Añade en cada botón de Elementor un desplegable para dejarlo
 *     quieto, por si algún caso concreto lo pide.
 *  3. Imprime CSS y JS solo si el interruptor está encendido.
 *
 * POR QUÉ NO HAY COLORES AQUÍ
 *  El efecto invierte los dos colores que el botón ya tiene puestos en
 *  Elementor, leídos en el navegador con getComputedStyle. El plugin no
 *  decide ningún color: el mismo módulo sirve para cualquier cliente.
 *
 * CÓMO SE AMPLÍA CON UNA VARIANTE NUEVA
 *  No hay que tocar este archivo:
 *
 *      add_filter( 'caracool_motion_variantes_boton', function ( $v ) {
 *          $v['diagonal'] = array(
 *              'etiqueta' => 'En diagonal',
 *              'ayuda'    => 'La banda entra inclinada desde abajo.',
 *          );
 *          return $v;
 *      } );
 *
 *  y en el JavaScript propio, atender a esa clave. Una variante declarada
 *  en PHP sin comportamiento en JS cae en la de serie y no rompe nada.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Caracool_Motion_Botones {

	const OPTION_KEY = 'caracool_motion_botones';

	public function __construct() {
		add_action( 'elementor/element/button/section_style/after_section_end', array( $this, 'controles_boton' ), 10, 2 );
		add_action( 'elementor/frontend/before_render', array( $this, 'inyectar_atributos' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'registrar_assets' ) );
		add_action( 'wp_footer', array( $this, 'imprimir_assets' ), 5 );

		add_action( 'caracool_motion_settings_panels', array( $this, 'panel' ) );
		add_action( 'admin_post_caracool_motion_save', array( $this, 'guardar' ), 5 );
	}

	// ── Catálogo de variantes (ampliable por filtro) ────────────────────

	public static function variantes() {
		return apply_filters(
			'caracool_motion_variantes_boton',
			array(
				'paso'   => array(
					'etiqueta' => 'De paso',
					'ayuda'    => 'El color entra por la izquierda y, al salir el cursor, sigue y se va por la derecha. No rebobina nunca, así que aguanta bien el ratón rápido sobre una fila de botones. Es la recomendada.',
				),
				'ida'    => array(
					'etiqueta' => 'Ida y vuelta',
					'ayuda'    => 'El color entra por la izquierda y al salir vuelve por donde vino. La más fácil de leer, algo nerviosa si hay varios botones juntos.',
				),
				'cursor' => array(
					'etiqueta' => 'Sigue al cursor',
					'ayuda'    => 'El color entra por el lado por el que llega el cursor y sale por el lado por el que se va. La más viva; sienta bien a un botón grande y solo.',
				),
			)
		);
	}

	public static function variante_defecto() {
		return 'paso';
	}

	// ── 1) Control en cada botón de Elementor ───────────────────────────

	public function controles_boton( $element, $args ) {
		if ( ! class_exists( '\Elementor\Controls_Manager' ) ) {
			return;
		}

		$element->start_controls_section(
			'cm_boton_seccion',
			array(
				'label' => 'Caracool Motion',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$element->add_control(
			'cm_boton',
			array(
				'label'       => 'Animación al pasar el cursor',
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => array(
					''   => 'La del sitio',
					'no' => 'Sin animación',
				),
				'default'     => '',
				'description' => 'El efecto se enciende para toda la web desde Caracool Motion. Aquí solo se puede dejar quieto este botón concreto.',
			)
		);

		$element->end_controls_section();
	}

	// ── 2) Atributo en el HTML renderizado ──────────────────────────────

	public function inyectar_atributos( $element ) {
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_settings_for_display' ) ) {
			return;
		}
		if ( ! method_exists( $element, 'get_name' ) || 'button' !== $element->get_name() ) {
			return;
		}

		$ajustes = $element->get_settings_for_display();
		$valor   = isset( $ajustes['cm_boton'] ) ? sanitize_key( $ajustes['cm_boton'] ) : '';

		if ( 'no' === $valor ) {
			$element->add_render_attribute( '_wrapper', 'data-cm-boton', 'no' );
		}
	}

	// ── 3) Assets, solo si el interruptor está encendido ────────────────

	public function registrar_assets() {
		$base = CARACOOL_MOTION_URL . 'assets/';
		wp_register_style( 'cm-botones', $base . 'cm-botones.css', array(), caracool_motion_ver( 'cm-botones.css' ) );
		wp_register_script( 'cm-botones', $base . 'cm-botones.js', array(), caracool_motion_ver( 'cm-botones.js' ), true );
	}

	public function imprimir_assets() {
		if ( self::editor_elementor() ) {
			return;
		}

		$c = self::get_settings();
		if ( 'si' !== $c['activo'] ) {
			return;
		}

		wp_enqueue_style( 'cm-botones' );
		wp_enqueue_script( 'cm-botones' );

		wp_add_inline_script(
			'cm-botones',
			'window.CaracoolMotionBotones = ' . wp_json_encode(
				array(
					'variante'  => $c['variante'],
					'velocidad' => (float) $c['velocidad'],
				)
			) . ';',
			'before'
		);
	}

	private static function editor_elementor() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}
		$e = \Elementor\Plugin::$instance;
		if ( isset( $e->preview ) && method_exists( $e->preview, 'is_preview_mode' ) && $e->preview->is_preview_mode() ) {
			return true;
		}
		if ( isset( $e->editor ) && method_exists( $e->editor, 'is_edit_mode' ) && $e->editor->is_edit_mode() ) {
			return true;
		}
		return false;
	}

	// ── 4) Ajustes del módulo ───────────────────────────────────────────

	public static function get_settings() {
		$guardado  = get_option( self::OPTION_KEY, array() );
		$variantes = self::variantes();

		$variante = isset( $guardado['variante'] ) ? sanitize_key( $guardado['variante'] ) : self::variante_defecto();
		if ( ! isset( $variantes[ $variante ] ) ) {
			$variante = self::variante_defecto();
		}

		return array(
			'activo'    => ( isset( $guardado['activo'] ) && 'si' === $guardado['activo'] ) ? 'si' : 'no',
			'variante'  => $variante,
			'velocidad' => isset( $guardado['velocidad'] ) ? self::acotar( $guardado['velocidad'], 0.2, 1.4, 0.5 ) : 0.5,
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
		<section id="cm-botones" class="cm-modulo" data-titulo="Botones">

			<div class="cm-card">
				<div class="cm-card-head">
					<div class="cm-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="8" width="19" height="8" rx="4"/><path d="M9 8v8"/></svg></div>
					<h2>Botones</h2>
				</div>
				<p class="cm-card-desc">Al pasar el cursor, una banda de color barre el botón y el texto cambia de color letra a letra conforme el borde lo cruza. <strong>No lleva colores propios</strong>: invierte los dos que cada botón ya tiene puestos en Elementor, así que si cambias el Kit el efecto se ajusta solo.</p>

				<div class="cm-campo">
					<label for="cm_botones_activo">Animar los botones</label>
					<div>
						<label class="cm-sw"><input type="checkbox" name="cm_botones[activo]" id="cm_botones_activo" value="si" <?php checked( 'si', $c['activo'] ); ?>><span></span></label>
						<span class="cm-hint">Se aplica a todos los botones de la web. Si algún botón concreto debe quedarse quieto, se marca en Elementor: <strong>Estilo → Caracool Motion</strong>.</span>
					</div>
				</div>

				<div class="cm-campo">
					<label for="cm_botones_variante">Cómo entra el color</label>
					<div>
						<select name="cm_botones[variante]" id="cm_botones_variante">
							<?php foreach ( self::variantes() as $clave => $v ) : ?>
								<option value="<?php echo esc_attr( $clave ); ?>" <?php selected( $clave, $c['variante'] ); ?>><?php echo esc_html( $v['etiqueta'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php
						$todas = self::variantes();
						$ayuda = isset( $todas[ $c['variante'] ]['ayuda'] ) ? $todas[ $c['variante'] ]['ayuda'] : '';
						?>
						<span class="cm-hint"><?php echo esc_html( $ayuda ); ?></span>
					</div>
				</div>

				<div class="cm-campo">
					<label for="cm_botones_velocidad">Velocidad</label>
					<div>
						<input type="number" name="cm_botones[velocidad]" id="cm_botones_velocidad" step="0.05" min="0.2" max="1.4" value="<?php echo esc_attr( $c['velocidad'] ); ?>">
						<span class="cm-hint">Segundos que tarda la banda en cruzar. Entre 0,2 y 1,4. Por debajo de 0,35 se pierde el detalle del texto; por encima de 0,7 se hace lento al pinchar. Recomendado: 0,5.</span>
					</div>
				</div>
			</div>

			<div class="cm-card">
				<div class="cm-card-head">
					<div class="cm-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 3v18"/><path d="M3 12h18"/></svg></div>
					<h2>De dónde saca los colores</h2>
				</div>
				<table class="cm-tabla">
					<thead><tr><th style="width:34%">Tipo de botón</th><th>Qué hace al pasar el cursor</th></tr></thead>
					<tbody>
						<tr>
							<td><strong>De línea</strong><br><code>fondo transparente</code></td>
							<td>Se rellena con el color de su borde, y el texto pasa al color de la sección que tiene detrás.</td>
						</tr>
						<tr>
							<td><strong>Con fondo</strong><br><code>fondo de color</code></td>
							<td>Se rellena con su propio color de texto, y el texto pasa al color que tenía de fondo. No se vacía: el botón principal nunca pierde peso justo cuando lo señalas.</td>
						</tr>
					</tbody>
				</table>
				<p class="cm-card-desc">El efecto se desactiva solo si el visitante tiene el movimiento reducido activado en su sistema, y si los dos colores de un botón salen iguales ese botón se queda quieto en lugar de mostrar un barrido invisible.</p>
			</div>

		</section>
		<?php
	}

	public function guardar() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'caracool_motion_save' );

		$post      = ( isset( $_POST['cm_botones'] ) && is_array( $_POST['cm_botones'] ) ) ? wp_unslash( $_POST['cm_botones'] ) : array();
		$variantes = self::variantes();

		$variante = isset( $post['variante'] ) ? sanitize_key( $post['variante'] ) : self::variante_defecto();
		if ( ! isset( $variantes[ $variante ] ) ) {
			$variante = self::variante_defecto();
		}

		update_option(
			self::OPTION_KEY,
			array(
				'activo'    => ( isset( $post['activo'] ) && 'si' === $post['activo'] ) ? 'si' : 'no',
				'variante'  => $variante,
				'velocidad' => self::acotar( isset( $post['velocidad'] ) ? $post['velocidad'] : null, 0.2, 1.4, 0.5 ),
			)
		);
	}
}

new Caracool_Motion_Botones();
