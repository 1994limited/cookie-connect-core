<?php

namespace NineteenNinetyFour\CookieConnect\Core\Tests\Output;

use NineteenNinetyFour\CookieConnect\Core\Output\Injector;
use PHPUnit\Framework\TestCase;

class InjectorTest extends TestCase
{
    private const HEAD = "<!-- Cookie Connect -->\n<script>cc()</script>\n<!-- /Cookie Connect -->";

    public function test_it_puts_each_block_in_its_place(): void
    {
        $page = '<!doctype html><html><head lang="en"><meta charset="utf-8"><title>x</title></head><body class="home"><header>h</header><p>hi</p></body></html>';

        $out = Injector::inject($page, self::HEAD, Injector::BODY_START."\n<script>start()</script>", Injector::BODY_END."\n<script>end()</script>");

        $this->assertSame(
            "<!doctype html><html><head lang=\"en\">\n".self::HEAD.'<meta charset="utf-8"><title>x</title></head><body class="home">'
            ."\n".Injector::BODY_START."\n<script>start()</script><header>h</header><p>hi</p>".Injector::BODY_END."\n<script>end()</script>\n</body></html>",
            $out,
        );
    }

    public function test_a_block_a_template_already_printed_is_not_added_again(): void
    {
        $page = '<html><head>'.self::HEAD.'</head><body></body></html>';

        $this->assertSame($page, Injector::inject($page, self::HEAD));
    }

    public function test_it_leaves_fragments_and_non_html_alone(): void
    {
        $this->assertSame('<div>partial</div>', Injector::inject('<div>partial</div>', self::HEAD));
        $this->assertSame('<header>x</header>', Injector::inject('<header>x</header>', self::HEAD));
        $this->assertFalse(Injector::isPage('<html><head></head></html>', 'application/json'));
        $this->assertTrue(Injector::isPage('<html><head></head></html>', 'text/html; charset=UTF-8'));
        $this->assertFalse(Injector::isPage('<div>partial</div>', 'text/html'));
    }
}
