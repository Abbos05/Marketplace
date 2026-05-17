<?php

namespace Tests\Unit;

use App\Models\OrderItem;
use App\Services\CommissionBreakdownService;
use App\Services\CommissionService;
use Mockery;
use Tests\TestCase;

class CommissionBreakdownServiceTest extends TestCase
{
    public function test_aggregate_splits_commission_into_payment_vat_and_platform(): void
    {
        config([
            'marketplace.commission_split.payment_fee_percent_of_commission' => 10,
            'marketplace.commission_split.vat_percent_of_commission' => 20,
        ]);

        $commissionService = Mockery::mock(CommissionService::class);
        $commissionService->shouldReceive('resolveSnapshot')
            ->andReturn([
                'gross' => 200.0,
                'commission' => 40.0,
                'seller_payout' => 160.0,
                'percent' => 15.0,
                'fixed_amount' => 5.0,
            ]);

        $item = new OrderItem([
            'quantity' => 2,
            'price_at_purchase' => 100,
            'commission_amount' => 40,
            'seller_payout_amount' => 160,
        ]);

        $service = new CommissionBreakdownService($commissionService);
        $result = $service->aggregate(collect([$item]));

        $t = $result['totals'];
        $this->assertSame(200.0, $t['gross']);
        $this->assertSame(40.0, $t['commission_total']);
        $this->assertSame(4.0, $t['payment_processing_fee']);
        $this->assertSame(8.0, $t['vat_amount']);
        $this->assertSame(28.0, $t['platform_net']);
        $this->assertLessThan($t['gross'], $t['commission_total']);
        $this->assertEqualsWithDelta(
            $t['commission_total'],
            $t['payment_processing_fee'] + $t['vat_amount'] + $t['platform_net'],
            0.01
        );
    }
}
