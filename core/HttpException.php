<?php
declare(strict_types=1);

/**
 * Exception portant un code HTTP (403, 404…) : interceptée par le point d'entrée
 * pour afficher la page d'erreur correspondante.
 */
final class HttpException extends RuntimeException
{
}
