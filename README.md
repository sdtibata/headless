# Mi presupuesto · WordPress headless

Ejemplo de clase de Apps avanzada: un plugin de WordPress con su propia API REST y una página web que la usa para ver, crear, editar y eliminar movimientos de un presupuesto (ingresos y gastos).

## Estructura

```
├── index.html        la página: Conexión, resumen, formulario y tabla
├── estilos.css       el diseño
├── app.js            los fetch a la API
└── pluginwordpress/
    └── presupuesto-jp/
        └── presupuesto-jp.php   el plugin (un solo archivo)
```

## Rutas de la API

Base: `/wp-json/presupuesto-jp/v1`

| Método | Ruta | Qué hace | Pide clave |
|---|---|---|---|
| GET | `/movimientos` | Lista todos | No |
| GET | `/movimientos/5` | Ve uno | No |
| POST | `/movimientos` | Crea | Sí |
| PUT | `/movimientos/5` | Actualiza | Sí |
| DELETE | `/movimientos/5` | Elimina | Sí |
| GET | `/resumen` | Ingresos, gastos y saldo | No |

La clave se envía en el encabezado `X-API-Key`. Sin ella, o si es incorrecta, la API responde 401.

Ejemplo de `/resumen`:

```json
{ "ingresos": 1700000, "gastos": 1000000, "saldo": 700000 }
```

## Cómo probarlo

1. Comprime la carpeta `pluginwordpress/presupuesto-jp` en un .zip e instálalo en WordPress (Plugins → Añadir nuevo → Subir plugin). Antes, cambia `CLAVE_API` por la clave que te dio el profe.
2. En Ajustes → Enlaces permanentes elige "Nombre de la entrada".
3. Abre `index.html` con Live Server. En **Conexión** escribe la URL de la API (por ejemplo `http://presupuesto.local/wp-json/presupuesto-jp/v1`) y la clave, y pulsa "Guardar y probar".

La clave no está en este repositorio: el plugin trae `CAMBIA-ESTA-CLAVE` y la página la pide en Conexión.
