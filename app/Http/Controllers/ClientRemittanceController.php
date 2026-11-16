<?php

namespace App\Http\Controllers;

use App\ClientInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientRemittanceController extends Controller
{
    public function index(Request $request)
    {
        // For a clearer UX, the "Remittances" entry for clients is unified under Receipts.
        // Redirect the legacy client remittances index to the Receipts page.
        return redirect()->route('receipts.client.index');
    }
}