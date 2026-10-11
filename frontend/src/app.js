const API = "/api/libros";
const tabla = document.getElementById("tabla-libros");
const mensaje = document.getElementById("mensaje");

function mostrarMensaje(texto, tipo) {
  mensaje.textContent = texto;
  mensaje.className = tipo; // 'exito' o 'error'
  setTimeout(() => {
    mensaje.textContent = "";
    mensaje.className = "";
  }, 4000);
}

function exportJSON() {
  fetch(`${API}/export`, {
    method: 'GET',
  })
    .then(response => response.text())
    .then(data => {
      const blob = new Blob([data], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'libros.json';
      a.click();
    })
    .catch(error => console.error('Error:', error));
}

// Carga los datos del libro en el formulario para editarlo
function cargarFormulario(libro) {
  document.getElementById("libro-id").value = libro.id;
  document.getElementById("titulo").value = libro.titulo;
  document.getElementById("autor").value = libro.autor;
  document.getElementById("isbn").value = libro.isbn;
  document.getElementById("anio").value = libro.anio_publicacion;
  document.getElementById("estado").value = libro.estado;
  document.getElementById("btn-guardar").textContent = "Guardar cambios";
  document.getElementById("btn-cancelar").hidden = false;
}

// Limpia el formulario y vuelve al modo "crear"
function limpiarFormulario() {
  document.getElementById("libro-id").value = "";
  document.getElementById("form-libro").reset();
  document.getElementById("btn-guardar").textContent = "Agregar libro";
  document.getElementById("btn-cancelar").hidden = true;
}

// Elimina con confirmación
async function eliminarLibro(id, titulo) {
  if (!confirm(`¿Seguro que quieres eliminar "${titulo}"?`)) {
    return; // Si cancela, no hacemos nada.
  }

  try {
    const resp = await fetch(`${API}/${id}`, { method: "DELETE" });
    if (!resp.ok) throw new Error("código " + resp.status);
    mostrarMensaje(`"${titulo}" eliminado`, "exito");
    cargarLibros();
  } catch (e) {
    mostrarMensaje("No se pudo eliminar: " + e.message, "error");
  }
}

document
  .getElementById("btn-cancelar")
  .addEventListener("click", limpiarFormulario);

async function cargarLibros() {
  try {
    const resp = await fetch(API);
    if (!resp.ok) throw new Error("código " + resp.status);
    dibujarTabla(await resp.json());
  } catch (e) {
    mostrarMensaje("No se pudo cargar la lista: " + e.message, "error");
  }
}

function dibujarTabla(libros) {
  tabla.replaceChildren();

  // ??? TU TAREA: si la lista viene vacía, mostrar una fila con
  //     el texto "No hay libros registrados" y salir de la función.
  if (libros.length === 0) {
    const fila = document.createElement("tr");
    const celda = document.createElement("td");
    celda.textContent = "No hay libros registrados";
    celda.colSpan = 7;
    fila.appendChild(celda);
    tabla.appendChild(fila);
    return;
  }

  for (const libro of libros) {
    const fila = document.createElement("tr");
    for (const campo of [
      "id",
      "titulo",
      "autor",
      "isbn",
      "anio_publicacion",
      "estado",
    ]) {
      const celda = document.createElement("td");
      celda.textContent = libro[campo];
      fila.appendChild(celda);
    }
    const celdaAcciones = document.createElement("td");
    // Botón editar
    const btnEditar = document.createElement("button");
    btnEditar.textContent = "Editar";
    btnEditar.onclick = () => cargarFormulario(libro);

    // Botón eliminar
    const btnEliminar = document.createElement("button");
    btnEliminar.textContent = "Eliminar";
    btnEliminar.onclick = () => eliminarLibro(libro.id, libro.titulo);

    celdaAcciones.appendChild(btnEditar);
    celdaAcciones.appendChild(btnEliminar);
    fila.appendChild(celdaAcciones);
    tabla.appendChild(fila);
  }
}

document.getElementById('form-libro').addEventListener('submit', async (e) => {
  e.preventDefault();

  const id = document.getElementById('libro-id').value;
  const datos = {
    titulo: document.getElementById('titulo').value.trim(),
    autor: document.getElementById('autor').value.trim(),
    isbn: document.getElementById('isbn').value.trim(),
    anio_publicacion: parseInt(document.getElementById('anio').value),
    estado: document.getElementById('estado').value,
  };

  const metodo = id ? "PUT" : "POST";
  const url = id ? `${API}/${id}` : API;

  try {
    const resp = await fetch(url, {
      method: metodo,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(datos),
    });

    const json = await resp.json();

    if (!resp.ok) {
      mostrarMensaje('Error: ' + (json.error ?? resp.status), 'error');
      return;
    }

    mostrarMensaje(id ? 'Libro actualizado' : 'Libro creado', 'exito');
    limpiarFormulario();
    cargarLibros();
  } catch (e) {
    mostrarMensaje('Error de red: ' + e.message, 'error');
  }
});

cargarLibros();
