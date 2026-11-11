<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    /**
     * Display a paginated list of members, optionally filtered by a search term.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? 'active';

        return Inertia::render('members/Index', [
            'members' => Member::query()
                ->select(['id', 'name', 'email', 'birth_date', 'deactivated_at'])
                ->when(
                    $status === 'inactive',
                    fn (Builder $query) => $query->inactive(),
                    fn (Builder $query) => $query->active(),
                )
                ->search($search)
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Show the form for registering a new member.
     */
    public function create(): Response
    {
        return Inertia::render('members/Create', []);
    }

    /**
     * Store a newly registered member.
     */
    public function store(StoreMemberRequest $request): RedirectResponse
    {
        Member::create($request->validated());

        Inertia::flash('success', 'Aluno cadastrado com sucesso.');

        return redirect()->route('members.index');
    }

    /**
     * Show the member's details.
     */
    public function show(Member $member): Response
    {
        return Inertia::render('members/Show', [
            'member' => $member->only(['id', 'name', 'email']) + [
                'birth_date' => $member->birth_date->toDateString(),
                'deactivated_at' => $member->deactivated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Show the form for editing a member.
     */
    public function edit(Member $member): Response
    {
        return Inertia::render('members/Edit', [
            'member' => $member->only(['id', 'name', 'email']) + [
                'birth_date' => $member->birth_date->toDateString(),
                'deactivated_at' => $member->deactivated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update the given member.
     */
    public function update(UpdateMemberRequest $request, Member $member): RedirectResponse
    {
        $member->update($request->validated());

        Inertia::flash('success', 'Aluno atualizado com sucesso.');

        return redirect()->route('members.index');
    }
}
