<?php

namespace App\Jobs;

use App\Models\RawDocument;
use App\Services\PythonEngineClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries   = 3;

    public function __construct(
        public readonly int $documentId,
    ) {}

    public function handle(PythonEngineClient $engine): void
    {
        $document = RawDocument::find($this->documentId);

        if (!$document) {
            Log::warning("[ProcessDocument] Document {$this->documentId} not found");
            return;
        }

        if ($document->processing_status === 'processed') {
            Log::debug("[ProcessDocument] Document {$this->documentId} already processed, skipping");
            return;
        }

        $document->markAsProcessing();

        try {
            $result = $engine->processDocument([
                'document_id'   => $document->id,
                'source_url'    => $document->source_url,
                'title'         => $document->title,
                'body'          => $document->body,
                'document_type' => $document->document_type,
                'published_at'  => $document->published_at?->toIso8601String(),
                'country_id'    => $document->country_id,
                'source_id'     => $document->source_id,
            ]);

            $document->markAsProcessed();

            Log::debug("[ProcessDocument] Processed document {$this->documentId}", [
                'mentions' => $result['mentions_created'] ?? 0,
            ]);

        } catch (\Exception $e) {
            $document->markAsFailed();
            Log::error("[ProcessDocument] Failed to process document {$this->documentId}: " . $e->getMessage());
            throw $e;
        }
    }

    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(10);
    }
}
