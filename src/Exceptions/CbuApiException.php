<?php

namespace Cbu\Currency\Exceptions;

use Exception;

class CbuApiException extends Exception
{
    public static function requestFailed(string $url, int $status): self
    {
        return new self("CBU API request failed. URL: {$url}, Status: {$status}");
    }

    public static function noDataReceived(string $url): self
    {
        return new self("No data received from CBU API. URL: {$url}");
    }

    public static function connectionError(string $url, string $message): self
    {
        return new self("Connection error to CBU API. URL: {$url}, Error: {$message}");
    }
    public static function dateFormatInvalid(string $date, string $message): self
    {
        return new self("Invalid date format. Please use Y-m-d format (e.g., $date) or $message");
    }
}
