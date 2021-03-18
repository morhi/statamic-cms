<?php

namespace Statamic\Facades;

use Illuminate\Support\Facades\Facade;
use Statamic\Contracts\Globals\GlobalRepository;

/**
 * @method static \Statamic\Globals\GlobalCollection all()
 * @method static null|\Statamic\Globals\GlobalSet find($id)
 * @method static null|\Statamic\Globals\GlobalSet findByHandle($handle)
 * @method static void save($global);
 * @method static delete(\Statamic\Globals\GlobalSet $param)
 *
 * @see \Statamic\Globals\GlobalCollection
 */
class GlobalSet extends Facade
{
    protected static function getFacadeAccessor()
    {
        return GlobalRepository::class;
    }
}
