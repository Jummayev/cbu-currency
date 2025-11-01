<?php
namespace Cbu\Currency\Enums;

enum CurrencySource: string
{
    case DATABASE = 'database';
    case API = 'api';
}
