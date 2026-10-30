<?php
namespace Tests\Feature;
use Tests\TestCase;
class BootstrapTest extends TestCase
{
    public function test_laravel_starts_without_a_database_and_does_not_expose_keys(): void
    {
        config(['football.highlightly_key' => 'private-test-key']);
        $response = $this->get('/')->assertOk()->assertJsonPath('backend', 'Laravel');
        $this->assertStringNotContainsString('private-test-key', $response->getContent());
        $this->get('/up')->assertOk();
        $this->get('/.env')->assertNotFound();
    }
}
