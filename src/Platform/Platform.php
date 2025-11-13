<?php

declare(strict_types=1);

namespace TakeoutRedate\Platform;

enum Platform: string
{
    case MACOS = 'macos';
    case WINDOWS = 'windows';
    case LINUX = 'linux';
    case UNKNOWN = 'unknown';
}
