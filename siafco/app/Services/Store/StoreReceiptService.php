<?php

namespace App\Services\Store;

use App\Models\StoreOrder;
use App\Models\StoreOrderReceipt;
use App\Models\StoreSetting;
use App\Models\User;
use App\Support\StoreOrderStatus;
use App\Support\StoreReceiptStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreReceiptService
{
    private const MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    public function submit(StoreOrder $order, User $user, UploadedFile $file): StoreOrderReceipt
    {
        $this->assertCanSubmit($order);
        $meta = $this->validateFile($file);
        $path = 'store-receipts/'.$order->code.'/'.Str::uuid().'.'.$meta['extension'];

        if (! Storage::disk('local')->put($path, file_get_contents($file->getRealPath()))) {
            throw ValidationException::withMessages(['receipt' => 'No se pudo guardar el comprobante.']);
        }

        try {
            return DB::transaction(function () use ($order, $user, $file, $meta, $path): StoreOrderReceipt {
                $receipt = $order->receipts()->create([
                    'uploaded_by_user_id' => $user->id,
                    'path' => $path,
                    'mime' => $meta['mime'],
                    'size_bytes' => $meta['size'],
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                    'status' => StoreReceiptStatus::PENDING,
                    'submitted_at' => now(),
                ]);

                app(StoreOrderStatusService::class)->transition($order, StoreOrderStatus::PAYMENT_REVIEW, $user, 'Comprobante enviado.');

                return $receipt;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    public function confirm(StoreOrder $order, StoreOrderReceipt $receipt, User $actor): void
    {
        $this->assertReceiptBelongs($order, $receipt);
        DB::transaction(function () use ($order, $receipt, $actor): void {
            $receipt->update([
                'status' => StoreReceiptStatus::CONFIRMED,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
            ]);
            app(StoreOrderStatusService::class)->transition($order, StoreOrderStatus::CONFIRMED, $actor, 'Comprobante confirmado.');
        });
    }

    public function reject(StoreOrder $order, StoreOrderReceipt $receipt, User $actor, string $reason): void
    {
        $this->assertReceiptBelongs($order, $receipt);
        DB::transaction(function () use ($order, $receipt, $actor, $reason): void {
            $receipt->update([
                'status' => StoreReceiptStatus::REJECTED,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);
            app(StoreOrderStatusService::class)->transition($order, StoreOrderStatus::WAITING_PAYMENT, $actor, 'Comprobante rechazado.');
        });
    }

    public function validateFile(UploadedFile $file): array
    {
        if ($file->getSize() < 1) {
            throw ValidationException::withMessages(['receipt' => 'El comprobante está vacío.']);
        }

        $maxKb = StoreSetting::current()->max_receipt_size_kb;
        if ($file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages(['receipt' => 'El comprobante supera el tamaño permitido.']);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        if (! isset(self::MIMES[$mime])) {
            throw ValidationException::withMessages(['receipt' => 'El formato del comprobante no es válido.']);
        }

        return ['mime' => $mime, 'extension' => self::MIMES[$mime], 'size' => $file->getSize()];
    }

    private function assertCanSubmit(StoreOrder $order): void
    {
        if (! in_array($order->status, [StoreOrderStatus::PENDING, StoreOrderStatus::WAITING_PAYMENT], true)) {
            throw ValidationException::withMessages(['receipt' => 'El pedido no admite un nuevo comprobante.']);
        }

        if ($order->receipts()->where('status', StoreReceiptStatus::PENDING)->exists()) {
            throw ValidationException::withMessages(['receipt' => 'Ya existe un comprobante pendiente de revisión.']);
        }
    }

    private function assertReceiptBelongs(StoreOrder $order, StoreOrderReceipt $receipt): void
    {
        abort_unless((int) $receipt->store_order_id === (int) $order->id, 404);
    }
}
