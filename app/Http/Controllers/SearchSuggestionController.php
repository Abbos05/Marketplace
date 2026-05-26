<?php

namespace App\Http\Controllers;

use App\Services\CatalogSearchSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchSuggestionController extends Controller
{
    public function __invoke(Request $request, CatalogSearchSuggestionService $suggestions): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:45'],
        ]);

        return response()->json(
            $suggestions->suggest((string) ($data['q'] ?? '')),
        );
    }
}
