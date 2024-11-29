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


}
