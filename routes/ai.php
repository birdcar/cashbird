<?php

use App\Mcp\Servers\CashbirdServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', CashbirdServer::class)
    ->middleware('auth:workos');
