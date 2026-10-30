<?php
namespace App\Football;
use Symfony\Component\HttpKernel\Exception\HttpException;
class ApiError extends HttpException
{
    public function __construct(string $message, int $status = 502) { parent::__construct($status, $message); }
}
