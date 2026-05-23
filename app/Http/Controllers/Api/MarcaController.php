<?php

namespace App\Http\Controllers\Api;

use App\Models\Marca;

class MarcaController extends CatalogoController
{
    protected string $modelClass = Marca::class;
}
