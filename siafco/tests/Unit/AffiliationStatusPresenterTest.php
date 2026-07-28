<?php

namespace Tests\Unit;

use App\Support\AffiliationStatusPresenter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AffiliationStatusPresenterTest extends TestCase
{
    public static function statuses(): array
    {
        return [
            ['pending_payment', 'Pendiente de pago'],
            ['payment_submitted', 'Pago enviado para revisión'],
            ['under_review', 'Pago en revisión'],
            ['approved', 'Afiliación aprobada'],
            ['active', 'Afiliado activo'],
            ['rejected', 'Solicitud observada'],
            ['cancelled', 'Solicitud cancelada'],
            ['PAYMENT SUBMITTED', 'Pago enviado para revisión'],
            ['payment-submitted', 'Pago enviado para revisión'],
            ['unknown_state', 'Unknown state'],
            [null, 'Estado no disponible'],
        ];
    }

    #[DataProvider('statuses')]
    public function test_it_presents_status_labels(?string $status, string $expected): void
    {
        $this->assertSame($expected, AffiliationStatusPresenter::label($status));
    }

    public function test_it_exposes_state_helpers_and_steps(): void
    {
        $this->assertTrue(AffiliationStatusPresenter::isPaymentSubmitted('PAYMENT-SUBMITTED'));
        $this->assertSame(3, AffiliationStatusPresenter::currentStep('payment_submitted'));
        $this->assertStringContainsString('bg-orange-100', AffiliationStatusPresenter::badgeClasses('payment_submitted'));
    }
}
