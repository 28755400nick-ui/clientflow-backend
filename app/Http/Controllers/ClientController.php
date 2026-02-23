<?php

namespace App\Http\Controllers;

use App\Exports\ClientsExport;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Client::query()
            ->select(['id', 'first_name', 'last_name', 'phone', 'email', 'created_at', 'updated_at']);

        if ($request->filled('name')) {
            $name = $request->input('name');
            // Split term by spaces to allow "John Doe" to match first+last name
            $query->where(function ($q) use ($name) {
                $q->where('first_name', 'like', "%{$name}%")
                  ->orWhere('last_name', 'like', "%{$name}%");
            });
        }

        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%' . $request->input('phone') . '%');
        }

        $perPage = min((int) $request->input('per_page', 10), 100);
        $clients = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($clients);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = Client::create($request->validated());

        return response()->json($client, 201);
    }

    public function update(UpdateClientRequest $request, int $id): JsonResponse
    {
        $client = Client::findOrFail($id);
        $client->update($request->validated());

        return response()->json($client);
    }

    public function destroy(int $id): JsonResponse
    {
        $client = Client::findOrFail($id);
        $client->delete();

        return response()->json([
            'message' => 'Cliente eliminado correctamente.',
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $filters = $request->only(['name', 'phone']);

        return Excel::download(new ClientsExport($filters), 'clientes.xlsx');
    }
}
