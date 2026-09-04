# Montar el sistema para otro negocio

Todo lo que identifica al cliente vive en **dos archivos**. El resto del
código —panel, agenda, sitio, asistente— es igual para todos.

| Archivo | Para qué | ¿Va a git? |
|---|---|---|
| `config-cliente.js` | El navegador: sitio y panel | Sí |
| `api/cliente-config.php` | El servidor: pagos, correos, bot | **No** (uno por cliente) |

---

## Pasos

### 1. Base de datos
Crear un proyecto nuevo en [supabase.com](https://supabase.com) (uno por
cliente, nunca compartido: si no, un negocio vería las fichas del otro) y
ejecutar en el editor SQL, **en este orden**:

1. `agenda-setup.sql`
2. `modulos-clinicos.sql`
3. `recursos.sql`
4. `seguridad-fase2a-preparar.sql`
5. `origen-reservas.sql`
6. `plazo-reserva.sql`
7. `seguridad-graves.sql`
8. `asistente-julia.sql`
9. `registro-cambios.sql`

> No pongas `begin; … rollback;` al final de un script: el editor de
> Supabase ejecuta todo como una sola transacción y esa vuelta atrás
> borraría también las tablas que acabas de crear.

En Settings → API copia **Project URL** y la llave **anon**.

### 2. Los dos archivos de identidad
En `config-cliente.js`: dirección y llave de Supabase, nombre, rubro,
dominio, teléfono, WhatsApp (solo dígitos), correo, dirección y el nombre
del asistente.

Copiar `api/cliente-config.example.php` a `api/cliente-config.php` con los
mismos valores, subirlo al servidor y dejarlo en permisos `600`.

Con eso, los enlaces de WhatsApp, los correos y los teléfonos de todo el
sitio se ponen al día solos. No hay que buscar y reemplazar nada.

### 3. Contenido del negocio
- `data.js`: servicios, categorías, precios y textos.
- `images/`: fotos de servicios y categorías, logo.
- Colores y tipografías, si la marca lo pide.

Es lo que más tiempo toma, y depende de que el cliente entregue el material.

### 4. Servicios externos
Cada negocio con lo suyo, **nunca compartido**:

- **Flow** (`api/flow-config.php`): si se comparte, los pagos de un cliente
  entran a la cuenta del otro.
- **WhatsApp de Meta** (`api/bot-config.php`): token, `waPhoneId`,
  `waVerifyToken` y `waAppSecret`.
- **`serviceKey`** de Supabase, en `api/bot-config.php`. Sin ella, los
  cobros por link no se registran en la base.

La clave de OpenAI sí puede ser la misma para todos.

### 5. Primer arranque
Abrir `/agenda.html` y crear la cuenta de administrador. Después, en
Administración → Usuarios y accesos, crear una por persona del equipo.

---

## Lo que conviene recordar

- **Nunca compartir el proyecto de Supabase, la cuenta de Flow ni el número
  de WhatsApp** entre dos clientes.
- Si un archivo JavaScript se cambia y el sitio sigue mostrando el anterior,
  es la caché del hosting: subir el número de versión en las etiquetas
  `<script src="...?v=N">` de las páginas.
- Con dos o tres clientes, copias separadas funcionan. Del cuarto en
  adelante conviene un solo repositorio con la configuración por cliente,
  para que cada arreglo llegue a todos con un despliegue.
