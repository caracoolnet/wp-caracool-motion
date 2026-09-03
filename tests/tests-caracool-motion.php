<?php
/**
 * Harness de pruebas para Caracool Motion — sin WordPress instalado.
 * Mocks mínimos de las funciones que usa el plugin, más comprobaciones de
 * valores por defecto, guardado, recorte de rangos y callbacks existentes.
 */

$GLOBALS['opciones']  = array();
$GLOBALS['acciones']  = array();
$GLOBALS['filtros']   = array();
$GLOBALS['fallos']    = array();
$GLOBALS['ok']        = 0;

define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );

function add_action( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['acciones'][] = array( $h, $cb ); }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['filtros'][] = array( $h, $cb ); }
function do_action( $h ) {}
function apply_filters( $h, $v ) { return $v; }
function get_option( $k, $d = false ) { return isset( $GLOBALS['opciones'][ $k ] ) ? $GLOBALS['opciones'][ $k ] : $d; }
function update_option( $k, $v ) { $GLOBALS['opciones'][ $k ] = $v; return true; }
function get_site_transient( $k ) { return false; }
function set_site_transient( $k, $v, $t = 0 ) { return true; }
function current_user_can( $c ) { return true; }
function check_admin_referer( $a ) { return true; }
function wp_unslash( $v ) { return $v; }
function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $v ) ); }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_url( $v ) { return $v; }
function checked( $a, $b, $e = true ) { return $a === $b ? 'checked' : ''; }
function selected( $a, $b, $e = true ) { return $a === $b ? 'selected' : ''; }
function admin_url( $p = '' ) { return 'https://ejemplo.test/wp-admin/' . $p; }
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function plugin_dir_url( $f ) { return 'https://ejemplo.test/wp-content/plugins/caracool-motion/'; }
function plugin_basename( $f ) { return 'caracool-motion/caracool-motion.php'; }
function add_menu_page() {}
function wp_nonce_field( $a ) {}
function wp_safe_redirect( $u ) {}
function wp_die( $m ) {}
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_register_style() {} function wp_register_script() {}
function wp_enqueue_style() {} function wp_enqueue_script() {}
function wp_add_inline_script() {}
function wp_remote_get( $u, $a = array() ) { return new WP_Error(); }
function wp_remote_retrieve_response_code( $r ) { return 0; }
function wp_remote_retrieve_body( $r ) { return ''; }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function get_bloginfo( $x ) { return '6.7'; }
class WP_Error {}
function is_admin() { return false; }
function wp_doing_ajax() { return false; }
function is_feed() { return false; }
function is_embed() { return false; }
function is_singular() { return false; }
function is_front_page() { return true; }
function get_queried_object_id() { return 0; }
function get_post_meta( $id, $k, $s = false ) { return array(); }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_hex_color( $c ) { return preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', (string) $c ) ? $c : ''; }
function get_attached_file( $id ) { return ''; }
function get_post_mime_type( $id ) { return ''; }
function wp_get_attachment_url( $id ) { return ''; }
function wp_enqueue_media() {}
function get_pages( $a = array() ) { return array(); }

require_once dirname( __DIR__ ) . '/caracool-motion.php';

function comprueba( $titulo, $condicion, $detalle = '' ) {
	if ( $condicion ) { $GLOBALS['ok']++; echo "  ok  · $titulo\n"; }
	else { $GLOBALS['fallos'][] = $titulo . ( $detalle ? " ($detalle)" : '' ); echo "  FALLO · $titulo $detalle\n"; }
}

echo "\n=== Valores por defecto ===\n";
$d = Caracool_Motion_Scroll::get_settings();
comprueba( 'inercia activada de fábrica', 'si' === $d['inercia'] );
comprueba( 'duración por defecto 1.25', 1.25 === (float) $d['duracion'], 'era ' . $d['duracion'] );
comprueba( 'táctil desactivado de fábrica', 'no' === $d['tactil'] );
comprueba( 'puntos desactivados de fábrica', 'no' === $d['puntos'] );

echo "\n=== Guardado normal ===\n";
$_POST = array( 'cm_scroll' => array( 'inercia' => 'si', 'duracion' => '1.6', 'puntos' => 'si' ) );
$m = new Caracool_Motion_Scroll();
$m->guardar();
$g = Caracool_Motion_Scroll::get_settings();
comprueba( 'guarda la duración enviada', 1.6 === (float) $g['duracion'], 'era ' . $g['duracion'] );
comprueba( 'guarda los puntos activados', 'si' === $g['puntos'] );
comprueba( 'checkbox ausente se guarda como no', 'no' === $g['tactil'] );

echo "\n=== Valores fuera de rango y basura ===\n";
foreach ( array( '99', '-4', 'abc', '' ) as $malo ) {
	$_POST = array( 'cm_scroll' => array( 'duracion' => $malo ) );
	$m->guardar();
	$g = Caracool_Motion_Scroll::get_settings();
	comprueba( "duración '$malo' cae al valor por defecto fijo", 1.25 === (float) $g['duracion'], 'era ' . $g['duracion'] );
}

echo "\n=== Catálogo de efectos ===\n";
$fx = Caracool_Motion_Scroll::efectos();
comprueba( 'los seis efectos de serie están', 6 === count( $fx ) && isset( $fx['cortina'], $fx['crece'], $fx['entrada'], $fx['parallax'], $fx['gira'], $fx['marca'] ) );
$op = Caracool_Motion_Scroll::opciones();
foreach ( $fx as $clave => $def ) {
	$todas = true;
	foreach ( $def['opciones'] as $o ) { if ( ! isset( $op[ $o ] ) ) { $todas = false; } }
	comprueba( "el efecto '$clave' solo pide opciones que existen", $todas );
	comprueba( "el efecto '$clave' tiene etiqueta", ! empty( $def['etiqueta'] ) );
}
foreach ( $op as $clave => $def ) {
	if ( isset( $def['tipo'] ) && 'color' === $def['tipo'] ) {
		comprueba( "la opción de color '$clave' declara su variable CSS", ! empty( $def['variable'] ) && 0 === strpos( $def['variable'], '--cm-' ) );
		continue;
	}
	if ( isset( $def['tipo'] ) && 'interruptor' === $def['tipo'] ) {
		comprueba( "el interruptor '$clave' arranca en sí o no", in_array( $def['defecto'], array( 'si', 'no' ), true ) );
		continue;
	}
	if ( isset( $def['tipo'] ) && 'numero' === $def['tipo'] ) {
		comprueba( "la opción numérica '$clave' tiene un defecto dentro de su rango", $def['defecto'] >= $def['min'] && $def['defecto'] <= $def['max'] );
		continue;
	}
	comprueba( "la opción '$clave' tiene un defecto válido", isset( $def['valores'][ $def['defecto'] ] ) );
}
comprueba( 'velo y línea usan el selector de color nativo de Elementor', 'color' === $op['velo']['tipo'] && 'color' === $op['barra_color']['tipo'] && 'interruptor' === $op['barra']['tipo'] && 'si' === $op['barra']['defecto'] );

echo "\n=== Botones · valores por defecto ===\n";
$b = Caracool_Motion_Botones::get_settings();
comprueba( 'apagado de fábrica', 'no' === $b['activo'] );
comprueba( 'variante por defecto: de paso', 'paso' === $b['variante'], 'era ' . $b['variante'] );
comprueba( 'velocidad por defecto 0.5', 0.5 === (float) $b['velocidad'], 'era ' . $b['velocidad'] );

echo "\n=== Botones · guardado ===\n";
$mb = new Caracool_Motion_Botones();
$_POST = array( 'cm_botones' => array( 'activo' => 'si', 'variante' => 'cursor', 'velocidad' => '0.8' ) );
$mb->guardar();
$b = Caracool_Motion_Botones::get_settings();
comprueba( 'guarda encendido', 'si' === $b['activo'] );
comprueba( 'guarda la variante', 'cursor' === $b['variante'], 'era ' . $b['variante'] );
comprueba( 'guarda la velocidad', 0.8 === (float) $b['velocidad'], 'era ' . $b['velocidad'] );

$_POST = array( 'cm_botones' => array( 'variante' => 'cursor' ) );
$mb->guardar();
$b = Caracool_Motion_Botones::get_settings();
comprueba( 'casilla ausente apaga el módulo', 'no' === $b['activo'] );

echo "\n=== Botones · basura ===\n";
foreach ( array( 'inventada', '', 'DROP TABLE' ) as $malo ) {
	$_POST = array( 'cm_botones' => array( 'variante' => $malo ) );
	$mb->guardar();
	$b = Caracool_Motion_Botones::get_settings();
	comprueba( "variante '$malo' cae en la de serie", 'paso' === $b['variante'], 'era ' . $b['variante'] );
}
foreach ( array( '9', '-1', 'abc', '' ) as $malo ) {
	$_POST = array( 'cm_botones' => array( 'velocidad' => $malo ) );
	$mb->guardar();
	$b = Caracool_Motion_Botones::get_settings();
	comprueba( "velocidad '$malo' cae en 0.5", 0.5 === (float) $b['velocidad'], 'era ' . $b['velocidad'] );
}

echo "\n=== Botones · catálogo de variantes ===\n";
$vs = Caracool_Motion_Botones::variantes();
comprueba( 'las tres variantes están', 3 === count( $vs ) && isset( $vs['paso'], $vs['ida'], $vs['cursor'] ) );
comprueba( 'la variante por defecto existe en el catálogo', isset( $vs[ Caracool_Motion_Botones::variante_defecto() ] ) );
foreach ( $vs as $k => $v ) {
	comprueba( "la variante '$k' tiene etiqueta y ayuda", ! empty( $v['etiqueta'] ) && ! empty( $v['ayuda'] ) );
}

echo "\n=== Ningún módulo pisa la opción de otro ===\n";
comprueba( 'claves de opción distintas', 4 === count( array_unique( array( Caracool_Motion_Scroll::OPTION_KEY, Caracool_Motion_Botones::OPTION_KEY, Caracool_Motion_Cabecera::OPTION_KEY, Caracool_Motion_Intro::OPTION_KEY ) ) ) );
$_POST = array( 'cm_botones' => array( 'activo' => 'si' ) );
$mb->guardar();
$g = Caracool_Motion_Scroll::get_settings();
comprueba( 'guardar botones no toca los ajustes de scroll', isset( $g['inercia'] ) && 1.25 === (float) $g['duracion'] );

echo "\n=== Cabecera · valores por defecto ===\n";
$cb = Caracool_Motion_Cabecera::get_settings();
comprueba( 'cabecera apagada de fábrica', 'no' === $cb['activo'] );
comprueba( 'umbral por defecto 120', 120 === (int) $cb['umbral'], 'era ' . $cb['umbral'] );
comprueba( 'fondo por defecto primary', 'primary' === $cb['fondo'], 'era ' . $cb['fondo'] );
comprueba( 'sin línea de fábrica', 'no' === $cb['sombra'] );

echo "\n=== Cabecera · guardado ===\n";
$mc = new Caracool_Motion_Cabecera();
$_POST = array( 'cm_cabecera' => array( 'activo' => 'si', 'umbral' => '200', 'fondo' => 'text', 'sombra' => 'si' ) );
$mc->guardar();
$cb = Caracool_Motion_Cabecera::get_settings();
comprueba( 'guarda encendida', 'si' === $cb['activo'] );
comprueba( 'guarda el umbral', 200 === (int) $cb['umbral'], 'era ' . $cb['umbral'] );
comprueba( 'guarda el fondo de sistema', 'text' === $cb['fondo'], 'era ' . $cb['fondo'] );
comprueba( 'guarda la línea', 'si' === $cb['sombra'] );

echo "\n=== Cabecera · basura ===\n";
foreach ( array( '10', '5000', 'abc', '' ) as $malo ) {
	$_POST = array( 'cm_cabecera' => array( 'umbral' => $malo ) );
	$mc->guardar();
	$cb = Caracool_Motion_Cabecera::get_settings();
	comprueba( "umbral '$malo' cae en 120", 120 === (int) $cb['umbral'], 'era ' . $cb['umbral'] );
}
foreach ( array( 'inventado', '', '<script>' ) as $malo ) {
	$_POST = array( 'cm_cabecera' => array( 'fondo' => $malo ) );
	$mc->guardar();
	$cb = Caracool_Motion_Cabecera::get_settings();
	comprueba( "fondo '$malo' cae en primary", 'primary' === $cb['fondo'], 'era ' . $cb['fondo'] );
}
comprueba( 'sin Elementor, la lista de colores trae los cuatro de sistema', 4 === count( Caracool_Motion_Cabecera::colores_globales() ) );

echo "\n=== Pestaña de Elementor ===\n";
foreach ( array( 'modules/cm-scroll.php', 'modules/cm-botones.php' ) as $f ) {
	$src = file_get_contents( dirname( __DIR__ ) . '/' . $f );
	comprueba( "$f cuelga su sección en la pestaña Estilo", false !== strpos( $src, 'TAB_STYLE' ) && false === strpos( $src, 'TAB_ADVANCED' ) );
}

echo "\n=== Intro · valores por defecto ===\n";
$in = Caracool_Motion_Intro::get_settings();
comprueba( 'intro apagada de fábrica', 'no' === $in['activo'] );
comprueba( 'en inicio de fábrica y sin otras páginas', 'si' === $in['inicio'] && array() === $in['paginas'] );
comprueba( 'tamaño por defecto 220 / 150', 220 === (int) $in['tamano'] && 150 === (int) $in['tamano_movil'] );
comprueba( 'animación por defecto freno', 'freno' === $in['animacion'] );
comprueba( 'salida por defecto sábana', 'sabana' === $in['salida'] );
comprueba( 'tempo 100 y 7 días', 100 === (int) $in['tempo'] && 7 === (int) $in['dias'] );
comprueba( 'sin logotipo no toca imprimir aunque se active', false === Caracool_Motion_Intro::toca() );

echo "\n=== Intro · guardado ===\n";
$mi = new Caracool_Motion_Intro();
$_POST = array( 'cm_intro' => array( 'activo' => 'si', 'inicio' => 'si', 'paginas' => array( '12', '12', 'x', '0', '7' ), 'logo' => '63', 'tamano' => '300', 'tamano_movil' => '120', 'fondo' => 'text', 'animacion' => 'respira', 'salida' => 'recorte', 'tempo' => '120', 'dias' => '30' ) );
$mi->guardar();
$in = Caracool_Motion_Intro::get_settings();
comprueba( 'guarda todo', 'si' === $in['activo'] && 'si' === $in['inicio'] && 63 === $in['logo'] && 300 === (int) $in['tamano'] && 120 === (int) $in['tamano_movil'] && 'text' === $in['fondo'] && 'respira' === $in['animacion'] && 'recorte' === $in['salida'] && 120 === (int) $in['tempo'] && 30 === (int) $in['dias'] );
comprueba( 'las páginas se guardan limpias y sin repetidos', array( 12, 7 ) === $in['paginas'], json_encode( $in['paginas'] ) );
comprueba( 'con logotipo y activa, toca imprimir en portada', true === Caracool_Motion_Intro::toca() );
$_POST = array( 'cm_intro' => array( 'activo' => 'si', 'logo' => '63', 'paginas' => array( '7' ) ) );
$mi->guardar();
comprueba( 'inicio desmarcado: en portada no toca', false === Caracool_Motion_Intro::toca() );

echo "\n=== Intro · basura ===\n";
$_POST = array( 'cm_intro' => array( 'logo' => '63', 'animacion' => 'inventada', 'salida' => 'lateral', 'fondo' => 'nada', 'tempo' => '999', 'dias' => '-3', 'tamano' => '5000', 'tamano_movil' => 'abc', 'paginas' => 'no-es-array' ) );
$mi->guardar();
$in = Caracool_Motion_Intro::get_settings();
comprueba( 'animación inventada cae en freno', 'freno' === $in['animacion'] );
comprueba( 'salida inventada cae en sábana', 'sabana' === $in['salida'] );
comprueba( 'fondo inventado cae en primary', 'primary' === $in['fondo'] );
comprueba( 'tempo fuera de rango cae en 100', 100 === (int) $in['tempo'] );
comprueba( 'días negativos caen en 7', 7 === (int) $in['dias'] );
comprueba( 'tamaños fuera de rango caen en 220 / 150', 220 === (int) $in['tamano'] && 150 === (int) $in['tamano_movil'] );
comprueba( 'páginas que no son lista se ignoran', array() === $in['paginas'] );
comprueba( 'casilla ausente apaga la intro', 'no' === $in['activo'] );

echo "\n=== Intro · catálogo ===\n";
$as = Caracool_Motion_Intro::animaciones();
comprueba( 'cinco animaciones de serie', 5 === count( $as ) && isset( $as['freno'], $as['asiento'], $as['respira'], $as['gota'], $as['sello'] ) );
foreach ( $as as $k => $a ) { comprueba( "la animación '$k' tiene etiqueta y ayuda", ! empty( $a['etiqueta'] ) && ! empty( $a['ayuda'] ) ); }

echo "\n=== Todos los hooks apuntan a algo que existe ===\n";
$total = 0;
foreach ( array_merge( $GLOBALS['acciones'], $GLOBALS['filtros'] ) as $par ) {
	list( $hook, $cb ) = $par;
	$total++;
	comprueba( "callback de $hook", is_callable( $cb ), is_array( $cb ) ? get_class( $cb[0] ) . '::' . $cb[1] : '' );
}
echo "  ($total hooks registrados)\n";

echo "\n=== Resultado ===\n";
echo '  ' . $GLOBALS['ok'] . " comprobaciones correctas\n";
if ( $GLOBALS['fallos'] ) {
	echo '  ' . count( $GLOBALS['fallos'] ) . " FALLOS:\n";
	foreach ( $GLOBALS['fallos'] as $f ) { echo "   - $f\n"; }
	exit( 1 );
}
echo "  sin fallos\n";
