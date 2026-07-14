<x-layouts.app title="Panel afiliado">
    @if(!$affiliate)
        <div class="rounded-lg border border-slate-200 bg-white p-6">No existe una ficha de afiliado vinculada a este usuario.</div>
    @else
        <div class="grid gap-5 lg:grid-cols-[1fr_380px]">
            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <p class="text-sm font-black uppercase text-[#b8942f]">{{ $affiliate->registration_number }}</p>
                <h2 class="text-3xl font-black text-[#0b1f3a]">{{ $affiliate->full_name }}</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="metric-card"><p>Estado</p><strong class="text-xl">{{ $affiliate->status }}</strong></div>
                    <div class="metric-card"><p>Sector</p><strong class="text-xl">{{ $affiliate->sector->name }}</strong></div>
                </div>

                <h3 class="mt-6 font-black text-[#0b1f3a]">Pago inicial</h3>
                @foreach($affiliate->payments as $payment)
                    <div class="mt-3 rounded border border-slate-200 p-4">
                        <p class="font-bold">Monto: Bs {{ number_format($payment->amount, 2) }} · {{ $payment->status }}</p>
                        <img class="my-4 h-44 w-44 rounded border object-contain" src="{{ $institution->paymentQrUrl() }}" alt="QR bancario">
                        <form method="post" enctype="multipart/form-data" action="{{ route('payments.proof', $payment) }}" class="grid gap-3 sm:grid-cols-2">
                            @csrf
                            <div>
                                <label class="form-label">Numero de transaccion</label>
                                <input class="form-input" name="transaction_number" value="{{ old('transaction_number', $payment->transaction_number) }}" required>
                            </div>
                            <div>
                                <label class="form-label">Comprobante opcional</label>
                                <input class="form-input" type="file" name="voucher" accept="image/*,application/pdf">
                            </div>
                            <button class="btn-primary sm:col-span-2">Registrar pago</button>
                        </form>
                    </div>
                @endforeach
            </section>

            <aside class="rounded-lg border border-slate-200 bg-white p-5">
                <h3 class="font-black text-[#0b1f3a]">Credencial</h3>
                @if($affiliate->status !== 'activo')
                    <p class="mt-3 rounded border border-[#d4af37]/40 bg-[#fff8df] p-3 text-sm text-slate-900">Debe confirmar su pago para habilitar su credencial digital.</p>
                @elseif($affiliate->credential)
                    <div class="mt-4 rounded border border-slate-200 bg-slate-50 p-3">
                        <img class="mx-auto h-24 w-40 rounded object-cover shadow" src="{{ Storage::url($affiliate->credential->png_path) }}" alt="Miniatura de credencial digital">
                    </div>
                    <a class="btn-primary mt-4 w-full" href="{{ route('credenciales.show', $affiliate) }}">Ver credencial completa</a>
                @else
                    <a class="btn-primary mt-4 w-full" href="{{ route('credenciales.show', $affiliate) }}">Ver credencial completa</a>
                @endif
            </aside>
        </div>
    @endif
</x-layouts.app>
