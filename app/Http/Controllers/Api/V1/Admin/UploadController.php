<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UploadController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:5120'],
            'folder' => ['nullable', Rule::in(['banners', 'games', 'promotions', 'posts', 'providers', 'payments'])],
        ]);

        $path = $request->file('file')->store('uploads/' . ($data['folder'] ?? 'misc'), 'public');

        return $this->created([
            'path' => '/storage/' . $path,
            'url' => asset('storage/' . $path),
        ], 'File uploaded');
    }
}
