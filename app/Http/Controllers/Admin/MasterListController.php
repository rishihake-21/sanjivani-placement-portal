<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterListImportRequest;
use App\Services\MasterListImporter;
use Illuminate\Http\JsonResponse;

class MasterListController extends Controller
{
    /** TPO and System Admin may import the roster. It carries no marks, documents or placement data. */
    public function import(MasterListImportRequest $request, MasterListImporter $importer): JsonResponse
    {
        $summary = $importer->import($request->file('file')->getRealPath(), $request->input('batch'));

        return response()->json($summary, 201);
    }
}
