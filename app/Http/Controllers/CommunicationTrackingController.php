<?php

namespace App\Http\Controllers;

use App\Models\FinancialCommunication;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CommunicationTrackingController extends Controller
{
    public function __invoke(Request $request, int $delivery): Response
    {
        abort_unless($request->hasValidSignature(), 403);
        FinancialCommunication::withoutGlobalScopes()->whereKey($delivery)->whereNull('opened_at')->update(['opened_at' => now()]);
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');

        return response($gif, 200, ['Content-Type' => 'image/gif', 'Cache-Control' => 'no-store, no-cache, must-revalidate']);
    }
}
