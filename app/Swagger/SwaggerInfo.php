<?php

namespace App\Swagger; // Ensure correct namespace

/**
 * @OA\Info(
 *     title="Retrack API",
 *     version="1.0.0",
 *     description="A simple API to get weather information."
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="apiKey",
 *     name="Authorization",
 *     in="header",
 *     description="Enter token in format (Bearer <token>)"
 * )
 */
class SwaggerInfo
{
    // No need for any function or code, just annotations
}