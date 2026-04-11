<?php

use App\Mcp\Servers\CashbirdServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

// MCP server with session auth (browser access)
Mcp::web('/mcp', CashbirdServer::class)
    ->middleware('auth:workos');

// OAuth 2.1 Protected Resource Metadata for MCP clients (Claude Desktop, etc.)
// MCP clients discover this endpoint from the WWW-Authenticate header on 401 responses.
// It points to the AuthKit authorization server for OAuth token issuance.
Route::get('/.well-known/oauth-protected-resource', function () {
    return response()->json([
        'resource' => config('app.url'),
        'authorization_servers' => [
            config('workos.fga.authkit_url', 'https://'.config('workos.client_id').'.authkit.app'),
        ],
        'bearer_methods_supported' => ['header'],
    ]);
});
