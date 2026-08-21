<?php

use App\Creeping\Fetching\PageDigest;

it('prefers a JSON-LD product block, and keeps it first', function () {
    $digest = PageDigest::fromHtml(<<<'HTML'
        <html><head>
            <script type="application/ld+json">
            {"@context":"https://schema.org","@type":"Product","name":"Stainless Kettle",
             "offers":{"@type":"Offer","price":"24.99","priceCurrency":"GBP"}}
            </script>
        </head><body><p>Stainless Kettle</p><p>Now only 19.99</p></body></html>
        HTML, 12000);

    $prompt = $digest->toPrompt('https://shop.test/p/1');

    expect($digest->structuredData)->toHaveCount(1)
        ->and($digest->structuredData[0]['name'])->toBe('Stainless Kettle')
        ->and(strpos($prompt, 'Structured data'))->toBeLessThan(strpos($prompt, 'Page text'));
});

it('unwraps a graph envelope and ignores nodes that are not products', function () {
    $digest = PageDigest::fromHtml(<<<'HTML'
        <html><head><script type="application/ld+json">
        {"@graph":[
            {"@type":"BreadcrumbList","name":"Crumbs"},
            {"@type":["Product","Thing"],"name":"Kettle"}
        ]}
        </script></head><body>x</body></html>
        HTML, 12000);

    expect($digest->structuredData)->toHaveCount(1)
        ->and($digest->structuredData[0]['name'])->toBe('Kettle');
});

it('recognises a schema type given as a full URL', function () {
    $digest = PageDigest::fromHtml(
        '<html><head><script type="application/ld+json">{"@type":"http://schema.org/Product","name":"Kettle"}</script></head><body>x</body></html>',
        12000,
    );

    expect($digest->structuredData)->toHaveCount(1);
});

it('skips a broken JSON-LD block without losing a good one', function () {
    $digest = PageDigest::fromHtml(<<<'HTML'
        <html><head>
            <script type="application/ld+json">{"@type":"Product",,,}</script>
            <script type="application/ld+json">{"@type":"Product","name":"Kettle"}</script>
        </head><body>x</body></html>
        HTML, 12000);

    expect($digest->structuredData)->toHaveCount(1)
        ->and($digest->structuredData[0]['name'])->toBe('Kettle');
});

it('picks up open graph and product meta tags', function () {
    $digest = PageDigest::fromHtml(<<<'HTML'
        <html><head>
            <title>Stainless Kettle — Shop</title>
            <link rel="canonical" href="https://shop.test/p/1">
            <meta property="og:title" content="Stainless Kettle">
            <meta property="product:price:amount" content="24.99">
            <meta property="product:price:currency" content="GBP">
            <meta name="viewport" content="width=device-width">
        </head><body>x</body></html>
        HTML, 12000);

    expect($digest->meta)->toHaveKey('og:title', 'Stainless Kettle')
        ->and($digest->meta)->toHaveKey('product:price:amount', '24.99')
        ->and($digest->meta)->toHaveKey('title', 'Stainless Kettle — Shop')
        ->and($digest->meta)->toHaveKey('canonical', 'https://shop.test/p/1')
        ->and($digest->meta)->not->toHaveKey('viewport');
});

it('reads microdata itemprops', function () {
    $digest = PageDigest::fromHtml(<<<'HTML'
        <html><body itemscope itemtype="https://schema.org/Product">
            <h1 itemprop="name">Stainless Kettle</h1>
            <span itemprop="price" content="24.99">£24.99</span>
            <meta itemprop="priceCurrency" content="GBP">
        </body></html>
        HTML, 12000);

    expect($digest->meta)->toHaveKey('itemprop:name', 'Stainless Kettle')
        ->and($digest->meta)->toHaveKey('itemprop:price', '24.99')
        ->and($digest->meta)->toHaveKey('itemprop:priceCurrency', 'GBP');
});

it('strips scripts, styles and navigation out of the body text', function () {
    $digest = PageDigest::fromHtml(<<<'HTML'
        <html><body>
            <nav><a href="/">Home</a><a href="/deals">Deals</a></nav>
            <script>var price = 'tracking';</script>
            <style>.a { color: red }</style>
            <!-- a comment -->
            <h1>Stainless Kettle</h1>
            <p>£24.99</p>
            <footer>All rights reserved</footer>
        </body></html>
        HTML, 12000);

    expect($digest->text)->toContain('Stainless Kettle')
        ->and($digest->text)->toContain('£24.99')
        ->and($digest->text)->not->toContain('Deals')
        ->and($digest->text)->not->toContain('tracking')
        ->and($digest->text)->not->toContain('color: red')
        ->and($digest->text)->not->toContain('a comment')
        ->and($digest->text)->not->toContain('All rights reserved');
});

it('keeps non-ascii characters intact', function () {
    $digest = PageDigest::fromHtml(
        '<html><body><p>Prix : 1.234,56 € — Größe</p></body></html>',
        12000,
    );

    expect($digest->text)->toContain('1.234,56 €')->toContain('Größe');
});

it('truncates a very long page and says so', function () {
    $digest = PageDigest::fromHtml(
        '<html><body><p>'.str_repeat('kettle ', 5000).'</p></body></html>',
        500,
    );

    expect($digest->truncated)->toBeTrue()
        ->and(mb_strlen($digest->text))->toBeLessThanOrEqual(500)
        ->and($digest->toPrompt('https://shop.test/p/1'))->toContain('[…truncated]');
});

it('leaves a short page untruncated', function () {
    $digest = PageDigest::fromHtml('<html><body><p>Kettle, £24.99</p></body></html>', 12000);

    expect($digest->truncated)->toBeFalse()
        ->and($digest->toPrompt('https://shop.test/p/1'))->not->toContain('truncated');
});

it('survives malformed HTML', function () {
    $digest = PageDigest::fromHtml('<html><body><p>Kettle <div><span>£24.99</body>', 12000);

    expect($digest->text)->toContain('Kettle')->toContain('£24.99');
});

it('comes back empty rather than exploding on nothing at all', function (string $html) {
    $digest = PageDigest::fromHtml($html, 12000);

    expect($digest->isThin())->toBeTrue()
        ->and($digest->structuredData)->toBe([])
        ->and($digest->meta)->toBe([]);
})->with(['', '   ', 'not html at all']);

it('knows a real product page is not thin', function () {
    $digest = PageDigest::fromHtml(
        '<html><head><meta property="og:title" content="Kettle"></head><body><p>Kettle</p></body></html>',
        12000,
    );

    expect($digest->isThin())->toBeFalse();
});

it('does not resolve external entities', function () {
    $digest = PageDigest::fromHtml(<<<'HTML'
        <!DOCTYPE html [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>
        <html><body><p>&xxe;</p><p>Kettle</p></body></html>
        HTML, 12000);

    expect($digest->text)->not->toContain('root:')
        ->and($digest->text)->not->toContain('/bin/')
        ->and($digest->text)->toContain('Kettle');
});

it('fingerprints the same page the same way, and a changed one differently', function () {
    $page = fn (string $price): PageDigest => PageDigest::fromHtml(
        '<html><body><p>Kettle</p><p>'.$price.'</p></body></html>',
        12000,
    );

    expect($page('£24.99')->fingerprint())->toBe($page('£24.99')->fingerprint())
        ->and($page('£24.99')->fingerprint())->not->toBe($page('£19.99')->fingerprint());
});
