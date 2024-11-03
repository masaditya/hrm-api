<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AttendanceController extends Controller
{
    public function getAttendaces(Request $request)
    {
        $page = $request->input('page', 1);
        $limit = $request->input('limit', 20);
        $workingFrom = $request->input('working_from');
        $userId = $request->input('user_id');

        // Build the query with optional filters
        $query = Attendance::query();

        if ($workingFrom) {
            $query->where('work_from_type', $workingFrom);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        // Order by the latest entries first, e.g., by created_at column
        $query->orderBy('created_at', 'desc');

        // Paginate results with custom limit and page
        $attendances = $query->paginate($limit, ['*'], 'page', $page)->withQueryString();

        // Transform data into the required format
        $data = $attendances->getCollection()->transform(function ($attendance) {
            return [
                'clock_in_time' => $attendance->clock_in_time ? Carbon::parse($attendance->clock_in_time)->format('Y-m-d H:i:s') : null,
                'clock_out_time' => $attendance->clock_out_time ? Carbon::parse($attendance->clock_out_time)->format('Y-m-d H:i:s') : null,
                'auto_clock_out' => (bool) $attendance->auto_clock_out,
                'clock_in_ip' => $attendance->clock_in_ip,
                'clock_out_ip' => $attendance->clock_out_ip,
                'late' => $attendance->late,
                'half_day' => $attendance->half_day,
                'latitude' => $attendance->latitude,
                'longitude' => $attendance->longitude,
                'work_from_type' => $attendance->work_from_type,
                'overwrite_attendance' => $attendance->overwrite_attendance,
                'photo' => $attendance->photo ?? '',
            ];
        });

        // Reapply the transformed collection to the paginator
        $attendances->setCollection($data);

        // Custom pagination response format
        return response()->json([
            'last_page_url' => $attendances->currentPage() === $attendances->lastPage() ? null : $attendances->url($attendances->lastPage()),
            'links' => [
                [
                    'url' => $attendances->currentPage() > 1 ? $attendances->previousPageUrl() : null,
                    'label' => '&laquo; Previous',
                    'active' => false,
                ],
                [
                    'url' => $attendances->url(1),
                    'label' => '1',
                    'active' => $attendances->currentPage() === 1,
                ],
                [
                    'url' => $attendances->currentPage() < $attendances->lastPage() ? $attendances->nextPageUrl() : null,
                    'label' => 'Next &raquo;',
                    'active' => false,
                ],
            ],
            'data' => $attendances->items(),
            'current_page' => $attendances->currentPage(),
            'last_page' => $attendances->lastPage(),
            'total' => $attendances->total(),
        ]);
    }

    public function checkin(Request $request)
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|integer',
            'user_id' => 'required|integer',
            'clock_in_time' => 'required|date_format:Y-m-d H:i:s',
            'auto_clock_out' => 'required|boolean',
            'clock_in_ip' => 'required|string',
            'late' => 'required|in:yes,no',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'work_from_type' => 'required|in:office,home,other',
            'overwrite_attendance' => 'required|in:yes,no',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Create the attendance record
        $attendance = new Attendance();
        $attendance->company_id = $request->input('company_id');
        $attendance->user_id = $request->input('user_id');
        $attendance->clock_in_time = Carbon::parse($request->input('clock_in_time'));
        $attendance->auto_clock_out = $request->input('auto_clock_out');
        $attendance->clock_in_ip = $request->input('clock_in_ip');
        $attendance->late = $request->input('late');
        $attendance->latitude = $request->input('latitude');
        $attendance->longitude = $request->input('longitude');
        $attendance->work_from_type = $request->input('work_from_type');
        $attendance->overwrite_attendance = $request->input('overwrite_attendance');

        // Handle the photo upload if present
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('photos/attendance', 'public');
            $attendance->photo = $photoPath;
        }

        // Save the attendance record
        $attendance->save();

        return response()->json(['message' => 'Check-in successful', 'data' => $attendance], 201);
    }

    public function checkout(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'user_id' => 'required|integer',
            'id' => 'required|integer', // Validate the attendance ID
            'clock_out_time' => 'required|date',
            'auto_clock_out' => 'required|boolean',
            'clock_out_ip' => 'required|string',
            'half_day' => 'required|in:yes,no',
        ]);

        // Get the user ID and clock out time from the request
        $userId = $request->input('user_id');
        $attendanceId = $request->input('id'); // Get the attendance ID
        $clockOutTime = $request->input('clock_out_time');

        // Find the attendance record for today, the user, and the specific ID
        $attendance = Attendance::where('user_id', $userId)
            ->where('id', $attendanceId) // Check for the specific attendance ID
            ->whereDate('clock_in_time', now()->toDateString()) // Check for today's attendance
            ->first();

        // Check if attendance record was found
        if (!$attendance) {
            return response()->json(['message' => 'No check-in record found for today with the provided ID.'], 404);
        }

        // Update the attendance record with checkout details
        DB::beginTransaction();
        try {
            // Update fields
            $attendance->clock_out_time = $clockOutTime;
            $attendance->auto_clock_out = $request->input('auto_clock_out');
            $attendance->clock_out_ip = $request->input('clock_out_ip');
            $attendance->half_day = $request->input('half_day');
            $attendance->save();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to checkout.'], 500);
        }

        // Return a success response including the attendance ID
        return response()->json([
            'message' => 'Checkout successful.',
            'data' => [
                'id' => $attendance->id, // Include the attendance ID
                'user_id' => $attendance->user_id,
                'clock_out_time' => $attendance->clock_out_time,
                'auto_clock_out' => $attendance->auto_clock_out,
                'clock_out_ip' => $attendance->clock_out_ip,
                'half_day' => $attendance->half_day,
                'created_at' => $attendance->created_at,
                'updated_at' => $attendance->updated_at,
            ],
        ]);
    }

    public function checkStatus(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'user_id' => 'required|integer',
        ]);

        $userId = $request->input('user_id');

        // Retrieve the attendance record for the user for today
        $attendance = DB::select("
            SELECT * FROM attendances 
            WHERE user_id = ? 
            AND clock_in_time BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 DAY) - INTERVAL 1 SECOND
        ", [$userId]);

        if (!$attendance) {
            return response()->json(['message' => 'No check-in record found for today.'], 404);
        }

        $attendance = $attendance[0];

        // Return the attendance record
        return response()->json([
            'message' => 'Check-in status retrieved successfully.',
            'data' => [
                'company_id' => $attendance->company_id,
                'user_id' => $attendance->user_id,
                'clock_in_time' => $attendance->clock_in_time ? Carbon::parse($attendance->clock_in_time)->format('Y-m-d H:i:s') : null,
                'clock_out_time' => $attendance->clock_out_time ? Carbon::parse($attendance->clock_out_time)->format('Y-m-d H:i:s') : null,
                'auto_clock_out' => $attendance->auto_clock_out,
                'clock_in_ip' => $attendance->clock_in_ip,
                'clock_out_ip' => $attendance->clock_out_ip,
                'late' => $attendance->late,
                'latitude' => $attendance->latitude,
                'longitude' => $attendance->longitude,
                'work_from_type' => $attendance->work_from_type,
                'overwrite_attendance' => $attendance->overwrite_attendance,
                'photo' => $attendance->photo,
            ],
        ], 200);
    }
}
