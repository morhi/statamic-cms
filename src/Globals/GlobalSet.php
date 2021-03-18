<?php

namespace Statamic\Globals;

use Illuminate\Support\Collection as IlluminateCollection;
use Statamic\Contracts\Globals\GlobalSet as Contract;
use Statamic\Data\ExistsAsFile;
use Statamic\Events\GlobalSetDeleted;
use Statamic\Events\GlobalSetSaved;
use Statamic\Facades;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Site;
use Statamic\Facades\Stache;
use Statamic\Support\Arr;
use Statamic\Support\Traits\FluentlyGetsAndSets;

class GlobalSet implements Contract
{
    use ExistsAsFile, FluentlyGetsAndSets;

    protected $title;
    protected $handle;
    protected $localizations;

    public function id(): string
    {
        return $this->handle();
    }

    public function handle($handle = null)
    {
        return $this->fluentlyGetOrSet('handle')->args(func_get_args());
    }

    /**
     * @param  string|null  $title
     * @return string|GlobalSet
     */
    public function title($title = null)
    {
        return $this
            ->fluentlyGetOrSet('title')
            ->getter(function ($title) {
                return $title ?? ucfirst($this->handle);
            })
            ->args(func_get_args());
    }

    public function blueprint(): ?\Statamic\Fields\Blueprint
    {
        return Blueprint::find('globals.'.$this->handle());
    }

    public function path(): string
    {
        return vsprintf('%s/%s.%s', [
            rtrim(Stache::store('globals')->directory(), '/'),
            $this->handle(),
            'yaml',
        ]);
    }

    public function save(): GlobalSet
    {
        Facades\GlobalSet::save($this);

        GlobalSetSaved::dispatch($this);

        return $this;
    }

    public function delete(): bool
    {
        Facades\GlobalSet::delete($this);

        GlobalSetDeleted::dispatch($this);

        return true;
    }

    public function fileData(): array
    {
        $data = [
            'title' => $this->title(),
        ];

        if (!Site::hasMultiple()) {
            $data['data'] = Arr::removeNullValues(
                $this->in(Site::default()->handle())->data()->all()
            );
        }

        return $data;
    }

    /**
     * @param  string  $site
     * @return Variables
     */
    public function makeLocalization(string $site): Variables
    {
        return (new Variables)
            ->globalSet($this)
            ->locale($site);
    }

    public function addLocalization(Variables $localization): GlobalSet
    {
        $localization->globalSet($this);

        $this->localizations[$localization->locale()] = $localization;

        return $this;
    }

    public function removeLocalization(Variables $localization): GlobalSet
    {
        unset($this->localizations[$localization->locale()]);

        return $this;
    }

    public function in($locale): ?Variables
    {
        return $this->localizations[$locale] ?? null;
    }

    public function inSelectedSite(): ?Variables
    {
        return $this->in(Site::selected()->handle());
    }

    public function inCurrentSite(): ?Variables
    {
        return $this->in(Site::current()->handle());
    }

    public function inDefaultSite(): ?Variables
    {
        return $this->in(Site::default()->handle());
    }

    public function existsIn($locale): bool
    {
        return $this->in($locale) !== null;
    }

    /**
     * @return IlluminateCollection|Variables[]
     */
    public function localizations(): IlluminateCollection
    {
        return collect($this->localizations);
    }

    public function editUrl()
    {
        return cp_route('globals.edit', $this->handle());
    }

    public function deleteUrl()
    {
        return cp_route('globals.destroy', $this->handle());
    }

    public static function __callStatic($method, $parameters)
    {
        return Facades\GlobalSet::{$method}(...$parameters);
    }
}
