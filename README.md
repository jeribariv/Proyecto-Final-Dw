# Motos Monfort — Sistema web de inventario y catálogo de motos

Proyecto integrador · IESTP Misioneros Monfortianos · Programación Web (PHP + PDO + MySQL + Bootstrap)

## Estructura

```
proyecto_final_pdo/
│
├── index.php            → redirige al catálogo
├── catalogo.php         ┐
├── login.php            │  FRONTEND (público)
├── registro.php         │  catálogo, login único y registro de clientes
├── logout.php           ┘
│
├── cliente/             ┐  FRONTEND (zona de clientes, rol = cliente)
│   ├── index.php        │  "Mis motos": estado de sus publicaciones
│   ├── publicar.php     │  publica su moto → queda PENDIENTE
│   ├── perfil.php       │  edita sus datos y cambia su contraseña
│   └── eliminar.php     ┘  retira una publicación suya
│
├── admin/               ┐  BACKEND (panel de administración, rol = admin)
│   ├── index.php        │  dashboard con indicadores
│   ├── motos.php        │  inventario con filtros y paginación
│   ├── agregar.php      │  nueva moto (se publica al instante)
│   ├── editar.php       │  editar moto y su estado
│   ├── eliminar.php     │  borrar moto + su foto
│   ├── solicitudes.php  │  aprobar / rechazar motos de clientes
│   ├── marcas.php       │  CRUD de marcas
│   ├── usuarios.php     │  crear admins/clientes, editar, ver detalle, rol, eliminar, buscar, paginar
│   ├── reportes.php     │  reportes del inventario
│   └── descargar_fotos.php ┘ baja fotos reales de Wikimedia Commons
│
├── includes/            → código compartido (bloqueado al navegador con .htaccess)
│   ├── config.php       credenciales de la BD y modo debug
│   ├── conexion.php     PDO, sesión, roles, CSRF, funciones (subirFoto, validarMoto…)
│   ├── header_front.php / footer_front.php   diseño del frontend
│   ├── header_admin.php / footer_admin.php   diseño del backend (barra lateral)
│   ├── form_moto.php    campos del formulario (admin y cliente)
│   └── alertas.php      mensajes flash
│
├── assets/css/          estilos.css (general) · admin.css (panel)
├── assets/js/app.js     validación en el navegador y estado de carga de los botones
├── uploads/             fotos de las motos (no ejecuta PHP)
└── db/db_motos.sql      base de datos con datos de prueba (bloqueado al navegador)
```

## Flujo cliente → admin

1. El cliente crea su cuenta en **registro.php** y publica su moto en **cliente/publicar.php**.
2. La moto se guarda con `estado = 'pendiente'` y el `id_usuario` del cliente.
3. El administrador la revisa en **admin/solicitudes.php**: la aprueba o la rechaza con un motivo.
4. Si se aprueba, aparece en **catalogo.php** con el nombre y celular del cliente.
   Si se rechaza, el cliente ve el motivo en **cliente/index.php**.

## Instalación (WampServer)

1. Copiar la carpeta a `C:\wamp64\www\`.
2. phpMyAdmin → Importar → `db/db_motos.sql`.
3. Abrir `http://localhost/proyecto_final_pdo/`.

| Rol     | Correo             | Contraseña |
|---------|--------------------|------------|
| Admin   | admin@motos.pe     | password   |
| Cliente | cliente@motos.pe   | password   |

## Seguridad implementada

- **Roles verificados en el servidor:** `verificarAdmin()` / `verificarCliente()` al inicio de cada página.
  El cliente no puede entrar a `admin/` aunque escriba la URL.
- **Sesión revalidada:** en cada página se vuelve a leer el usuario de la BD (si lo eliminan o le cambian el rol, aplica al instante).
- **CSRF:** todos los formularios POST llevan un token (`campoCsrf()`) que se valida en `conexion.php`.
- **SQL Injection:** solo sentencias preparadas de PDO.
- **XSS:** todo lo que se imprime pasa por `e()` (`htmlspecialchars`).
- **Contraseñas:** `password_hash()` / `password_verify()`.
- **Subida de fotos:** se valida el tipo real (finfo), tamaño máximo 2 MB y nombre único; `uploads/` no ejecuta PHP.
- **Errores:** el usuario ve un mensaje claro; el detalle técnico va al log de PHP (`MODO_DEBUG` en `config.php`).
- **Validación doble:** en el navegador (Bootstrap) y en PHP.
