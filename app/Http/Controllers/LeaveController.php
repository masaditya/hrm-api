<?php

namespace App\Http\Controllers;

use App\Models\Leave;
use App\Models\LeaveFile;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class LeaveController extends Controller
{
    public function getLeaveType()
    {
        return response()->json([
            'message' => 'Success',
            'data' => LeaveType::all(['id', 'type_name']),
        ], 200);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_id' => 'required',
            'user_id' => 'required|exists:users,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'leave_date' => 'required|date',
            'leave_end_date' => 'required|date|after_or_equal:leave_date',
            'reason' => 'required|string',
            'added_by' => 'required|exists:users,id',
            'filename' => 'file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

         // Ambil rentang tanggal
        $startDate = Carbon::parse($request->leave_date); // Tanggal mulai
        $endDate = Carbon::parse($request->leave_end_date); // Tanggal selesai

        // Loop dari tanggal mulai ke tanggal selesai
        while ($startDate <= $endDate) {
            // Simpan data leave untuk setiap tanggal
            $leave = new Leave();
            $leave->company_id = $request->company_id;
            $leave->user_id = $request->user_id;
            $leave->leave_type_id = $request->leave_type_id;
            $leave->duration = 'Full-day';
            $leave->leave_date = $startDate->toDateString(); // Gunakan tanggal yang sedang diproses
            $leave->reason = $request->reason;
            $leave->status = 'pending';
            $leave->paid = 0;
            $leave->over_utilized = 0;
            $leave->added_by = $request->added_by;

            $leave->save();

            // Jika ada file, simpan file untuk setiap leave yang baru dibuat
            if ($request->hasFile('filename')) {
                $file = $request->file('filename');
                $hashname = $file->hashName(); // Nama file unik
                $path = $file->storeAs('images/leave', $hashname, 'public'); // Simpan file ke public disk

                // Simpan informasi file untuk leave yang baru dibuat
                $file_leave = new LeaveFile();
                $file_leave->company_id = $request->company_id;
                $file_leave->user_id = $request->user_id;
                $file_leave->leave_id = $leave->id; // Referensikan leave yang baru disimpan
                $file_leave->filename = $file->getClientOriginalName();
                $file_leave->hashname = $hashname;
                $file_leave->size = $file->getSize();
                $file_leave->added_by = $request->added_by;
                $file_leave->save();
            }

            // Increment ke tanggal berikutnya
            $startDate->addDay(); // Tambahkan satu hari untuk proses tanggal berikutnya
        }

        return response()->json([
            'success' => true,
            'message' => 'Leave successfully created.'
        ], 201);
    }

    public function getLeaves(Request $request)
    {
        $page = $request->input('page', 1);
        $limit = $request->input('limit', 20);
        $userId = $request->input('user_id');

        // Build the query with optional filters
        $query = Leave::query()->latest();

        if ($userId) {
            $query->where('user_id', $userId);
        }

        // Paginate results with custom limit and page
        $leaves = $query->paginate($limit, ['*'], 'page', $page)->withQueryString();

        // Transform data into the required format
        $data = $leaves->getCollection()->transform(function ($leave) {
            return [
                'company_id' => $leave->company_id,
                'user_id' => $leave->user_id,
                'leave_type_id' => $leave->leave_type_id,
                'duration' => $leave->duration,
                'leave_date' => $leave->leave_date,
                'reason' => $leave->reason,
                'status' => $leave->status,
                'paid' => $leave->paid,
                'over_utilized' => $leave->over_utilized,
                'added_by' => $leave->added_by,
            ];
        });

        // Reapply the transformed collection to the paginator
        $leaves->setCollection($data);

        // Custom pagination response format
        return response()->json([
            'last_page_url' => $leaves->currentPage() === $leaves->lastPage() ? null : $leaves->url($leaves->lastPage()),
            'links' => [
                [
                    'url' => $leaves->currentPage() > 1 ? $leaves->previousPageUrl() : null,
                    'label' => '&laquo; Previous',
                    'active' => false,
                ],
                [
                    'url' => $leaves->url(1),
                    'label' => '1',
                    'active' => $leaves->currentPage() === 1,
                ],
                [
                    'url' => $leaves->currentPage() < $leaves->lastPage() ? $leaves->nextPageUrl() : null,
                    'label' => 'Next &raquo;',
                    'active' => false,
                ],
            ],
            'data' => $leaves->items(),
            'current_page' => $leaves->currentPage(),
            'last_page' => $leaves->lastPage(),
            'total' => $leaves->total(),
        ]);
    }
}
