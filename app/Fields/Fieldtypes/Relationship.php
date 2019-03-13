<?php

namespace Statamic\Fields\Fieldtypes;

use Statamic\API\Site;
use Statamic\API\Entry;
use Statamic\CP\Column;
use Statamic\API\Content;
use Illuminate\Support\Arr;
use Statamic\Fields\Fieldtype;

class Relationship extends Fieldtype
{
    protected $component = 'relationship';
    protected $itemComponent = 'related-item';
    protected $categories = ['relationship'];
    protected $canEdit = true;
    protected $canCreate = true;
    protected $canSearch = true;
    protected $statusIcons = true;

    protected $configFields = [
        'max_items' => ['type' => 'integer'],
        'collections' => ['type' => 'collections'],
    ];

    public function preProcess($data)
    {
        return Arr::wrap($data);
    }

    public function preProcessConfig($data)
    {
        $data = $this->preProcess($data);

        return $this->config('max_items') === 1 ? Arr::first($data) : $data;
    }

    public function preProcessIndex($data)
    {
        return $this->augment($data)->map(function ($item) use ($data) {
            return [
                'id' => $item->id(),
                'title' => $item->get('title'),
                'edit_url' => $item->editUrl(),
                'published' => $item->published(),
            ];
        });
    }

    public function process($data)
    {
        if ($data === null || $data === []) {
            return null;
        }

        if ($this->field->get('max_items') === 1) {
            return $data[0];
        }

        return $data;
    }

    public function rules(): array
    {
        $rules = ['array'];

        if ($max = $this->config('max_items')) {
            $rules[] = 'max:' . $max;
        }

        return $rules;
    }

    public function preload()
    {
        return [
            'data' => $this->getItemData($this->field->value())->all(),
            'columns' => $this->getColumns(),
            'itemDataUrl' => $this->getItemDataUrl(),
            'baseSelectionsUrl' => $this->getBaseSelectionsUrl(),
            'getBaseSelectionsUrlParameters' => $this->getBaseSelectionsUrlParameters(),
            'itemComponent' => $this->getItemComponent(),
            'canEdit' => $this->canEdit(),
            'canCreate' => $this->canCreate(),
            'canSearch' => $this->canSearch(),
            'statusIcons' => $this->statusIcons,
        ];
    }

    protected function canCreate()
    {
        if ($this->canCreate === false) {
            return false;
        }

        return $this->config('create', true);
    }

    protected function canEdit()
    {
        if ($this->canEdit === false) {
            return false;
        }

        return $this->config('edit', true);
    }

    protected function canSearch()
    {
        return $this->canSearch;
    }

    protected function getItemComponent()
    {
        return $this->itemComponent;
    }

    protected function getColumns()
    {
        return [
            Column::make('title'),
            Column::make('url')->label('URL'),
        ];
    }

    protected function getItemDataUrl()
    {
        return cp_route('relationship.data');
    }

    protected function getBaseSelectionsUrl()
    {
        return cp_route('relationship.index');
    }

    protected function getBaseSelectionsUrlParameters()
    {
        return [
            'collections' => $this->config('collections'),
        ];
    }

    public function getItemData($values, $site = null)
    {
        $site = $site ?? Site::selected()->handle();

        return collect($values)->map(function ($id) use ($site) {
            return $this->toItemArray($id, $site);
        })->values();
    }

    protected function toItemArray($id, $site)
    {
        if ($entry = Entry::find($id)) {
            return $entry->in($site)->toArray();
        }

        return $this->invalidItemArray($id);
    }

    protected function invalidItemArray($id)
    {
        return [
            'id' => $id,
            'title' => $id,
            'invalid' => true
        ];
    }

    public function augment($values)
    {
        return collect($values)->map(function ($value) {
            return Content::find($value);
        });
    }

    public function getIndexItems($request)
    {
        return $this->getIndexQuery($request)
            ->orderBy($this->getSortColumn($request), $this->getSortDirection($request))
            ->paginate();
    }

    public function getSortColumn($request)
    {
        return $request->get('sort', 'title');
    }

    public function getSortDirection($request)
    {
        return $request->get('order', 'asc');
    }

    protected function getIndexQuery($request)
    {
        $query = Entry::query();

        if ($collections = $request->collections) {
            $query->whereIn('collection', $collections);
        }

        if ($search = $request->search) {
            $query->where('title', 'like', '%'.$search.'%');
        }

        if ($site = $request->site) {
            $query->where('site', $site);
        }

        return $query;
    }
}
