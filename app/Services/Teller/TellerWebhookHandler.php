<?php

declare(strict_types=1);

namespace App\Services\Teller;

use Illuminate\Http\Request;

class TellerWebhookHandler
{
    public function verifySignature(Request $request): void
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
