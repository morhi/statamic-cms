<?php

namespace Statamic\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed|\Spatie\Blink\Blink store($name = 'default')
 * @method static put($key, $value = null)
 * @method static get(string $key, $default = null)
 * @method static has(string $key): bool
 * @method static all(): array
 * @method static allStartingWith(string $startingWith = ''): array
 * @method static forget(string $key)
 * @method static flush()
 * @method static flushStartingWith(string $startingWith = '')
 * @method static pull(string $key)
 * @method static increment(string $key, int $by = 1)
 * @method static decrement(string $key, int $by = 1)
 * @method static offsetExists($offset)
 * @method static offsetGet($offset)
 * @method static offsetSet($offset, $value)
 * @method static offsetUnset($offset)
 * @method static count()
 * @method static once($key, callable $callable)
 * @method static getValuesForKeys(array $keys): array
 *
 * @see \Statamic\Support\Blink
 */
class Blink extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Statamic\Support\Blink::class;
    }
}
