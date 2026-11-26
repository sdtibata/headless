<?php
/**
 * Plugin Name: Presupuesto JP
 * Description: API REST para llevar un presupuesto de ingresos y gastos. Actividad de Apps avanzada.
 * Version: 1.0.0
 * Author: Juan Pérez
 */

// Cambia JP y jp por tus iniciales (en esta línea y en GRUPO).
// Así tu plugin no choca con los de tus compañeros en el mismo WordPress.
namespace Presupuesto\JP;

const GRUPO = 'jp';

// Clave para crear, editar y eliminar. Pega aquí la que te da el profe.
const CLAVE_API = 'CAMBIA-ESTA-CLAVE';

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita que el archivo se abra directamente.
}

/* ------------------------------------------------------------------
 * 1. Datos del plugin
 * ------------------------------------------------------------------ */

// Nombre de la tabla: wp_presupuesto_jp
function tabla() {
    global $wpdb;
    return $wpdb->prefix . 'presupuesto_' . GRUPO;
}

// Ruta base de la API: /wp-json/presupuesto-jp/v1
function ruta_api() {
    return 'presupuesto-' . GRUPO . '/v1';
}

/* ------------------------------------------------------------------
 * 2. Crear la tabla al activar el plugin
 * ------------------------------------------------------------------ */
register_activation_hook( __FILE__, __NAMESPACE__ . '\crear_tabla' );

function crear_tabla() {
    global $wpdb;
    $tabla   = tabla();
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $tabla (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        concepto VARCHAR(120) NOT NULL,
        tipo VARCHAR(10) NOT NULL,
        monto DECIMAL(12,2) NOT NULL,
        fecha DATE NOT NULL,
        PRIMARY KEY  (id)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

/* ------------------------------------------------------------------
 * 3. Rutas de la API
 * ------------------------------------------------------------------ */
add_action( 'rest_api_init', function () {

    // GET  /movimientos  -> listar      POST /movimientos -> crear
    register_rest_route( ruta_api(), '/movimientos', array(
        array( 'methods' => 'GET',  'callback' => __NAMESPACE__ . '\listar', 'permission_callback' => '__return_true' ),
        array( 'methods' => 'POST', 'callback' => __NAMESPACE__ . '\crear',  'permission_callback' => __NAMESPACE__ . '\puede_editar' ),
    ) );

    // GET /movimientos/5 -> ver   PUT -> actualizar   DELETE -> eliminar
    register_rest_route( ruta_api(), '/movimientos/(?P<id>\d+)', array(
        array( 'methods' => 'GET',    'callback' => __NAMESPACE__ . '\ver',        'permission_callback' => '__return_true' ),
        array( 'methods' => 'PUT',    'callback' => __NAMESPACE__ . '\actualizar', 'permission_callback' => __NAMESPACE__ . '\puede_editar' ),
        array( 'methods' => 'DELETE', 'callback' => __NAMESPACE__ . '\eliminar',   'permission_callback' => __NAMESPACE__ . '\puede_editar' ),
    ) );

    // GET /resumen -> total de ingresos, gastos y saldo
    register_rest_route( ruta_api(), '/resumen', array(
        'methods' => 'GET', 'callback' => __NAMESPACE__ . '\resumen', 'permission_callback' => '__return_true',
    ) );
} );

// Leer es público. Crear, editar y eliminar exige enviar la clave en el encabezado X-API-Key.
function puede_editar( $request ) {
    $clave = (string) $request->get_header( 'X-API-Key' );
    if ( '' === $clave || ! hash_equals( CLAVE_API, $clave ) ) {
        return new \WP_Error( 'sin_permiso', 'Falta la clave o es incorrecta', array( 'status' => 401 ) );
    }
    return true;
}

// Permite que el navegador envíe el encabezado X-API-Key desde otra página (CORS).
add_filter( 'rest_allowed_cors_headers', function ( $encabezados ) {
    $encabezados[] = 'X-API-Key';
    return $encabezados;
} );

/* ------------------------------------------------------------------
 * 4. Validar y dar formato
 * ------------------------------------------------------------------ */

// Convierte una fila de la base de datos en el JSON que devuelve la API.
function formatear( $fila ) {
    return array(
        'id'       => (int) $fila->id,
        'concepto' => $fila->concepto,
        'tipo'     => $fila->tipo,
        'monto'    => (float) $fila->monto,
        'fecha'    => $fila->fecha,
    );
}

// Revisa los datos recibidos. Devuelve un arreglo limpio o un WP_Error (400).
// $parcial = true cuando se actualiza: solo se revisan los campos que llegan.
function validar( $datos, $parcial = false ) {
    $limpio = array();

    if ( isset( $datos['concepto'] ) || ! $parcial ) {
        $concepto = sanitize_text_field( $datos['concepto'] ?? '' );
        if ( '' === $concepto ) {
            return new \WP_Error( 'datos_invalidos', 'El concepto es obligatorio', array( 'status' => 400 ) );
        }
        $limpio['concepto'] = $concepto;
    }

    if ( isset( $datos['tipo'] ) || ! $parcial ) {
        $tipo = $datos['tipo'] ?? '';
        if ( ! in_array( $tipo, array( 'ingreso', 'gasto' ), true ) ) {
            return new \WP_Error( 'datos_invalidos', 'El tipo debe ser ingreso o gasto', array( 'status' => 400 ) );
        }
        $limpio['tipo'] = $tipo;
    }

    if ( isset( $datos['monto'] ) || ! $parcial ) {
        $monto = floatval( $datos['monto'] ?? 0 );
        if ( $monto <= 0 ) {
            return new \WP_Error( 'datos_invalidos', 'El monto debe ser mayor que cero', array( 'status' => 400 ) );
        }
        $limpio['monto'] = $monto;
    }

    if ( ! empty( $datos['fecha'] ) ) {
        $limpio['fecha'] = gmdate( 'Y-m-d', strtotime( $datos['fecha'] ) );
    } elseif ( ! $parcial ) {
        $limpio['fecha'] = current_time( 'Y-m-d' ); // Si no envían fecha, se usa la de hoy.
    }

    return $limpio;
}

/* ------------------------------------------------------------------
 * 5. Funciones del CRUD
 * ------------------------------------------------------------------ */

// READ: todos los movimientos, del más reciente al más antiguo.
function listar() {
    global $wpdb;
    $filas = $wpdb->get_results( 'SELECT * FROM ' . tabla() . ' ORDER BY fecha DESC, id DESC' );
    return rest_ensure_response( array_map( __NAMESPACE__ . '\formatear', $filas ) );
}

// READ: un movimiento por id.
function ver( $request ) {
    global $wpdb;
    $fila = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . tabla() . ' WHERE id = %d', $request['id'] ) );
    if ( ! $fila ) {
        return new \WP_Error( 'no_existe', 'Movimiento no encontrado', array( 'status' => 404 ) );
    }
    return rest_ensure_response( formatear( $fila ) );
}

// CREATE: guarda un movimiento nuevo. Responde 201.
function crear( $request ) {
    global $wpdb;
    $datos = validar( $request->get_json_params() );
    if ( is_wp_error( $datos ) ) {
        return $datos;
    }
    $wpdb->insert( tabla(), $datos );

    $request['id'] = $wpdb->insert_id;
    $respuesta     = ver( $request );
    $respuesta->set_status( 201 );
    return $respuesta;
}

// UPDATE: cambia solo los campos que lleguen.
function actualizar( $request ) {
    global $wpdb;
    $existe = ver( $request );
    if ( is_wp_error( $existe ) ) {
        return $existe;
    }
    $datos = validar( $request->get_json_params(), true );
    if ( is_wp_error( $datos ) ) {
        return $datos;
    }
    if ( $datos ) {
        $wpdb->update( tabla(), $datos, array( 'id' => (int) $request['id'] ) );
    }
    return ver( $request );
}

// DELETE: borra un movimiento.
function eliminar( $request ) {
    global $wpdb;
    $existe = ver( $request );
    if ( is_wp_error( $existe ) ) {
        return $existe;
    }
    $wpdb->delete( tabla(), array( 'id' => (int) $request['id'] ) );
    return rest_ensure_response( array( 'eliminado' => true, 'id' => (int) $request['id'] ) );
}

// RESUMEN: suma ingresos y gastos, y calcula el saldo.
function resumen() {
    global $wpdb;
    $ingresos = (float) $wpdb->get_var( 'SELECT COALESCE(SUM(monto), 0) FROM ' . tabla() . " WHERE tipo = 'ingreso'" );
    $gastos   = (float) $wpdb->get_var( 'SELECT COALESCE(SUM(monto), 0) FROM ' . tabla() . " WHERE tipo = 'gasto'" );
    return rest_ensure_response( array(
        'ingresos' => $ingresos,
        'gastos'   => $gastos,
        'saldo'    => $ingresos - $gastos,
    ) );
}
