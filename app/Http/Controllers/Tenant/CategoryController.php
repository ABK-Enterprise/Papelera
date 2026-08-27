<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreCategoryRequest;
use App\Http\Requests\Tenant\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = Category::withCount('products')
            ->orderBy('name')
            ->get();

        return response()->json($categories);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse|JsonResponse
    {
        $category = Category::create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $request->validated('name'),
            'slug' => $request->validated('slug'),
            'icon' => $request->validated('icon', 'box'),
            'description' => $request->validated('description'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        if ($request->wantsJson()) {
            return response()->json($category, 201);
        }

        return back()->with('success', 'Categoría creada correctamente.');
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse|JsonResponse
    {
        if ($category->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }

        $category->update([
            'name' => $request->validated('name'),
            'slug' => $request->validated('slug'),
            'icon' => $request->validated('icon', 'box'),
            'description' => $request->validated('description'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        if ($request->wantsJson()) {
            return response()->json($category);
        }

        return back()->with('success', 'Categoría actualizada correctamente.');
    }

    public function destroy(Request $request, Category $category): RedirectResponse|JsonResponse
    {
        if ($category->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }

        if ($category->products()->exists()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'No es posible eliminar la categoría porque tiene productos asociados.',
                ], 422);
            }

            return back()->with('error', 'No es posible eliminar la categoría porque tiene productos asociados.');
        }

        $category->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Categoría eliminada.']);
        }

        return back()->with('success', 'Categoría eliminada correctamente.');
    }
}
