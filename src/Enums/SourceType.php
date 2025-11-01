<?php

namespace Cbu\Currency\Enums;

enum SourceType: string
{
    case DATABASE = 'database';
    case API = 'api';
}
