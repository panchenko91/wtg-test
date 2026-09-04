<?php

namespace App\Http\Controllers\API;

use App\Enums\ImportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\StoreImportRequest;
use App\Http\Resources\Import\ImportResource;
use App\Http\Resources\Import\StatusImport;
use App\Jobs\ProcessImport;
use App\Models\Import;
use App\Models\Supplier;

class ImportController extends Controller
{
    public function import(StoreImportRequest $request)
    {
        $import = Import::query()->firstOrCreate(
            [
                'supplier_id' => model_id(Supplier::byExternalId($request->supplier)),
                'external_id' => $request->external_id,
            ],
            [
                'status' => ImportStatus::Created,
                'sent_at' => $request->sent_at,
                'raw_offers' => $request->offers,
            ]
        );

        if ($import->wasRecentlyCreated) {
            dispatch(new ProcessImport(model_id($import)));
        }

        return StatusImport::make($import)
            ->response()
            ->setStatusCode(202);
    }

    public function show(Import $import)
    {
        $import->load('supplier');

        return ImportResource::make($import);
    }
}
