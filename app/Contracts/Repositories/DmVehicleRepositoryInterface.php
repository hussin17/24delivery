<?php

namespace App\Contracts\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface DmVehicleRepositoryInterface extends RepositoryInterface
{
    /**
     * @param array $params
     * @param array $relations
     * @return Model|null
     */
    public function getFirstWithoutGlobalScopeWhere(array $params, array $relations = []): ?Model;

    /**
     * @param string|null $searchValue
     * @param int|string $dataLimit
     * @return Collection
     */
    public function getSearchedList(string $searchValue = null, int|string $dataLimit = DEFAULT_DATA_LIMIT): Collection;

    /**
     * @param array $params
     * @param string|null $id
     * @return Model|null
     */
    public function getExistFirst(array $params, string $id = null): ?Model;
}
