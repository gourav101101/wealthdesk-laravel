<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PrivateDocuments
{
    public function replace(UploadedFile $file, string $kind, ?int $clientId): Document
    {
        $path = $file->store('documents', 'local');
        try {
            [$document, $old] = DB::transaction(function () use ($file, $kind, $clientId, $path) {
                $query = Document::where('kind', $kind)->where('client_id', $clientId);
                $old = (clone $query)->get();
                $query->delete();
                $document = Document::create(['id' => (string) Str::uuid(), 'client_id' => $clientId, 'kind' => $kind, 'path' => $path, 'original_name' => mb_substr($file->getClientOriginalName(), 0, 150), 'mime' => $file->getMimeType(), 'size' => $file->getSize()]);
                Ledger::audit('document', 'Updated '.($kind === 'qr' ? 'payment QR code' : $kind), $clientId);

                return [$document, $old];
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
        foreach ($old as $previous) {
            Storage::disk('local')->delete($previous->path);
        }

        return $document;
    }
}
