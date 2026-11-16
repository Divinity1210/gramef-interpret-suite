<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StatusController extends Controller
{
    /**
     * Return known job statuses
     *
     * The InterpreterJob model uses numeric statuses:
     *   0 = Pending, 1 = Assigned, 2 = Completed, 3 = Cancelled, 4 = DNA, 5 = Returned
     */
    public function index()
    {
        try {
            $statuses = [
                ['id' => 0, 'name' => 'Pending', 'label' => 'Pending'],
                ['id' => 1, 'name' => 'Assigned', 'label' => 'Assigned'],
                ['id' => 2, 'name' => 'Completed', 'label' => 'Completed'],
                ['id' => 3, 'name' => 'Cancelled', 'label' => 'Cancelled'],
                ['id' => 4, 'name' => 'DNA', 'label' => 'Did Not Attend'],
                ['id' => 5, 'name' => 'Returned', 'label' => 'Returned'],
            ];

            return response()->json([
                'success' => true,
                'data' => $statuses,
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR statuses.index ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to load statuses',
            ], 503);
        }
    }
}
