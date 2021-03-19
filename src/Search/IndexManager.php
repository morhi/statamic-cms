<?php

namespace Statamic\Search;

use Algolia\AlgoliaSearch\SearchClient;
use Illuminate\Support\Collection as IlluminateCollection;
use Statamic\API\Cacher as APICacher;
use Statamic\Search\Algolia\Index as AlgoliaIndex;
use Statamic\Search\Comb\Index as CombIndex;
use Statamic\StaticCaching\Cacher as StaticCacher;
use Statamic\Support\Manager;

class IndexManager extends Manager
{
    protected function invalidImplementationMessage($name): string
    {
        return "Search index [{$name}] is not defined.";
    }

    public function all(): IlluminateCollection
    {
        return collect($this->app['config']['statamic.search.indexes'])->map(function ($config, $name) {
            return $this->index($name);
        });
    }

    /**
     * @param  string|null  $name
     * @return APICacher|StaticCacher
     */
    public function index(string $name = null)
    {
        return $this->driver($name);
    }

    public function getDefaultDriver()
    {
        return $this->app['config']['statamic.search.default'];
    }

    public function createLocalDriver(array $config, $name): CombIndex
    {
        return new CombIndex($name, $config);
    }

    public function createAlgoliaDriver(array $config, $name): AlgoliaIndex
    {
        $credentials = $config['credentials'];

        $client = SearchClient::create($credentials['id'], $credentials['secret']);

        return new AlgoliaIndex($client, $name, $config);
    }

    protected function getConfig($name): ?array
    {
        $config = $this->app['config'];

        if (! $index = $config["statamic.search.indexes.$name"]) {
            return null;
        }

        return array_merge(
            $config['statamic.search.defaults'] ?? [],
            $config["statamic.search.drivers.{$index['driver']}"] ?? [],
            $index
        );
    }
}
