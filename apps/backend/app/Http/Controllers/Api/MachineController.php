<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use Illuminate\Http\JsonResponse;

class MachineController extends Controller
{
    public function index(): JsonResponse
    {
        $machines = Machine::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'manufacturer', 'model_number']);

        return response()->json(['data' => $machines]);
    }

    public function show(Machine $machine): JsonResponse
    {
        abort_unless($machine->is_active, 404);

        $machine->load([
            'softwareVersions' => fn ($query) => $query->orderBy('sort_order')->orderBy('version'),
            'codePatterns' => fn ($query) => $query->where('is_active', true)->orderBy('priority'),
        ]);

        return response()->json(['data' => $machine]);
    }
}
