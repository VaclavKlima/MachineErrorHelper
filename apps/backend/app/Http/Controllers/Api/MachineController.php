<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MachineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = mb_strtolower(trim((string) $request->query('search', '')));

        $machines = Machine::query()
            ->where('is_active', true)
            ->when($search !== '', function ($query) use ($search): void {
                $needle = '%'.$search.'%';

                $query->where(function ($query) use ($needle): void {
                    $query
                        ->whereRaw('lower(name) like ?', [$needle])
                        ->orWhereRaw('lower(manufacturer) like ?', [$needle])
                        ->orWhereRaw('lower(model_number) like ?', [$needle]);
                });
            })
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'manufacturer', 'model_number']);

        return response()->json(['data' => $machines]);
    }

    public function userMachines(Request $request): JsonResponse
    {
        $machines = $request->user()
            ->machines()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['machines.id', 'machines.name', 'machines.slug', 'machines.manufacturer', 'machines.model_number']);

        return response()->json(['data' => $machines]);
    }

    public function attachUserMachine(Request $request, Machine $machine): JsonResponse
    {
        abort_unless($machine->is_active, 404);

        $request->user()->machines()->syncWithoutDetaching([$machine->id]);

        return response()->json([
            'data' => $machine->only(['id', 'name', 'slug', 'manufacturer', 'model_number']),
        ], 201);
    }

    public function detachUserMachine(Request $request, Machine $machine): JsonResponse
    {
        $request->user()->machines()->detach($machine->id);

        return response()->json([
            'data' => [
                'message' => 'Machine removed.',
            ],
        ]);
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
