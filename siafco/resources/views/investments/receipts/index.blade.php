<x-layouts.app title="Recibos de inversiones">
    <div class="section-card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Recibo</th><th>Accionista</th><th>Lote</th><th>Total</th><th>Fecha</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            @foreach($receipts as $receipt)
                <tr>
                    <td class="font-black">{{ $receipt->receipt_number }}</td>
                    <td>{{ $receipt->investor_name_snapshot }}</td>
                    <td>{{ $receipt->purchase_number_snapshot }}</td>
                    <td>Bs {{ number_format($receipt->total_amount, 2) }}</td>
                    <td>{{ $receipt->issue_date->format('d/m/Y') }}</td>
                    <td><span class="badge">{{ $receipt->status }}</span></td>
                    <td><a class="font-bold" href="{{ route('investments.receipts.show', $receipt) }}">Ver</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="mt-4">{{ $receipts->links() }}</div>
    </div>
</x-layouts.app>
