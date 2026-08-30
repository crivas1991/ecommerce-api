<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'User',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Carlos Rivas'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'carlos_rivas@email.com'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'UserRef',
    type: 'object',
    description: 'Referencia minima al usuario que realizo una accion',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Carlos Rivas'),
    ]
)]
#[OA\Schema(
    schema: 'Product',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Wireless Bluetooth Headphones'),
        new OA\Property(property: 'slug', type: 'string', example: 'wireless-bluetooth-headphones'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'High quality wireless headphones.'),
        new OA\Property(property: 'price', type: 'number', format: 'float', example: 59.99),
        new OA\Property(property: 'stock', type: 'integer', example: 40),
        new OA\Property(property: 'image_url', type: 'string', nullable: true, example: 'https://example.com/image.png'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'created_by', ref: '#/components/schemas/UserRef', nullable: true),
        new OA\Property(property: 'updated_by', ref: '#/components/schemas/UserRef', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'OrderItem',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'product_id', type: 'integer', example: 1),
        new OA\Property(property: 'product_name', type: 'string', example: 'Wireless Bluetooth Headphones'),
        new OA\Property(property: 'quantity', type: 'integer', example: 2),
        new OA\Property(property: 'unit_price', type: 'number', format: 'float', example: 59.99),
        new OA\Property(property: 'subtotal', type: 'number', format: 'float', example: 119.98),
    ]
)]
#[OA\Schema(
    schema: 'Order',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'status', type: 'string', example: 'pending', enum: ['pending', 'paid', 'cancelled', 'failed']),
        new OA\Property(property: 'total', type: 'number', format: 'float', example: 119.98),
        new OA\Property(property: 'currency', type: 'string', example: 'usd'),
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/OrderItem')),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'ValidationError',
    type: 'object',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(
                type: 'array',
                items: new OA\Items(type: 'string')
            )
        ),
    ]
)]
#[OA\Schema(
    schema: 'ErrorMessage',
    type: 'object',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'An error occurred.'),
    ]
)]
class Schemas
{
    //
}
