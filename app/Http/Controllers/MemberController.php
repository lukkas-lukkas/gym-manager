<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemberRequest;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    /**
     * Display a paginated list of members.
     */
    public function index(): Response
    {
        return Inertia::render('members/Index', [
            'members' => Member::query()
                ->select(['id', 'name', 'email', 'birth_date'])
                ->orderBy('name')
                ->paginate(15),
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
}
