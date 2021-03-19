<?php

namespace Statamic\Entries;

use Illuminate\Support\Collection as IlluminateCollection;
use Statamic\API\Cacher as APICacher;
use Statamic\Contracts\Data\Augmentable as AugmentableContract;
use Statamic\Contracts\Entries\Collection as Contract;
use Statamic\Contracts\Taxonomies\Taxonomy;
use Statamic\Data\ContainsCascadingData;
use Statamic\Data\ExistsAsFile;
use Statamic\Data\HasAugmentedData;
use Statamic\Events\CollectionCreated;
use Statamic\Events\CollectionDeleted;
use Statamic\Events\CollectionSaved;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Facades;
use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint as BlueprintFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\File;
use Statamic\Facades\Search;
use Statamic\Facades\Site;
use Statamic\Facades\Stache;
use Statamic\Facades\Taxonomy as TaxonomyFacade;
use Statamic\Fields\Blueprint;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Statamic;
use Statamic\StaticCaching\Cacher as StaticCacher;
use Statamic\Structures\CollectionStructure;
use Statamic\Support\Arr;
use Statamic\Support\Traits\FluentlyGetsAndSets;

class Collection implements Contract, AugmentableContract
{
    use FluentlyGetsAndSets, ExistsAsFile, HasAugmentedData, ContainsCascadingData;

    protected $handle;
    protected $routes = [];
    protected $mount;
    protected $title;
    protected $template;
    protected $layout;
    protected $sites;
    protected $blueprints = [];
    protected $searchIndex;
    protected $dated = false;
    protected $sortField;
    protected $sortDirection;
    protected $ampable = false;
    protected $revisions = false;
    protected $positions;
    protected $defaultPublishState = true;
    protected $futureDateBehavior = 'public';
    protected $pastDateBehavior = 'public';
    protected $structure;
    protected $structureContents;
    protected $taxonomies = [];

    public function __construct()
    {
        $this->cascade = collect();
    }

    public function id(): ?string
    {
        return $this->handle();
    }

    /**
     * @param  string|null  $handle
     * @return string|Collection
     */
    public function handle(string $handle = null)
    {
        return $this->fluentlyGetOrSet('handle')->args(func_get_args());
    }

    /**
     * @param  null  $routes
     * @return IlluminateCollection|Collection
     */
    public function routes($routes = null)
    {
        return $this
            ->fluentlyGetOrSet('routes')
            ->getter(function ($routes) {
                return $this->sites()->mapWithKeys(function ($site) use ($routes) {
                    $siteRoute = is_string($routes) ? $routes : ($routes[$site] ?? null);

                    return [$site => $siteRoute];
                });
            })
            ->args(func_get_args());
    }

    public function route(string $site): ?string
    {
        return $this->routes()->get($site);
    }

    /**
     * @param  bool|null  $dated
     * @return Collection|bool
     */
    public function dated(bool $dated = null)
    {
        return $this->fluentlyGetOrSet('dated')->args(func_get_args());
    }

    public function orderable(): bool
    {
        return optional($this->structure())->maxDepth() === 1;
    }

    /**
     * @param  string|null  $field
     * @return Collection|string
     */
    public function sortField(string $field = null)
    {
        return $this
            ->fluentlyGetOrSet('sortField')
            ->getter(function ($sortField) {
                if ($sortField) {
                    return $sortField;
                } elseif ($this->orderable()) {
                    return 'order';
                } elseif ($this->dated()) {
                    return 'date';
                }

                return 'title';
            })
            ->args(func_get_args());
    }


    /**
     * @param  string|null  $dir
     * @return Collection|string
     */
    public function sortDirection(string $dir = null)
    {
        return $this
            ->fluentlyGetOrSet('sortDirection')
            ->getter(function ($sortDirection) {
                if ($sortDirection) {
                    return $sortDirection;
                }

                // If a custom sort field has been defined but no direction, we'll default
                // to ascending. Otherwise, if it was a dated collection, it might end
                // up with a field in descending order which would be confusing.
                if ($this->sortField) {
                    return 'asc';
                }

                if ($this->orderable()) {
                    return 'asc';
                } elseif ($this->dated()) {
                    return 'desc';
                }

                return 'asc';
            })
            ->args(func_get_args());
    }

    /**
     * @param  string|null  $title
     * @return Collection|string
     */
    public function title(string $title = null)
    {
        return $this
            ->fluentlyGetOrSet('title')
            ->getter(function ($title) {
                return $title ?? ucfirst($this->handle);
            })
            ->args(func_get_args());
    }

    /**
     * @param  bool|null  $ampable
     * @return Collection|bool
     */
    public function ampable(bool $ampable = null)
    {
        return $this
            ->fluentlyGetOrSet('ampable')
            ->getter(function ($ampable) {
                return config('statamic.amp.enabled') && $ampable;
            })
            ->args(func_get_args());
    }

    /**
     * @param  string|null  $site
     * @return string|null
     */
    public function url(string $site = null): ?string
    {
        if (!$mount = $this->mount()) {
            return null;
        }

        $site = $site ?? $this->sites()->first();

        return optional($mount->in($site))->url();
    }

    /**
     * @param  string|null  $site
     * @return string|null
     */
    public function uri(string $site = null): ?string
    {
        if (!$mount = $this->mount()) {
            return null;
        }

        $site = $site ?? $this->sites()->first();

        return optional($mount->in($site))->uri();
    }

    public function showUrl(): ?string
    {
        return cp_route('collections.show', $this->handle());
    }

    public function editUrl(): ?string
    {
        return cp_route('collections.edit', $this->handle());
    }

    public function deleteUrl(): ?string
    {
        return cp_route('collections.destroy', $this->handle());
    }

    public function createEntryUrl(string $site = null): ?string
    {
        $site = $site ?? $this->sites()->first();

        return cp_route('collections.entries.create', [$this->handle(), $site]);
    }

    public function queryEntries(): EntryQueryBuilder
    {
        return Facades\Entry::query()->where('collection', $this->handle());
    }

    /**
     * @return IlluminateCollection|Blueprint[]
     */
    public function entryBlueprints(): IlluminateCollection
    {
        $blink = 'collection-entry-blueprints-'.$this->handle();

        return Blink::once($blink, function () {
            return $this->getEntryBlueprints();
        });
    }

    /**
     * @return IlluminateCollection|Blueprint[]
     */
    private function getEntryBlueprints(): IlluminateCollection
    {
        $blueprints = BlueprintFacade::in('collections/'.$this->handle());

        if ($blueprints->isEmpty()) {
            $blueprints = collect([$this->fallbackEntryBlueprint()]);
        }

        return $blueprints->values()->map(function ($blueprint) {
            return $this->ensureEntryBlueprintFields($blueprint);
        });
    }

    public function entryBlueprint(string $blueprint = null, Entry $entry = null): ?Blueprint
    {
        if (!$blueprint = $this->getBaseEntryBlueprint($blueprint)) {
            return null;
        }

        $blueprint->setParent($entry ?? $this);

        $this->dispatchEntryBlueprintFoundEvent($blueprint, $entry);

        return $blueprint;
    }

    private function getBaseEntryBlueprint(string $blueprint = null): ?Blueprint
    {
        $blink = 'collection-entry-blueprint-'.$this->handle().'-'.$blueprint;

        return Blink::once($blink, function () use ($blueprint) {
            return is_null($blueprint)
                ? $this->entryBlueprints()->reject->hidden()->first()
                : $this->entryBlueprints()->keyBy->handle()->get($blueprint);
        });
    }

    private function dispatchEntryBlueprintFoundEvent(Blueprint $blueprint, Entry $entry = null)
    {
        $id = optional($entry)->id() ?? 'null';

        $blink = 'collection-entry-blueprint-'.$this->handle().'-'.$blueprint->handle().'-'.$id;

        Blink::once($blink, function () use ($blueprint, $entry) {
            EntryBlueprintFound::dispatch($blueprint, $entry);
        });
    }

    public function fallbackEntryBlueprint(): ?Blueprint
    {
        $blueprint = BlueprintFacade::find('default')
            ->setHandle($this->handle())
            ->setNamespace('collections.'.$this->handle());

        $contents = $blueprint->contents();
        $contents['title'] = $this->title();
        $blueprint->setContents($contents);

        return $blueprint;
    }

    public function ensureEntryBlueprintFields(Blueprint $blueprint): Blueprint
    {
        $blueprint
            ->ensureFieldPrepended('title', ['type' => 'text', 'required' => true])
            ->ensureField('slug', ['type' => 'slug', 'required' => true, 'localizable' => true], 'sidebar');

        if ($this->dated()) {
            $blueprint->ensureField('date', ['type' => 'date', 'required' => true], 'sidebar');
        }

        if ($this->hasStructure() && !$this->orderable()) {
            $blueprint->ensureField('parent', [
                'type' => 'entries',
                'collections' => [$this->handle()],
                'max_items' => 1,
                'listable' => false,
                'localizable' => true,
            ], 'sidebar');
        }

        foreach ($this->taxonomies() as $taxonomy) {
            $blueprint->ensureField($taxonomy->handle(), [
                'type' => 'terms',
                'taxonomies' => [$taxonomy->handle()],
                'display' => $taxonomy->title(),
                'mode' => 'select',
            ], 'sidebar');
        }

        return $blueprint;
    }

    /**
     * @param  string[]|null  $sites
     * @return Collection|IlluminateCollection
     */
    public function sites(array $sites = null)
    {
        return $this
            ->fluentlyGetOrSet('sites')
            ->getter(function ($sites) {
                if (!Site::hasMultiple() || !$sites) {
                    $sites = [Site::default()->handle()];
                }

                return collect($sites);
            })
            ->args(func_get_args());
    }

    /**
     * @param  string|null  $template
     * @return Collection|string
     */
    public function template(string $template = null)
    {
        return $this
            ->fluentlyGetOrSet('template')
            ->getter(function ($template) {
                return $template ?? 'default';
            })
            ->args(func_get_args());
    }

    /**
     * @param  string|null  $layout
     * @return Collection|string
     */
    public function layout(string $layout = null)
    {
        return $this
            ->fluentlyGetOrSet('layout')
            ->getter(function ($layout) {
                return $layout ?? 'layout';
            })
            ->args(func_get_args());
    }

    public function save(): Collection
    {
        $isNew = !Facades\Collection::handleExists($this->handle);

        Facades\Collection::save($this);

        Blink::forget('collection-handles');
        Blink::flushStartingWith("collection-{$this->id()}");

        if ($isNew) {
            CollectionCreated::dispatch($this);
        }

        CollectionSaved::dispatch($this);

        return $this;
    }

    public function updateEntryUris($ids = null): Collection
    {
        Facades\Collection::updateEntryUris($this, $ids);

        return $this;
    }

    public function updateEntryOrder($ids = null): Collection
    {
        Facades\Collection::updateEntryOrder($this, $ids);

        return $this;
    }

    public function path(): string
    {
        return vsprintf('%s/%s.yaml', [
            rtrim(Stache::store('collections')->directory(), '/'),
            $this->handle,
        ]);
    }

    /**
     * @param  string|null  $index
     * @return Collection|APICacher|StaticCacher
     */
    public function searchIndex(string $index = null)
    {
        return $this
            ->fluentlyGetOrSet('searchIndex')
            ->getter(function ($index) {
                return $index ? Search::index($index) : null;
            })
            ->args(func_get_args());
    }

    public function hasSearchIndex(): bool
    {
        return $this->searchIndex() !== null;
    }

    public function fileData(): array
    {
        $array = Arr::except($this->toArray(), [
            'handle',
            'past_date_behavior',
            'future_date_behavior',
            'default_publish_state',
            'dated',
            'structured',
            'orderable',
            'routes',
        ]);

        $route = is_string($this->routes) ? $this->routes : $this->routes()->filter()->all();

        $array = Arr::removeNullValues(array_merge($array, [
            'route' => $route,
            'amp' => $array['amp'] ?: null,
            'date' => $this->dated ?: null,
            'sort_by' => $this->sortField,
            'sort_dir' => $this->sortDirection,
            'default_status' => $this->defaultPublishState === false ? 'draft' : null,
            'date_behavior' => [
                'past' => $this->pastDateBehavior,
                'future' => $this->futureDateBehavior,
            ],
        ]));

        if (!Site::hasMultiple()) {
            unset($array['sites']);
        }

        if ($array['date_behavior'] == ['past' => 'public', 'future' => 'public']) {
            unset($array['date_behavior']);
        }

        $array['inject'] = Arr::pull($array, 'cascade');

        if ($this->hasStructure()) {
            $array['structure'] = $this->structureContents();
        }

        return $array;
    }

    /**
     * @param  string|null  $behavior
     * @return Collection|string
     */
    public function futureDateBehavior(string $behavior = null)
    {
        return $this
            ->fluentlyGetOrSet('futureDateBehavior')
            ->getter(function ($behavior) {
                return $behavior ?? 'public';
            })
            ->args(func_get_args());
    }

    /**
     * @param  bool|null  $state
     * @return Collection|bool
     */
    public function defaultPublishState(bool $state = null)
    {
        return $this
            ->fluentlyGetOrSet('defaultPublishState')
            ->getter(function ($state) {
                return $this->revisionsEnabled() ? false : $state;
            })
            ->args(func_get_args());
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'handle' => $this->handle,
            'routes' => $this->routes,
            'dated' => $this->dated,
            'past_date_behavior' => $this->pastDateBehavior(),
            'future_date_behavior' => $this->futureDateBehavior(),
            'default_publish_state' => $this->defaultPublishState,
            'amp' => $this->ampable,
            'sites' => $this->sites,
            'template' => $this->template,
            'layout' => $this->layout,
            'cascade' => $this->cascade->all(),
            'blueprints' => $this->blueprints,
            'search_index' => $this->searchIndex,
            'orderable' => $this->orderable(),
            'structured' => $this->hasStructure(),
            'mount' => $this->mount,
            'taxonomies' => $this->taxonomies,
            'revisions' => $this->revisions,
        ];
    }

    /**
     * @param  string|null  $behavior
     * @return Collection|string
     */
    public function pastDateBehavior(string $behavior = null)
    {
        return $this
            ->fluentlyGetOrSet('pastDateBehavior')
            ->getter(function ($behavior) {
                return $behavior ?? 'public';
            })
            ->args(func_get_args());
    }

    /**
     * @param  bool|null  $enabled
     * @return Collection|bool
     */
    public function revisionsEnabled(bool $enabled = null)
    {
        return $this
            ->fluentlyGetOrSet('revisions')
            ->getter(function ($enabled) {
                if (!config('statamic.revisions.enabled') || !Statamic::pro()) {
                    return false;
                }

                return $enabled;
            })
            ->args(func_get_args());
    }

    /**
     * @param  CollectionStructure|null  $structure
     * @return Collection|CollectionStructure|null
     */
    public function structure(CollectionStructure $structure = null)
    {
        return $this
            ->fluentlyGetOrSet('structure')
            ->getter(function (CollectionStructure $structure = null) {
                return Blink::once("collection-{$this->id()}-structure", function () use ($structure) {
                    if (! $structure && $this->structureContents) {
                        $structure = $this->structure = $this->makeStructureFromContents();
                    }

                    return $structure;
                });
            })
            ->setter(function (CollectionStructure $structure = null) {
                if ($structure) {
                    $structure->handle($this->handle());
                }

                $this->structureContents = null;
                Blink::forget("collection-{$this->id()}-structure");

                return $structure;
            })
            ->args(func_get_args());
    }

    /**
     * @param  array|null  $contents
     * @return Collection|array|null
     */
    public function structureContents(array $contents = null)
    {
        return $this
            ->fluentlyGetOrSet('structureContents')
            ->setter(function ($contents) {
                Blink::forget("collection-{$this->id()}-structure");
                $this->structure = null;

                return $contents;
            })
            ->getter(function ($contents) {
                if (!$structure = $this->structure()) {
                    return null;
                }

                return Arr::removeNullValues([
                    'root' => $structure->expectsRoot(),
                    'max_depth' => $structure->maxDepth(),
                ]);
            })
            ->args(func_get_args());
    }

    protected function makeStructureFromContents(): CollectionStructure
    {
        return (new CollectionStructure)
            ->handle($this->handle())
            ->expectsRoot($this->structureContents['root'] ?? false)
            ->maxDepth($this->structureContents['max_depth'] ?? null);
    }

    public function structureHandle(): ?string
    {
        if (!$this->hasStructure()) {
            return null;
        }

        return $this->structure()->handle();
    }

    public function hasStructure(): bool
    {
        return $this->structure !== null || $this->structureContents !== null;
    }

    public function delete(): bool
    {
        $this->queryEntries()->get()->each->delete();

        Facades\Collection::delete($this);

        CollectionDeleted::dispatch($this);

        return true;
    }

    /**
     * @param  string|null  $page
     * @return Collection|Entry|null
     */
    public function mount(string $page = null)
    {
        return $this
            ->fluentlyGetOrSet('mount')
            ->getter(function ($mount) {
                if (!$mount) {
                    return null;
                }

                return Blink::once("collection-{$this->id()}-mount-{$mount}", function () use ($mount) {
                    return EntryFacade::find($mount);
                });
            })
            ->args(func_get_args());
    }

    /**
     * @param  array|null  $taxonomies
     * @return Collection|IlluminateCollection|Taxonomy[]
     */
    public function taxonomies(array $taxonomies = null)
    {
        return $this
            ->fluentlyGetOrSet('taxonomies')
            ->getter(function ($taxonomies) {
                $key = "collection-{$this->id()}-taxonomies-".md5(json_encode($taxonomies));

                return Blink::once($key, function () use ($taxonomies) {
                    return collect($taxonomies)->map(function ($taxonomy) {
                        return TaxonomyFacade::findByHandle($taxonomy);
                    })->filter();
                });
            })
            ->args(func_get_args());
    }

    public function deleteFile()
    {
        File::delete($this->path());
        File::delete(dirname($this->path()).'/'.$this->handle);
    }

    public static function __callStatic($method, $parameters)
    {
        return Facades\Collection::{$method}(...$parameters);
    }

    public function __toString()
    {
        return $this->handle();
    }

    public function augmentedArrayData()
    {
        return [
            'title' => $this->title(),
            'handle' => $this->handle(),
        ];
    }
}
