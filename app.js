// Página pública del presupuesto. Solo habla con el plugin por la API REST.
// La URL y la clave se escriben en "Conexión" y se guardan en este navegador.

const $ = (id) => document.getElementById(id);
const pesos = (n) => '$' + Number(n).toLocaleString('es-CO');

// Lo que escribiste en Conexión (localStorage vive solo en tu navegador).
const CONFIG = {
  api: localStorage.getItem('api') || '',
  clave: localStorage.getItem('clave') || '',
};
$('api').value = CONFIG.api;
$('clave').value = CONFIG.clave;

// Encabezados para crear, editar y eliminar: el plugin revisa X-API-Key.
function encabezados() {
  return { 'Content-Type': 'application/json', 'X-API-Key': CONFIG.clave };
}

// 0. CONEXIÓN: guarda la URL y la clave, y vuelve a cargar los datos.
$('form-conexion').addEventListener('submit', (e) => {
  e.preventDefault();
  CONFIG.api = $('api').value.trim().replace(/\/$/, '');
  CONFIG.clave = $('clave').value.trim();
  localStorage.setItem('api', CONFIG.api);
  localStorage.setItem('clave', CONFIG.clave);
  mostrar('Conexión guardada');
  cargar();
});

function mostrar(texto, error = false) {
  $('mensaje').textContent = texto;
  $('mensaje').style.color = error ? '#b91c1c' : '#15803d';
}

// 1. VER: trae el resumen y la lista (GET, no necesita clave).
async function cargar() {
  if (!CONFIG.api) return mostrar('Escribe la URL de tu API en Conexión', true);
  try {
    const [rResumen, rLista] = await Promise.all([
      fetch(CONFIG.api + '/resumen'),
      fetch(CONFIG.api + '/movimientos'),
    ]);
    if (!rLista.ok) throw new Error('la API respondió ' + rLista.status);
    const resumen = await rResumen.json();
    const movimientos = await rLista.json();

    $('ingresos').textContent = pesos(resumen.ingresos);
    $('gastos').textContent = pesos(resumen.gastos);
    $('saldo').textContent = pesos(resumen.saldo);

    $('lista').innerHTML = '';
    if (movimientos.length === 0) {
      $('lista').innerHTML = '<tr><td colspan="4">Todavía no hay movimientos.</td></tr>';
    }
    movimientos.forEach((m) => {
      const fila = document.createElement('tr');
      fila.innerHTML = `<td>${m.fecha}</td><td></td>
        <td class="${m.tipo}">${m.tipo === 'gasto' ? '−' : '+'} ${pesos(m.monto)}</td>
        <td><button class="secundario editar">Editar</button> <button class="eliminar">Eliminar</button></td>`;
      fila.children[1].textContent = m.concepto; // textContent evita inyectar HTML.
      fila.querySelector('.editar').onclick = () => editar(m);
      fila.querySelector('.eliminar').onclick = () => eliminar(m.id);
      $('lista').appendChild(fila);
    });
  } catch (err) {
    mostrar('No se pudo leer la API: revisa la URL en Conexión (' + err.message + ')', true);
  }
}

// 2. CREAR (POST) o EDITAR (PUT): si hay id, edita.
$('form-movimiento').addEventListener('submit', async (e) => {
  e.preventDefault();
  const id = $('id').value;
  const datos = {
    concepto: $('concepto').value,
    tipo: $('tipo').value,
    monto: Number($('monto').value),
    fecha: $('fecha').value,
  };
  const r = await fetch(CONFIG.api + '/movimientos' + (id ? '/' + id : ''), {
    method: id ? 'PUT' : 'POST',
    headers: encabezados(),
    body: JSON.stringify(datos),
  });
  const respuesta = await r.json();
  if (!r.ok) {
    mostrar('Error ' + r.status + ': ' + respuesta.message, true);
    return;
  }
  mostrar(id ? 'Movimiento actualizado' : 'Movimiento agregado');
  limpiar();
  cargar();
});

// 3. ELIMINAR (DELETE).
async function eliminar(id) {
  if (!confirm('¿Eliminar este movimiento?')) return;
  const r = await fetch(CONFIG.api + '/movimientos/' + id, { method: 'DELETE', headers: encabezados() });
  const respuesta = await r.json();
  mostrar(r.ok ? 'Movimiento eliminado' : 'Error ' + r.status + ': ' + respuesta.message, !r.ok);
  cargar();
}

// Llena el formulario con un movimiento para editarlo.
function editar(m) {
  $('id').value = m.id;
  $('concepto').value = m.concepto;
  $('tipo').value = m.tipo;
  $('monto').value = m.monto;
  $('fecha').value = m.fecha;
  $('titulo-form').textContent = 'Editar movimiento #' + m.id;
  $('boton').textContent = 'Guardar cambios';
  $('cancelar').hidden = false;
  $('concepto').focus();
}

// Deja el formulario vacío otra vez.
function limpiar() {
  $('form-movimiento').reset();
  $('id').value = '';
  $('titulo-form').textContent = 'Nuevo movimiento';
  $('boton').textContent = 'Agregar';
  $('cancelar').hidden = true;
}

$('cancelar').onclick = limpiar;
cargar();
