<?php

use App\Rules\PublicUrl;
use Illuminate\Support\Facades\Validator;

function passesPublicUrl(string $url): bool
{
    return PublicUrl::permits($url);
}

it('accepts an ordinary public URL', function (string $url) {
    expect(passesPublicUrl($url))->toBeTrue();
})->with([
    'https://example.com/products/kettle',
    'http://example.com',
    'https://shop.example.co.uk/p/1?variant=2',
]);

it('rejects anything that is not on the public internet', function (string $url) {
    expect(passesPublicUrl($url))->toBeFalse();
})->with([
    'loopback name' => 'http://localhost/admin',
    'loopback address' => 'http://127.0.0.1:8000/admin',
    'private class A' => 'http://10.0.0.1/',
    'private class B' => 'http://172.16.4.2/',
    'private class C' => 'http://192.168.1.1/',
    'link local metadata' => 'http://169.254.169.254/latest/meta-data/',
    'ipv6 loopback' => 'http://[::1]/',
    'dotted localhost' => 'http://api.localhost/',
]);

it('rejects schemes other than http and https', function (string $url) {
    expect(passesPublicUrl($url))->toBeFalse();
})->with([
    'file:///etc/passwd',
    'ftp://example.com/file',
    'gopher://example.com',
]);

it('rejects credentials smuggled into the URL', function () {
    expect(passesPublicUrl('https://admin:hunter2@example.com/'))->toBeFalse();
});

it('rejects something that is not a URL at all', function () {
    expect(passesPublicUrl('not a url'))->toBeFalse();
});

it('gives the same answer as the rule it wraps', function (string $url) {
    expect(PublicUrl::permits($url))->toBe(
        Validator::make(['url' => $url], ['url' => [new PublicUrl]])->passes(),
    );
})->with([
    'https://example.com/products/kettle',
    'http://127.0.0.1/p/1',
    'ftp://example.com',
    'not a url at all',
]);
