# API movil de Mini Store

Contrato para la aplicacion Android de afiliados activos.

Base URL local:

```text
http://127.0.0.1:8000/api/mobile/v1
```

Todas las rutas de tienda requieren:

```http
Authorization: Bearer {token}
Accept: application/json
```

Middleware aplicado:

- `auth:sanctum`
- `mobile.affiliate`
- `mobile.affiliate.active`

Solo acceden usuarios `user_type=affiliate`, activos, con afiliado existente y `affiliate.status=activo`.

## Formato

Respuesta exitosa:

```json
{
  "success": true,
  "message": "OK",
  "data": {}
}
```

Error:

```json
{
  "success": false,
  "message": "Los datos enviados no son validos.",
  "errors": {}
}
```

Estados esperados: `401`, `403`, `404`, `409`, `422`, `429`.

## Catalogo

```http
GET /store
```

Filtros:

- `search`
- `category`
- `featured`
- `availability`
- `page`
- `per_page`, maximo `30`

Devuelve configuracion compacta, destacados, categorias, productos y paginacion. No devuelve numero de WhatsApp, rutas internas ni IDs.

```json
{
  "settings": {
    "currency": "BOB",
    "pickup_enabled": true,
    "shipping_enabled": false,
    "pickup_instructions": null,
    "shipping_instructions": null,
    "payment": {
      "qr_url": null,
      "bank": null,
      "holder": null,
      "account": null,
      "instructions": null
    },
    "whatsapp_enabled": false
  },
  "featured": [],
  "categories": [],
  "products": [],
  "pagination": {}
}
```

## Producto

```http
GET /store/products/{product_public_code}
```

El producto debe estar activo, visible y con categoria activa. Las imagenes se devuelven como URL HTTP/HTTPS versionada con `?v={lastModified}`. No se devuelve `path`, `disk` ni ruta fisica.

## Cotizacion

```http
POST /store/quote
Content-Type: application/json
```

```json
{
  "items": [
    {
      "product_public_code": "00000000-0000-0000-0000-000000000000",
      "variant_public_code": null,
      "quantity": 1
    }
  ],
  "delivery_method": "pickup",
  "department": null,
  "city": null,
  "zone": null,
  "delivery_address": null,
  "coupon_code": null
}
```

La API recalcula precios, descuentos, envio y total con los mismos servicios web. No acepta precios ni totales enviados por Android. La cotizacion no crea pedido, no reserva cupon y no guarda carrito.

## Crear pedido

```http
POST /store/orders
Idempotency-Key: {uuid}
Content-Type: application/json
```

Payload igual a cotizacion.

Misma clave y mismo payload devuelve el pedido original. Misma clave con payload distinto devuelve `409`.

Respuesta principal:

```json
{
  "order": {
    "code": "PED-...",
    "status": "pendiente",
    "status_label": "Pendiente",
    "items": [],
    "subtotal": "0.00",
    "discount_total": "0.00",
    "shipping_total": "0.00",
    "total": "0.00",
    "currency": "BOB",
    "capabilities": {
      "can_upload_receipt": true,
      "can_open_whatsapp": true,
      "can_cancel": true,
      "can_view_receipt": false
    }
  }
}
```

## Pedidos propios

```http
GET /store/orders
GET /store/orders/{order_code}
```

Filtros de listado:

- `status`
- `date_from`
- `date_to`
- `code`
- `page`
- `per_page`, maximo `30`

Solo se devuelven pedidos del afiliado autenticado. Un pedido ajeno responde `404`.

## Comprobante

```http
POST /store/orders/{order_code}/receipt
Idempotency-Key: {uuid}
Content-Type: multipart/form-data
```

Campo:

- `receipt`

Formatos aceptados por MIME real: JPEG, PNG, WEBP y PDF. El archivo se almacena en disco privado local con nombre UUID. La respuesta no incluye URL publica, ruta interna ni hash.

Un comprobante pendiente bloquea otro envio. Un comprobante rechazado permite nuevo envio.

## WhatsApp

```http
POST /store/orders/{order_code}/whatsapp
```

El servidor valida propietario, configuracion, descifra el numero solo en servidor, genera mensaje desde snapshots, audita con hint y devuelve una URL `https://wa.me/...` para que Android la abra.

No se devuelve el numero en un campo separado. El mensaje no incluye CI, email, telefono, direccion completa, IDs, cupones ni tokens.

## Flujo Android sugerido

1. Login con Sanctum.
2. Cargar `GET /store`.
3. Abrir detalle con `GET /store/products/{public_code}`.
4. Calcular total con `POST /store/quote`.
5. Crear pedido con `POST /store/orders` e `Idempotency-Key`.
6. Mostrar detalle con `GET /store/orders/{code}`.
7. Subir comprobante privado con `POST /store/orders/{code}/receipt`.
8. Abrir WhatsApp con `POST /store/orders/{code}/whatsapp`.
