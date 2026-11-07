<?php

declare(strict_types=1);

arch('no debug calls')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die', 'exit'])
    ->not->toBeUsed();

arch('env() is only used in config files')
    ->expect('env')
    ->not->toBeUsedIn('Filagora\AiForums');

arch('source files use strict types')
    ->expect('Filagora\AiForums')
    ->toUseStrictTypes();
