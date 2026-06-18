<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Append-only repository. Exposes ONLY append() and abstract void().
 * No update(), delete(), save(), or any mutation that overwrites rows.
 * Invariant §1: money & accountability data is INSERT-only.
 *
 * Each sub-repo implements its own void() for its model's specific fields.
 * The base void() is removed because the generic implementation is attendance-shaped
 * and would break on issue_events (missing catalogue_item_id, batch_id, etc.).
 */
abstract class AppendOnlyRepository
{
    protected string $model;

    public function append(array $attributes): Model
    {
        $instance = new $this->model($attributes);
        $instance->save();

        return $instance;
    }

    abstract public function void(string $id, string $reason): Model;

    public function find(string $id): ?Model
    {
        return $this->model::find($id);
    }

    public function findOrFail(string $id): Model
    {
        return $this->model::findOrFail($id);
    }

    public function exists(string $id): bool
    {
        return $this->model::where('id', $id)->exists();
    }

    public function findByIdAndRevision(string $id, string $revision): ?Model
    {
        return $this->model::where('id', $id)
            ->where('revision', $revision)
            ->first();
    }
}
