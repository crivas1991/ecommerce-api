# Ecommerce API

API REST para una tienda en línea construida con **Laravel 12** y **PHP 8.2+**. Incluye catálogo de productos, autenticación de clientes con tokens (Sanctum), órdenes de compra, pasarela de pago con **Stripe**, y documentación interactiva con **Swagger/OpenAPI**.

## Cumplimiento de requerimientos

| Requerimiento | Estado | Detalle |
|---|:---:|---|
| Framework y versión: Laravel 12 con PHP 8.2+ | ✅ | `laravel/framework: ^12.0`, `php: ^8.2` (ver `composer.json`) |
| Base de datos MySQL | ✅ | `.env` → `DB_CONNECTION=mysql`, base `ecommerce` |
| Tabla `users` | ✅ | Con columna extra `is_active` (estado del usuario) |
| Tabla `products` | ✅ | Con soft delete y auditoría (`created_by`, `updated_by`, `deleted_by`) |
| Tabla `orders` | ✅ | Estado (`pending`/`paid`/`failed`/`cancelled`), total, moneda |
| Tabla `order_items` | ✅ | Detalle de productos por orden, con precio congelado al momento de compra |
| Tabla `payments` | ✅ | Registro de transacciones de Stripe (Checkout Session + Payment Intent) |
| Autenticación segura por tokens | ✅ | Laravel Sanctum (Bearer tokens) |
| Documentación Swagger/OpenAPI | ✅ | `darkaonline/l5-swagger`, disponible en `/api/documentation` |
| Pasarela de pago Stripe | ✅ | `stripe/stripe-php`, Checkout Sessions + Webhooks verificados por firma |
| CRUD completo de productos (con auth para crear/editar/eliminar) | ✅ | Listado y detalle públicos; crear/editar/eliminar/restaurar requieren token |
| Registro y autenticación de usuarios | ✅ | `POST /api/auth/register`, `POST /api/auth/login` |
| Listado público de productos | ✅ | `GET /api/products` (sin autenticación) |
| Creación de órdenes de compra | ✅ | `POST /api/orders` |
| Consulta del historial de compras por usuario | ✅ | `GET /api/orders` (todas) y `GET /api/orders/user/{user}` (por usuario) |
| Validaciones con Form Requests | ✅ | Un Form Request por cada operación de escritura (ver carpeta `app/Http/Requests`) |
| Manejo de errores JSON consistente | ✅ | Handler centralizado en `bootstrap/app.php` (`withExceptions`) |
| Variables de entorno (Stripe y BD) | ✅ | `.env` / `.env.example` |
| Seeders de productos de ejemplo | ✅ | `database/seeders/ProductSeeder.php` (25 productos) |

## Stack técnico

- **Framework:** Laravel 12
- **PHP:** 8.2+
- **Base de datos:** MySQL
- **Autenticación:** Laravel Sanctum (tokens personales)
- **Pagos:** Stripe (`stripe/stripe-php`) vía Checkout Sessions + Webhooks
- **Documentación API:** `darkaonline/l5-swagger` (OpenAPI 3.0)

## Instalación

```bash
composer install

cp .env.example .env
php artisan key:generate
```

Configura en `.env` las credenciales de tu base de datos MySQL y de Stripe (modo test):

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ecommerce
DB_USERNAME=root
DB_PASSWORD=

STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

Crea la base de datos vacía (`ecommerce`) y luego corre las migraciones y seeders:

```bash
php artisan migrate --seed
```

Esto crea todas las tablas y siembra 25 productos de ejemplo + un usuario de prueba (`test@example.com` / `password`).

Levanta el servidor:

```bash
php artisan serve
```

La API queda disponible en `http://127.0.0.1:8000/api` y la documentación Swagger en `http://127.0.0.1:8000/api/documentation`.

## Autenticación

La API usa **Laravel Sanctum** con tokens tipo Bearer. Flujo:

1. `POST /api/auth/register` o `POST /api/auth/login` devuelven un `token`.
2. Ese token se envía en cada petición protegida como header:
   ```
   Authorization: Bearer {token}
   ```
3. En Swagger UI: botón **Authorize** (candado, arriba a la derecha) → pegar el token.

Un usuario con `is_active = false` no puede iniciar sesión (403).

## Webhook de Stripe (desarrollo local)

Para probar el flujo de pago completo en local necesitas el [Stripe CLI](https://stripe.com/docs/stripe-cli):

```bash
stripe listen --forward-to http://127.0.0.1:8000/api/stripe/webhook
```

El comando imprime un `whsec_...` — cópialo a `STRIPE_WEBHOOK_SECRET` en tu `.env`. Con eso corriendo, cualquier pago real de prueba (tarjeta `4242 4242 4242 4242`) disparará el webhook y actualizará automáticamente la orden a `paid`.

> El endpoint `/api/stripe/webhook` **no se prueba manualmente desde Swagger**: requiere una firma real que solo Stripe (o el CLI) puede generar.

## Endpoints

Todos los endpoints están documentados de forma interactiva en `/api/documentation`. Resumen:

### Auth (`/api/auth`)

| Método | Ruta | Descripción | Auth |
|---|---|---|:---:|
| POST | `/api/auth/register` | Registrar un nuevo cliente | No |
| POST | `/api/auth/login` | Iniciar sesión, obtener token | No |
| POST | `/api/auth/logout` | Cerrar sesión (revoca el token actual) | Sí |
| GET | `/api/auth/me` | Obtener el usuario autenticado | Sí |

### Products (`/api/products`)

| Método | Ruta | Descripción | Auth |
|---|---|---|:---:|
| GET | `/api/products` | Listado público de productos (filtro `search`, paginado `per_page`) | No |
| GET | `/api/products/{product}` | Detalle de un producto | No |
| POST | `/api/products` | Crear producto | Sí |
| PUT / PATCH | `/api/products/{product}` | Actualizar producto | Sí |
| DELETE | `/api/products/{product}` | Eliminar producto (soft delete) | Sí |
| PUT | `/api/products/{product}/restore` | Restaurar un producto eliminado | Sí |

Los productos eliminados no se borran físicamente (soft delete): se marca `deleted_at` y se registra quién lo eliminó (`deleted_by`). También se registra `created_by` y `updated_by` en cada operación.

### Orders (`/api/orders`)

| Método | Ruta | Descripción | Auth |
|---|---|---|:---:|
| GET | `/api/orders` | Listado general de todas las órdenes del sistema | Sí |
| POST | `/api/orders` | Crear una orden de compra | Sí |
| GET | `/api/orders/user/{user}` | Historial de compras de un usuario específico | Sí |
| GET | `/api/orders/{order}` | Detalle de una orden (solo el dueño) | Sí |

Al crear una orden se valida stock disponible, se congela el precio unitario de cada producto al momento de la compra, y se descuenta el stock dentro de una transacción con bloqueo de fila (`lockForUpdate`) para evitar condiciones de carrera.

### Payments (`/api/orders/{order}/pay`, `/api/orders/{order}/mark-as-paid`, `/api/stripe/webhook`)

| Método | Ruta | Descripción | Auth |
|---|---|---|:---:|
| POST | `/api/orders/{order}/pay` | Genera una Stripe Checkout Session para pagar la orden | Sí (solo el dueño) |
| PUT | `/api/orders/{order}/mark-as-paid` | Marca una orden como pagada manualmente (contingencia: efectivo, transferencia, etc., sin pasar por Stripe) | Sí (no puede ser el dueño de la orden) |
| POST | `/api/stripe/webhook` | Webhook público invocado por Stripe; verifica la firma con `STRIPE_WEBHOOK_SECRET` | No (verificado por firma) |

## Validaciones (Form Requests)

Cada operación de escritura tiene su propio Form Request en `app/Http/Requests`:

- `Auth/RegisterRequest`, `Auth/LoginRequest`
- `Product/StoreProductRequest`, `Product/UpdateProductRequest`
- `Order/StoreOrderRequest`, `Order/MarkOrderAsPaidRequest`

## Manejo de errores

Todas las respuestas de error de la API son JSON consistentes, gestionadas centralmente en `bootstrap/app.php`:

```json
{ "message": "Descripción del error" }
```

Para errores de validación (422), se incluye además `errors` con el detalle por campo:

```json
{
  "message": "The given data was invalid.",
  "errors": { "email": ["The email field is required."] }
}
```

Códigos manejados explícitamente: `401` (no autenticado), `403` (no autorizado), `404` (recurso no encontrado), `405` (método no permitido), `422` (validación), `500` (error de servidor, sin exponer detalles internos ni SQL en producción).

## Seeders

```bash
php artisan db:seed
```

- `DatabaseSeeder` crea un usuario de prueba (`test@example.com` / `password`) y llama a `ProductSeeder`.
- `ProductSeeder` siembra 25 productos: 15 con datos curados + 10 generados aleatoriamente vía `ProductFactory`. Es idempotente (usa `firstOrCreate`, se puede correr varias veces sin duplicar).

## Reconstruir la base de datos desde cero

```bash
php artisan migrate:fresh --seed
```

## Notas de diseño

- **Sin sistema de roles:** cualquier usuario autenticado puede gestionar productos (crear/editar/eliminar) y marcar órdenes ajenas como pagadas manualmente, ya que no se implementó un sistema de roles admin/cliente. Se agregó una única restricción de seguridad: el dueño de una orden no puede marcarla como pagada él mismo vía `mark-as-paid`.
- **Soft delete solo en `products`:** los demás modelos (`Order`, `OrderItem`, `Payment`) usan borrado físico.
