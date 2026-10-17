<?php

namespace NineteenNinetyFour\CookieConnect\Core\Tests\Providers;

use NineteenNinetyFour\CookieConnect\Core\Category;
use NineteenNinetyFour\CookieConnect\Core\Output\Runtime;
use NineteenNinetyFour\CookieConnect\Core\Providers\Provider;
use NineteenNinetyFour\CookieConnect\Core\Providers\Providers;
use NineteenNinetyFour\CookieConnect\Core\Providers\ProviderSchema;
use NineteenNinetyFour\CookieConnect\Core\Tests\Fixtures\Sites;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class ProviderFilesTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function files(): array
    {
        $files = [];

        foreach (glob(dirname(__DIR__, 2).'/resources/providers/*.yaml') ?: [] as $file) {
            $files[basename($file, '.yaml')] = [$file];
        }

        return $files;
    }

    public function test_the_launch_providers_are_all_there_in_order(): void
    {
        $this->assertSame(
            ['cookiebot', 'usercentrics', 'onetrust', 'cookieyes', 'iubenda', 'termly', 'cookiefirst', 'cookie-script', 'custom'],
            array_keys(Providers::default()->options()),
        );
    }

    #[DataProvider('files')]
    public function test_the_file_is_valid(string $file): void
    {
        $data = Yaml::parseFile($file);

        $this->assertSame([], ProviderSchema::problems($data));
        $this->assertSame(basename($file, '.yaml'), $data['handle'], 'The file is named after its handle.');
    }

    #[DataProvider('files')]
    public function test_it_was_checked_against_the_platform_docs_in_the_last_year(string $file): void
    {
        $provider = Providers::load($file);
        $age = (time() - (int) strtotime($provider->verified)) / 86400;

        $this->assertGreaterThanOrEqual(0, $age, 'verified is in the future.');
        $this->assertLessThan(365, $age, "{$provider->name} was last checked against its docs on {$provider->verified}. Check it again (docs/providers.md) and update verified.");
    }

    #[DataProvider('files')]
    public function test_the_browser_script_has_an_adapter_for_it(string $file): void
    {
        $handle = Providers::load($file)->handle;
        $source = (string) file_get_contents(dirname(__DIR__, 2).'/resources/runtime/cookie-connect.js');

        $this->assertMatchesRegularExpression('/\n    (\''.preg_quote($handle, '/').'\'|'.preg_quote($handle, '/').'): \{/', $source);
    }

    #[DataProvider('files')]
    public function test_it_has_values_and_pasted_snippets_in_the_test_fixtures(string $file): void
    {
        $this->assertArrayHasKey(Providers::load($file)->handle, Sites::all());
    }

    #[DataProvider('files')]
    public function test_every_optional_category_maps_to_something(string $file): void
    {
        $provider = Providers::load($file);

        foreach (Category::optional() as $category) {
            $this->assertNotEmpty($provider->categories[$category->value], "{$provider->handle} has no id for {$category->value}.");
        }
    }

    public function test_the_minified_runtime_is_up_to_date(): void
    {
        $dir = dirname(__DIR__, 2).'/resources/runtime';

        $this->assertFileExists($dir.'/cookie-connect.min.js', 'Run npm run build.');
        $this->assertGreaterThanOrEqual(
            filemtime($dir.'/cookie-connect.js'),
            filemtime($dir.'/cookie-connect.min.js'),
            'cookie-connect.js changed since the last build. Run npm run build.',
        );
        $this->assertStringEndsWith('min.js', Runtime::path());
    }

    public function test_a_site_can_register_its_own_provider(): void
    {
        $providers = Providers::fromDirectory(dirname(__DIR__, 2).'/resources/providers');
        $data = Yaml::parseFile(dirname(__DIR__, 2).'/resources/providers/custom.yaml');
        $data['handle'] = 'acme';
        $data['name'] = 'Acme Consent';
        $providers->register(Provider::fromArray($data));

        $this->assertSame('Acme Consent', $providers->get('acme')?->name);
        $this->assertSame('custom', array_key_last($providers->options()), 'Custom stays last.');
    }
}
