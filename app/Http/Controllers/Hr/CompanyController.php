<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

// Company-wide pages every staff member can see: the org chart and the
// departments (who leads each one, who's in it, what it offers). Only
// names, titles and company emails are shown, nothing personal.
class CompanyController extends Controller
{
    public function orgChart(): View
    {
        $people = User::with('employee')->where('active', true)->orderBy('name')->get();
        $board = $people->where('role', User::ROLE_BOD);
        $boardIds = $board->pluck('id')->all();
        $reportsToBoard = fn (User $u) => in_array($u->employee?->reports_to_user_id, $boardIds, true);

        $units = $this->units($people);
        $placed = $units->flatMap(fn ($u) => collect([$u['head']])->merge($u['members']))->filter()->pluck('id')->all();

        return view('hr.company.org-chart', [
            'top' => $board->reject($reportsToBoard)->values(),
            'second' => $board->filter($reportsToBoard)->values(),
            'units' => $units,
            'others' => $people->reject(fn (User $u) => $u->isBod() || in_array($u->id, $placed, true))->values(),
        ]);
    }

    public function departments(Request $request): View
    {
        return view('hr.company.departments', [
            'units' => $this->units(User::where('active', true)->orderBy('name')->get()),
            'canEdit' => $request->user()->canManageHr(),
            'staff' => User::where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function updateDepartment(Request $request, Department $department): RedirectResponse
    {
        abort_unless($request->user()->canManageHr(), 403);
        $data = $request->validate([
            'head_user_id' => ['nullable', 'exists:users,id'],
            'head_interim' => ['nullable', 'boolean'],
            'services' => ['nullable', 'string', 'max:3000'],
            'products' => ['nullable', 'string', 'max:3000'],
        ]);
        $lines = fn (?string $text) => collect(preg_split('/\R/', (string) $text))->map(fn ($l) => trim($l))->filter()->values()->all();

        $department->update([
            'head_user_id' => $data['head_user_id'] ?? null,
            'head_interim' => $request->boolean('head_interim'),
            'services' => $lines($data['services'] ?? ''),
            'products' => $lines($data['products'] ?? ''),
        ]);

        return back()->with('success', $department->label().' updated.');
    }

    /**
     * Each unit with its head (set by HR, else its Dept Head) and members.
     *
     * @return Collection<int, array{dept: Department, head: ?User, members: Collection}>
     */
    private function units(Collection $people): Collection
    {
        return Department::ordered()->map(function (Department $dept) use ($people) {
            $head = ($dept->head_user_id ? $people->firstWhere('id', $dept->head_user_id) : null)
                ?? $people->first(fn (User $u) => $u->isDeptHead() && $u->department === $dept->key);
            $members = $people->filter(fn (User $u) => $u->department === $dept->key && ! $u->isBod() && $u->id !== $head?->id)
                ->sortBy(fn (User $u) => ($u->isDeptHead() ? '0' : '1').$u->name)->values();

            return ['dept' => $dept, 'head' => $head, 'members' => $members];
        });
    }
}
