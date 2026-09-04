<?php

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Services\Import\OfferImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProcessImport implements ShouldQueue
{
    use Queueable;

    protected $import;

    protected $service;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected $id
    )
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->init();

        if ($this->import->status !== ImportStatus::Created) {
            return;
        }

        $this->import->processing();
        $this->handleOffers();
        $this->import->completed();
    }

    protected function handleOffers()
    {
        foreach ($this->import->raw_offers as $offer) {
            try {
                $this->service->populateDatabase($offer, $this->import);

                $this->import->increment('total_imported');
            } catch (ValidationException $exception) {
                report($exception);
            }
        }
    }

    public function failed(Throwable $exception)
    {
        $this->import->fill([
            'status' => ImportStatus::Failed,
            'error' => $exception->getMessage(),
        ])->save();
    }

    protected function init()
    {
        $this->import = Import::query()->find($this->id);
        $this->service = resolve(OfferImportService::class);
    }
}
