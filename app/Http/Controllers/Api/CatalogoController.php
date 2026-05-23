<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCatalogoRequest;
use App\Http\Requests\UpdateCatalogoRequest;
use App\Http\Resources\CatalogoResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

abstract class CatalogoController extends Controller
{
    protected string $modelClass;

    public function index(Request $request)
    {
        $modelClass = $this->modelClass;

        $items = $modelClass::query()
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->when($request->has('estado'), fn ($query) => $query->where('estado', $request->boolean('estado')))
            ->when($request->filled('buscar'), fn ($query) => $query->where('nombre', 'like', '%'.$request->string('buscar').'%'))
            ->orderBy('nombre')
            ->paginate($request->integer('per_page', 15));

        return CatalogoResource::collection($items);
    }

    public function store(StoreCatalogoRequest $request)
    {
        $this->ensureUniqueName($request);

        $modelClass = $this->modelClass;
        $item = $modelClass::create($this->payload($request));

        return (new CatalogoResource($item))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $id)
    {
        return new CatalogoResource($this->findScoped($request, $id));
    }

    public function update(UpdateCatalogoRequest $request, int $id)
    {
        $item = $this->findScoped($request, $id);
        $this->ensureUniqueName($request, $item);

        $item->update($this->payload($request, false));

        return new CatalogoResource($item->refresh());
    }

    public function destroy(Request $request, int $id)
    {
        $item = $this->findScoped($request, $id);
        $item->update(['estado' => false]);

        return response()->json(['message' => 'Registro desactivado correctamente.']);
    }

    protected function findScoped(Request $request, int $id): Model
    {
        $modelClass = $this->modelClass;

        return $modelClass::query()
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->findOrFail($id);
    }

    protected function payload(Request $request, bool $creating = true): array
    {
        $data = $request->validated();
        $modelClass = $this->modelClass;
        $table = (new $modelClass())->getTable();

        if (! Schema::hasColumn($table, 'descripcion')) {
            unset($data['descripcion']);
        }

        if (! Schema::hasColumn($table, 'abreviatura')) {
            unset($data['abreviatura']);
        }

        if ($creating) {
            $data['tenant_id'] = $request->attributes->get('tenant')->id;
            $data['empresa_id'] = $request->attributes->get('empresa')->id;
        }

        return $data;
    }

    protected function ensureUniqueName(Request $request, ?Model $current = null): void
    {
        if (! $request->filled('nombre')) {
            return;
        }

        $modelClass = $this->modelClass;

        $query = $modelClass::query()
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('nombre', $request->input('nombre'));

        if ($current) {
            $query->where('id', '!=', $current->id);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'nombre' => ['El nombre ya existe para esta empresa.'],
            ]);
        }
    }
}
