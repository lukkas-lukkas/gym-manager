<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class MemberStatusController extends Controller
{
    /**
     * Reactivate the given member.
     */
    public function activate(Member $member): RedirectResponse
    {
        $member->activate();

        Inertia::flash('success', 'Aluno reativado.');

        return redirect()->route('members.edit', $member);
    }

    /**
     * Deactivate the given member.
     */
    public function deactivate(Member $member): RedirectResponse
    {
        $member->deactivate();

        Inertia::flash('success', 'Aluno desativado.');

        return redirect()->route('members.edit', $member);
    }
}
