<?php

namespace App\Http\Controllers\Api;

use App\Models\Laboratorio;

class LaboratorioController extends CatalogoController
{
    protected string $modelClass = Laboratorio::class;
}
