<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\BankAccount;

class BankAccountMaskingTest extends TestCase
{
    public function test_mask_account_number_full_length()
    {
        $masked = BankAccount::maskAccountNumber('12345678');
        $this->assertEquals('****5678', $masked);
    }

    public function test_mask_account_number_short_length()
    {
        $masked = BankAccount::maskAccountNumber('1234');
        // last 2 digits visible for short numbers
        $this->assertEquals('**34', $masked);
    }

    public function test_mask_account_number_handles_null()
    {
        $masked = BankAccount::maskAccountNumber(null);
        $this->assertEquals('N/A', $masked);
    }

    public function test_mask_sort_code_formats_and_masks()
    {
        $masked = BankAccount::maskSortCode('12-34-56');
        // masks all but last 2 and formats as XX-XX-XX
        $this->assertEquals('**-**-56', $masked);
    }

    public function test_mask_sort_code_handles_null()
    {
        $masked = BankAccount::maskSortCode(null);
        $this->assertEquals('N/A', $masked);
    }
}