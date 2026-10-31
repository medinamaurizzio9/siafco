@props(['status'])
<span class="investor-status">{{ ['draft' => 'Borrador', 'pending_signature' => 'Pendiente de firma', 'signed' => 'Firmado', 'void' => 'Anulado'][$status] ?? 'En revisión' }}</span>
