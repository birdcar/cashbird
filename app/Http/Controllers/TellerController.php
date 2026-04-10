<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ProcessTellerWebhook;
use App\Jobs\SyncAllAccounts;
use App\Models\Institution;
use App\Models\TellerEnrollment;
use Illuminate\Http\JsonResponse;
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

        $institution = Institution::firstOrCreate(
            ['teller_id' => $request->input('institution.id')],
            ['name' => $request->input('institution.name')],
        );

        $existing = TellerEnrollment::where('user_id', $user->id)
            ->where('institution_id', $institution->id)
            ->first();

        if ($existing) {
            return redirect()->route('accounts.index')
                ->with('error', 'This institution is already connected.');
        }

        TellerEnrollment::create([
            'user_id' => $user->id,
            'institution_id' => $institution->id,
            'access_token' => $request->input('access_token'),
            'enrolled_at' => now(),
        ]);

        SyncAllAccounts::dispatch($user);

        return redirect()->route('accounts.index')
            ->with('success', 'Account connected. Syncing transactions...');
    }

    public function webhook(Request $request): JsonResponse
    {
        $this->verifyWebhookSignature($request);

        ProcessTellerWebhook::dispatch(
            $request->json()->all(),
        );

        return response()->json(['status' => 'ok']);
    }

    private function verifyWebhookSignature(Request $request): void
    {
        $secret = config('teller.signing_secret');
        $signature = $request->header('Teller-Signature');

        if (! $secret || ! $signature) {
            abort(403, 'Invalid webhook signature');
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            abort(403, 'Invalid webhook signature');
        }
    }
}
