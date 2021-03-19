<?php

namespace Statamic\Contracts\Entries;

use Illuminate\Support\Collection as IlluminateCollection;

interface CollectionRepository
{
    public function all(): IlluminateCollection;

    public function find($id): ?Collection;

    public function findByHandle($handle): ?Collection;

    public function findByMount($mount): ?Collection;

    public function make(string $handle = null): Collection;

    public function handles(): IlluminateCollection;

    public function handleExists(string $handle): bool;

    public function save(Collection $collection): void;

    public function delete(Collection $collection): void;

    public function updateEntryUris(Collection $collection, $ids = null): void;

    public function updateEntryOrder(Collection $collection, $ids = null): void;

    public function whereStructured(): IlluminateCollection;
}
