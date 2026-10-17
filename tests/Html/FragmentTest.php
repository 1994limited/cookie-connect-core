<?php

namespace NineteenNinetyFour\CookieConnect\Core\Tests\Html;

use NineteenNinetyFour\CookieConnect\Core\Html\Element;
use NineteenNinetyFour\CookieConnect\Core\Html\Fragment;
use PHPUnit\Framework\TestCase;

class FragmentTest extends TestCase
{
    public function test_untouched_html_comes_back_byte_for_byte(): void
    {
        $html = "<div class=a>\n<p>Hi <b>there</b></p><!-- <script>no</script> --><style>img{x:1}</style><textarea><iframe></textarea>\n</div>";

        $this->assertSame($html, Fragment::rewrite($html, fn (Element $e) => $e));
    }

    public function test_it_finds_scripts_iframes_and_images_but_not_inside_scripts_or_comments(): void
    {
        $html = '<script>var s = "<img src=x>";</script><!-- <iframe src="a"></iframe> --><iframe src="b"></iframe><img src="c" alt="1 > 0"><header></header>';

        $found = array_map(fn (Element $e) => $e->name.':'.($e->get('src') ?? ''), Fragment::find($html));

        $this->assertSame(['script:', 'iframe:b', 'img:c'], $found);
    }

    public function test_it_rewrites_opening_tags_and_keeps_contents(): void
    {
        $html = "<SCRIPT async SRC='https://x.test/a.js?a=1&amp;b=2' data-x=\"say &quot;hi&quot;\"></SCRIPT><script>if (a > b) { go(); }</script>";

        $out = Fragment::rewrite($html, fn (Element $e) => $e->set('type', 'text/plain'));

        $this->assertSame(
            '<script async src="https://x.test/a.js?a=1&amp;b=2" data-x="say &quot;hi&quot;" type="text/plain"></SCRIPT>'
            .'<script type="text/plain">if (a > b) { go(); }</script>',
            $out,
        );
    }

    public function test_values_are_read_decoded_and_written_encoded(): void
    {
        [$element] = Fragment::find('<img src="a.jpg?x=1&amp;y=2" alt=\'"quoted"\'>');

        $this->assertSame('a.jpg?x=1&y=2', $element->get('src'));
        $this->assertSame('"quoted"', $element->get('alt'));
        $this->assertSame('<img src="a.jpg?x=1&amp;y=2" alt="&quot;quoted&quot;">', $element->openingTag());
    }

    public function test_an_unclosed_script_keeps_the_rest(): void
    {
        $html = '<p>a</p><script>var x = 1;';

        $this->assertSame('<p>a</p><script type="text/plain">var x = 1;</script>', Fragment::rewrite($html, fn (Element $e) => $e->set('type', 'text/plain')));
    }

    public function test_rename_add_class_and_remove(): void
    {
        $element = new Element('iframe', ['src' => 'https://a.test', 'class' => 'video']);
        $element->rename('src', 'data-src')->addClass('optanon-category-C0004 video')->remove('missing');

        $this->assertSame('<iframe data-src="https://a.test" class="video optanon-category-C0004">', $element->openingTag());
    }
}
