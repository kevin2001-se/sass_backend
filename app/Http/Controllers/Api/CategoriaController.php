<?php

namespace App\Http\Controllers\Api;

use App\Models\Categoria;

class CategoriaController extends CatalogoController
{
    protected string $modelClass = Categoria::class;
}
