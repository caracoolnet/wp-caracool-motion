<?php
/**
 * Caracool Motion — Módulo Cabecera
 * ─────────────────────────────────────────────────────────────────────
 * Archivo aparte y autorregistrado, como el resto de módulos.
 *
 * QUÉ HACE
 *  Convierte la cabecera del Theme Builder de Elementor en una cabecera
 *  «inteligente»: fija y transparente sobre la primera pantalla, se
 *  esconde al bajar y reaparece al subir, ya con fondo sólido. Así las
 *  composiciones a pantalla completa quedan limpias y el botón de reservar
 *  sigue a un gesto de distancia.
 *
 *  1. Interruptor de sitio en el panel, con el umbral a partir del cual se
 *     esconde y el color global del Kit que toma al reaparecer.
 *  2. Imprime CSS y JS solo si el interruptor está encendido.
 *
 * QUÉ NO HACE
 *  No maqueta la cabecera: eso se hace en Elementor → Plantillas → Maquetador
 *  de temas, con contenedores y el Kit. El módulo solo la mueve y la pinta.
 *
 * POR QUÉ NO HAY COLORES AQUÍ
 *  El fondo al reaparecer se elige entre los colores globales del Kit (los
 *  de sistema y los personalizados) y viaja como variable CSS. El mismo
 *  módulo sirve para cualquier cliente.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Caracool_Motion_Cabecera {

	const OPTION_KEY = 'caracool_motion_cabecera';

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'registrar_assets' ) );
		add_action( 'wp_footer', array( $this, 'imprimir_assets' ), 5 );

		add_action( 'caracool_motion_settings_panels', array( $this, 'panel' ) );
		add_action( 'admin_post_caracool_motion_save', array( $this, 'guardar' ), 5 );
	}

	// ── Colores globales del Kit, para el desplegable ───────────────────

	/** Devuelve [ id => etiqueta ] con los colores de sistema y los personalizados del Kit activo. */
	public static function colores_globales() {
		$lista = array(
			'primary'   => 'Principal',
			'secondary' => 'Secundario',
			'text'      => 'Texto',
			'accent'    => 'Énfasis',
		);

		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return $lista;
		}

		$kits = \Elementor\Plugin::$instance->kits_manager;
		$kit  = ( $kits && method_exists( $kits, 'get_active_kit' ) ) ? $kits->get_active_kit() : null;
		if ( ! $kit || ! method_exists( $kit, 'get_settings' ) ) {
			return $lista;
		}

		foreach ( array( 'system_colors', 'custom_colors' ) as $grupo ) {
			$colores = $kit->get_settings( $grupo );
			if ( ! is_array( $colores ) ) {
				continue;
			}
			foreach ( $colores as $c ) {
				if ( empty( $c['_id'] ) ) {
					continue;
				}
				$id = sanitize_key( $c['_id'] );
				$lista[ $id ] = ! empty( $c['title'] ) ? sanitize_text_field( $c['title'] ) : $id;
			}
		}

		return $lista;
	}

	// ── Assets, solo si hacen falta ─────────────────────────────────────

	public function registrar_assets() {
		$base = CARACOOL_MOTION_URL . 'assets/';
		wp_register_style( 'cm-cabecera', $base . 'cm-cabecera.css', array(), caracool_motion_ver( 'cm-cabecera.css' ) );
		wp_register_script( 'cm-cabecera', $base . 'cm-cabecera.js', array(), caracool_motion_ver( 'cm-cabecera.js' ), true );
	}

	public function imprimir_assets() {
		if ( self::editor_elementor() ) {
			return;
		}

		$c = self::get_settings();
		if ( 'si' !== $c['activo'] ) {
			return;
		}
		// Si la página no tiene cabecera del Theme Builder, el JS no hace nada:
		// cuesta menos que adivinarlo aquí y equivocarse.

		wp_enqueue_style( 'cm-cabecera' );
		wp_enqueue_script( 'cm-cabecera' );

		wp_add_inline_script(
			'cm-cabecera',
			'window.CaracoolMotionCabecera = ' . wp_json_encode(
				array(
					'umbral' => (int) $c['umbral'],
					'fondo'  => '--e-global-color-' . $c['fondo'],
					'sombra' => ( 'si' === $c['sombra'] ),
					'linea'  => '--e-global-color-' . $c['linea'],
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

	// ── Ajustes del módulo ──────────────────────────────────────────────

	public static function get_settings() {
		$g = get_option( self::OPTION_KEY, array() );

		$fondo = isset( $g['fondo'] ) ? sanitize_key( $g['fondo'] ) : 'primary';
		if ( '' === $fondo ) {
			$fondo = 'primary';
		}

		$linea = isset( $g['linea'] ) ? sanitize_key( $g['linea'] ) : 'text';
		if ( '' === $linea ) {
			$linea = 'text';
		}

		return array(
			'activo' => ( isset( $g['activo'] ) && 'si' === $g['activo'] ) ? 'si' : 'no',
			'umbral' => isset( $g['umbral'] ) ? self::acotar( $g['umbral'], 40, 800, 120 ) : 120,
			'fondo'  => $fondo,
			'sombra' => ( isset( $g['sombra'] ) && 'si' === $g['sombra'] ) ? 'si' : 'no',
			'linea'  => $linea,
		);
	}

	/** El valor por defecto es SIEMPRE una constante, nunca lo último guardado. */
	private static function acotar( $valor, $min, $max, $defecto ) {
		if ( ! is_numeric( $valor ) ) {
			return $defecto;
		}
		$valor = (int) $valor;
		if ( $valor < $min || $valor > $max ) {
			return $defecto;
		}
		return $valor;
	}

	public function panel() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$c       = self::get_settings();
		$colores = self::colores_globales();
		?>
		<section id="cm-cabecera" class="cm-modulo" data-titulo="Cabecera">

			<div class="cm-card">
				<div class="cm-card-head">
					<div class="cm-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="5" rx="1.5"/><path d="M3 14h18M3 19h12"/></svg></div>
					<h2>Cabecera</h2>
				</div>
				<p class="cm-card-desc">La cabecera del Maquetador de temas se queda fija y transparente sobre la primera pantalla, <strong>se esconde al bajar y reaparece al subir</strong>, ya con fondo sólido. Las composiciones a pantalla completa quedan limpias y el botón de reservar sigue a un gesto.</p>

				<div class="cm-campo">
					<label for="cm_cabecera_activo">Cabecera inteligente</label>
					<div>
						<label class="cm-sw"><input type="checkbox" name="cm_cabecera[activo]" id="cm_cabecera_activo" value="si" <?php checked( 'si', $c['activo'] ); ?>><span></span></label>
						<span class="cm-hint">Necesita una cabecera hecha en Elementor → Plantillas → Maquetador de temas. Sin ella no hace nada. Al activarla, la cabecera deja de ocupar sitio arriba: las páginas sin foto a pantalla completa necesitan su propio margen superior.</span>
					</div>
				</div>

				<div class="cm-campo">
					<label for="cm_cabecera_umbral">Se esconde a partir de</label>
					<div>
						<input type="number" name="cm_cabecera[umbral]" id="cm_cabecera_umbral" step="10" min="40" max="800" value="<?php echo esc_attr( $c['umbral'] ); ?>">
						<span class="cm-hint">Píxeles de scroll. Por encima de este punto la cabecera se esconde al bajar y toma el fondo sólido al reaparecer; por debajo vuelve a ser transparente. Entre 40 y 800. Recomendado: 120.</span>
					</div>
				</div>

				<div class="cm-campo">
					<label for="cm_cabecera_fondo">Fondo al reaparecer</label>
					<div>
						<select name="cm_cabecera[fondo]" id="cm_cabecera_fondo">
							<?php foreach ( $colores as $id => $etiqueta ) : ?>
								<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $id, $c['fondo'] ); ?>><?php echo esc_html( $etiqueta ); ?></option>
							<?php endforeach; ?>
						</select>
						<span class="cm-hint">Un color global del Kit de Elementor, de sistema o personalizado. Si cambias el color en el Kit, la cabecera cambia sola.</span>
					</div>
				</div>

				<div class="cm-campo">
					<label for="cm_cabecera_sombra">Línea de separación</label>
					<div>
						<label class="cm-sw"><input type="checkbox" name="cm_cabecera[sombra]" id="cm_cabecera_sombra" value="si" <?php checked( 'si', $c['sombra'] ); ?>><span></span></label>
						<span class="cm-hint">Un hilo de 1 px bajo la cabecera mientras está a la vista con fondo sólido. Escondida, no se ve.</span>
					</div>
				</div>

				<div class="cm-campo">
					<label for="cm_cabecera_linea">Color de la línea</label>
					<div>
						<select name="cm_cabecera[linea]" id="cm_cabecera_linea">
							<?php foreach ( $colores as $id => $etiqueta ) : ?>
								<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $id, $c['linea'] ); ?>><?php echo esc_html( $etiqueta ); ?></option>
							<?php endforeach; ?>
						</select>
						<span class="cm-hint">Un color global del Kit. Si el Kit tiene un color para líneas o bordes, es el que toca.</span>
					</div>
				</div>
			</div>

		</section>
		<?php
	}

	public function guardar() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'caracool_motion_save' );

		$post    = ( isset( $_POST['cm_cabecera'] ) && is_array( $_POST['cm_cabecera'] ) ) ? wp_unslash( $_POST['cm_cabecera'] ) : array();
		$colores = self::colores_globales();

		$fondo = isset( $post['fondo'] ) ? sanitize_key( $post['fondo'] ) : 'primary';
		if ( ! isset( $colores[ $fondo ] ) ) {
			$fondo = 'primary';
		}
		$linea = isset( $post['linea'] ) ? sanitize_key( $post['linea'] ) : 'text';
		if ( ! isset( $colores[ $linea ] ) ) {
			$linea = 'text';
		}

		update_option(
			self::OPTION_KEY,
			array(
				'activo' => ( isset( $post['activo'] ) && 'si' === $post['activo'] ) ? 'si' : 'no',
				'umbral' => self::acotar( isset( $post['umbral'] ) ? $post['umbral'] : null, 40, 800, 120 ),
				'fondo'  => $fondo,
				'sombra' => ( isset( $post['sombra'] ) && 'si' === $post['sombra'] ) ? 'si' : 'no',
				'linea'  => $linea,
			)
		);
	}
}

new Caracool_Motion_Cabecera();
