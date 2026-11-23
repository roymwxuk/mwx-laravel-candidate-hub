<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReverseProxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_urls_use_https_and_the_path_prefix_from_the_proxy(): void
    {
        $this->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Prefix' => '/candidate-hub',
        ])
            ->get('http://demo.example.com/')
            ->assertRedirect('https://demo.example.com/candidate-hub/login');
    }

    public function test_urls_have_no_prefix_when_the_proxy_sends_none(): void
    {
        $this->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('http://candidate-hub.example.com/')
            ->assertRedirect('https://candidate-hub.example.com/login');
    }

    public function test_a_forwarded_host_is_ignored(): void
    {
        $this->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'evil.example.net',
        ])
            ->get('http://demo.example.com/')
            ->assertRedirect('https://demo.example.com/login');
    }
}
