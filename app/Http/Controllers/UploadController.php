<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadRequest;
use App\Jobs\ProcessExcelJob;
use App\Models\Row;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function upload(UploadRequest $request): JsonResponse
    {
        if ($request->hasFile('file') && $request->file('file')->isValid()) {
            $file = $request->file('file');
            $filename = time() . '.' . $file->getClientOriginalExtension();
            $loadedFile = $file->move(storage_path('app/public/uploads'), $filename);
            dispatch(new ProcessExcelJob($loadedFile->getPathname()));

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully!',
                'path' => 'storage/uploads/' . $filename
            ]);

        } else {
            return response()->json([
                'success' => false,
                'message' => 'No valid file uploaded.'
            ], 400);
        }
    }

    public function show(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 50);
        $rows = Row::query()
            ->orderBy('date')
            ->paginate($perPage);

        return response()->json($rows);
    }
}
