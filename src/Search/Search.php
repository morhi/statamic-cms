<?php

namespace Statamic\Search;

use Illuminate\Support\Collection as IlluminateCollection;
use Statamic\API\Cacher as APICacher;
use Statamic\StaticCaching\Cacher as StaticCacher;

class Search
{
    /**
     * @var IndexManager
     */
    protected $indexes;

    public function __construct(IndexManager $indexes)
    {
        $this->indexes = $indexes;
    }

    public function indexes(): IlluminateCollection
    {
        return $this->indexes->all();
    }

    /**
     * @param  string|null  $index
     * @return APICacher|StaticCacher
     */
    public function index(string $index = null)
    {
        return $this->indexes->index($index);
    }

    /**
     * @param  string|null  $index
     * @return APICacher|StaticCacher
     */
    public function in(string $index = null)
    {
        dd($this->index($index));
        return $this->index($index);
    }

    public function clearIndex($index = null)
    {
        return $this->index($index)->clear();
    }

    public function indexExists($index = null)
    {
        return $this->index($index)->exists();
    }

    public function extend($driver, $callback)
    {
        app(IndexManager::class)->extend($driver, $callback);
    }

    public function __call($method, $parameters)
    {
        return $this->index()->$method(...$parameters);
    }
}
