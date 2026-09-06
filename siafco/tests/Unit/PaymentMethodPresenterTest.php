<?php

namespace Tests\Unit;

use App\Support\PaymentMethodPresenter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PaymentMethodPresenterTest extends TestCase
{
    public static function methods(): array
    {
        return [
            ['efectivo', 'Efectivo'],
            ['qr', 'QR'],
            ['transferencia', 'Transferencia'],
            [null, 'No registrado'],
        ];
    }

    #[DataProvider('methods')]
    public function test_it_presents_payment_methods_without_generic_combinations(?string $method, string $label): void
    {
        $this->assertSame($label, PaymentMethodPresenter::label($method));
    }

    public function test_only_electronic_methods_show_a_transaction_number(): void
    {
        $this->assertTrue(PaymentMethodPresenter::showsTransactionNumber('qr'));
        $this->assertTrue(PaymentMethodPresenter::showsTransactionNumber('transferencia'));
        $this->assertFalse(PaymentMethodPresenter::showsTransactionNumber('efectivo'));
        $this->assertFalse(PaymentMethodPresenter::showsTransactionNumber(null));
    }
}
