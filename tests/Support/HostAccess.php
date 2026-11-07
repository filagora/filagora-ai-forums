<?php

declare(strict_types=1);

namespace Filagora\AiForums\Tests\Support;

use Filagora\Core\Contracts\AccessChecker;
use Illuminate\Contracts\Auth\Authenticatable;

class HostAccess implements AccessChecker
{
    /** @var array<int|string, list<string>> user id => keys */
    public static array $granted = [];

    public function allows(?Authenticatable $user, string $key): bool
    {
        return $user !== null && in_array($key, self::$granted[$user->getAuthIdentifier()] ?? [], true);
    }

    public function allowsAny(?Authenticatable $user, array $keys): bool
    {
        return array_filter($keys, fn (string $key): bool => $this->allows($user, $key)) !== [];
    }

    public function allowsAll(?Authenticatable $user, array $keys): bool
    {
        return array_filter($keys, fn (string $key): bool => ! $this->allows($user, $key)) === [];
    }
}
