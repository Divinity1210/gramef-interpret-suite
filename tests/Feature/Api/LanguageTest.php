<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Language;

class LanguageTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function can_get_languages()
    {
        $this->seed('LanguagesTableSeeder');

        $languages = Language::pluck('name', 'id')->toArray();
        
        $this->get('api/languages')

            ->assertOk()
            ->assertJson($languages);
    }
}
