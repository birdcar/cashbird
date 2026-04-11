<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\SyncAllAccounts;
use App\Models\Institution;
use App\Models\TellerEnrollment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TellerController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'access_token' => 'required|string',
            'enrollment_id' => 'required|string',
            'institution.id' => 'required|string',
            'institution.name' => 'required|string',
        ]);

        $user = $request->user();
        assert($user !== null);

        $institution = Institution::firstOrCreate(
            ['teller_id' => $request->input('institution.id')],
            ['name' => $request->input('institution.name')],
        );

        try {
            TellerEnrollment::create([
                'user_id' => $user->id,
                'institution_id' => $institution->id,
                'access_token' => $request->input('access_token'),
                'enrolled_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return redirect()->route('accounts.index')
                ->with('error', 'This institution is already connected.');
        }

        SyncAllAccounts::dispatch($user);

        return redirect()->route('accounts.index')
            ->with('success', 'Account connected. Syncing transactions...');
    }
}
