<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Ecommerce API',
    description: 'API REST para una tienda en linea: catalogo de productos, autenticacion de clientes, ordenes de compra y pagos con Stripe.'
)]
#[OA\Server(url: '/', description: 'Servidor actual')]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Personal Access Token (Laravel Sanctum)'
)]
#[OA\Tag(name: 'Auth', description: 'Registro y autenticacion de usuarios/clientes')]
#[OA\Tag(name: 'Products', description: 'Catalogo de productos')]
#[OA\Tag(name: 'Orders', description: 'Ordenes de compra e historial')]
#[OA\Tag(name: 'Payments', description: 'Pasarela de pago con Stripe')]
class OpenApiSpec
{
    //
}
