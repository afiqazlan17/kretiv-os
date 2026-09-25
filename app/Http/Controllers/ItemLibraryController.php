<?php

namespace App\Http\Controllers;

use App\Models\ItemLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

// The catalogue behind the line-item dropdown (New Job + document modal).
// Built up by staff; hiding an item keeps old documents intact.
class ItemLibraryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ItemLibrary::class);

        $query = ItemLibrary::query()->orderBy('department')->orderBy('item_name');

        if ($search = trim((string) $request->query('q'))) {
            $query->where(fn ($q) => $q->where('item_name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%"));
        }

        if (($dept = $request->query('department')) && array_key_exists($dept, config('kretivco.departments'))) {
            $query->where('department', $dept);
        }

        return view('items.index', [
            'items' => $query->get(),
            'search' => $search,
            'department' => $dept ?? '',
        ]);
    }

    /** Typeahead for the dropdowns — scoped to the job's own department. */
    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ItemLibrary::class);

        $q = trim((string) $request->query('q'));
        $dept = (string) $request->query('dept');

        $items = ItemLibrary::query()
            ->where('active', true)
            ->when(
                $dept !== '' && array_key_exists($dept, config('kretivco.departments')),
                fn ($query) => $query->where('department', $dept)
            )
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('item_name', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%")))
            ->orderBy('item_name')
            ->limit(15)
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->item_name,
                'description' => $i->description,
                'price' => $i->price !== null ? (float) $i->price : null,
                'department' => $i->department,
            ]);

        return response()->json($items);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ItemLibrary::class);

        $data = $this->validated($request);

        if (ItemLibrary::where('department', $data['department'])->where('item_name', $data['item_name'])->exists()) {
            return back()->withErrors(['item_name' => 'That item already exists for this department.'])->withInput();
        }

        $item = ItemLibrary::create($data + ['created_by' => $request->user()->id, 'usage_count' => 0]);

        return back()->with('success', "{$item->item_name} added to the library.");
    }

    public function update(Request $request, ItemLibrary $item): RedirectResponse
    {
        $this->authorize('update', $item);

        $data = $this->validated($request);
        $data['active'] = $request->boolean('active');

        if (ItemLibrary::where('department', $data['department'])->where('item_name', $data['item_name'])->where('id', '!=', $item->id)->exists()) {
            return back()->withErrors(['item_name' => 'That item already exists for this department.']);
        }

        $item->update($data);

        return back()->with('success', "{$item->item_name} updated.");
    }

    public function destroy(ItemLibrary $item): RedirectResponse
    {
        $this->authorize('delete', $item);

        $item->delete();

        return back()->with('success', "{$item->item_name} deleted.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'department' => ['required', Rule::in(array_keys(config('kretivco.departments')))],
            'item_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);
    }
}
