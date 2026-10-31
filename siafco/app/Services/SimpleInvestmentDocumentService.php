<?php

namespace App\Services;

use App\Models\SimpleInvestmentContract;
use App\Models\SimpleInvestmentDocumentVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SimpleInvestmentDocumentService
{
    private const MIMES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function upload(SimpleInvestmentContract $contract, UploadedFile $file, User $actor): SimpleInvestmentDocumentVersion
    {
        $metadata = $this->validateFile($file);
        $path = 'simple-investments/contracts/'.$contract->id.'/documents/'.Str::uuid().'.'.$metadata['extension'];

        if (! Storage::disk('local')->put($path, file_get_contents($file->getRealPath()))) {
            throw ValidationException::withMessages(['document' => 'No se pudo guardar el documento en el almacenamiento privado.']);
        }

        try {
            return DB::transaction(function () use ($contract, $file, $actor, $metadata, $path) {
                $locked = SimpleInvestmentContract::query()->lockForUpdate()->findOrFail($contract->id);
                $version = (int) $locked->documentVersions()->max('version') + 1;
                $document = $locked->documentVersions()->create([
                    'version' => $version,
                    'original_name' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
                    'path' => $path,
                    'mime' => $metadata['mime'],
                    'size_bytes' => $metadata['size'],
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                    'status' => SimpleInvestmentDocumentVersion::UPLOADED,
                    'uploaded_by' => $actor->id,
                    'uploaded_at' => now(),
                ]);
                AuditService::record('simple_investment_document_uploaded', $document, [
                    'contract_number' => $locked->contract_number,
                    'version' => $version,
                    'sha256' => $document->sha256,
                ]);

                return $document->fresh(['uploader']);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    public function review(SimpleInvestmentContract $contract, SimpleInvestmentDocumentVersion $document, User $actor, string $status, ?string $notes): SimpleInvestmentDocumentVersion
    {
        if (! in_array($status, [SimpleInvestmentDocumentVersion::VERIFIED, SimpleInvestmentDocumentVersion::OBSERVED], true)) {
            throw ValidationException::withMessages(['status' => 'El estado documental no es válido.']);
        }

        return DB::transaction(function () use ($contract, $document, $actor, $status, $notes) {
            $lockedContract = SimpleInvestmentContract::query()->lockForUpdate()->findOrFail($contract->id);
            $locked = SimpleInvestmentDocumentVersion::query()->lockForUpdate()->findOrFail($document->id);
            if ($locked->contract_id !== $lockedContract->id || $locked->version !== (int) $lockedContract->documentVersions()->max('version')) {
                throw ValidationException::withMessages(['document' => 'Solo la versión documental vigente puede revisarse.']);
            }
            if ($status === SimpleInvestmentDocumentVersion::OBSERVED && blank($notes)) {
                throw ValidationException::withMessages(['review_notes' => 'Debe registrar el motivo de la observación.']);
            }
            $locked->update([
                'status' => $status,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_notes' => filled($notes) ? trim((string) $notes) : null,
            ]);
            AuditService::record('simple_investment_document_reviewed', $locked, [
                'contract_number' => $lockedContract->contract_number,
                'version' => $locked->version,
                'status' => $status,
            ]);

            return $locked->fresh(['reviewer']);
        });
    }

    public function validateFile(UploadedFile $file): array
    {
        $size = (int) $file->getSize();
        if ($size < 1) {
            throw ValidationException::withMessages(['document' => 'El documento está vacío.']);
        }
        if ($size > ((int) config('simple_investments.document_max_kb', 10240) * 1024)) {
            throw ValidationException::withMessages(['document' => 'El documento supera el tamaño permitido.']);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        if (! isset(self::MIMES[$mime])) {
            throw ValidationException::withMessages(['document' => 'Solo se permiten documentos PDF, JPG, PNG o WEBP válidos.']);
        }

        return ['mime' => $mime, 'extension' => self::MIMES[$mime], 'size' => $size];
    }
}
