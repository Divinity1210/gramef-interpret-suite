<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\InterpreterJob;

class InterpreterJobCastingTest extends TestCase
{
    public function test_security_type_id_is_cast_to_integer()
    {
        $job = InterpreterJob::make([
            'security_type_id' => '4',
        ]);

        // Ensure the cast is applied so strict comparisons elsewhere work as expected
        $this->assertSame(4, $job->security_type_id);
    }
}