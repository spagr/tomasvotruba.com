<?php declare(strict_types=1);

namespace TomasVotruba\Website\Packagist;

use TomasVotruba\Website\Exception\ShouldNotHappenException;
use TomasVotruba\Website\Json\FileToJsonLoader;

final class PackageMonthlyDownloadsProvider
{
    /**
     * @var string
     */
    private const URL_DOWNLOAD_STATS = 'https://packagist.org/packages/%s/stats/all.json?average=monthly';

    /**
     * @var mixed[][]
     */
    private $interveningPackagesDownloads = [];

    /**
     * @var string[][]
     */
    private $interveningDependencies = [
        // https://packagist.org/packages/phpstan/phpstan
        'phpstan/phpstan' => [
            'nette/bootstrap',
            'nette/di',
            'nette/robot-loader',
            'nette/utils',
            'symfony/console',
            'symfony/finder',
            // consequently
            'nette/php-generator',
            'nette/neon',
            'nette/finder',
            'symfony/contracts',
            'symfony/polyfill-mbstring',
        ],
        // https://packagist.org/packages/friendsofphp/php-cs-fixer
        'friendsofphp/php-cs-fixer' => [
            'symfony/console',
            'symfony/event-dispatcher',
            'symfony/filesystem',
            'symfony/finder',
            'symfony/options-resolver',
            'symfony/polyfill-php70',
            'symfony/polyfill-php72',
            'symfony/process',
            'symfony/stopwatch',
            // consequently
            'symfony/contracts',
            'symfony/polyfill-mbstring',
            'symfony/polyfill-ctype',
        ],
        // https://packagist.org/packages/robmorgan/phinx
        'robmorgan/phinx' => [
            'cakephp/collection',
            'cakephp/database',
            'symfony/console',
            'symfony/config',
            'symfony/yaml',
            // consequently
            'cakephp/cache',
            'cakephp/core',
            'cakephp/datasource',
            'cakephp/log',
            'symfony/contracts',
            'symfony/polyfill-mbstring',
            'symfony/filesystem',
            'symfony/polyfill-ctype',
        ],
        // https://packagist.org/packages/laravel/framework
        'laravel/framework' => [
            'symfony/console',
            'symfony/debug',
            'symfony/finder',
            'symfony/http-foundation',
            'symfony/http-kernel',
            'symfony/process',
            'symfony/routing',
            'symfony/var-dumper',
            // consequently
            'symfony/contracts',
            'symfony/polyfill-mbstring',
            'symfony/contracts',
            'symfony/event-dispatcher',
            'symfony/http-foundation',
            'symfony/debug',
            'symfony/polyfill-ctype',
            'symfony/polyfill-php72',
        ],
    ];

    /**
     * @var FileToJsonLoader
     */
    private $fileToJsonLoader;

    public function __construct(FileToJsonLoader $fileToJsonLoader)
    {
        $this->fileToJsonLoader = $fileToJsonLoader;
    }

    /**
     * @return int[]
     */
    public function provideForPackage(string $packageName): array
    {
        $values = $this->getRawMonthlyDownloadsForPackage($packageName);

        return $this->correctInterveningPackages($values, $packageName);
    }

    /**
     * @return int[]
     */
    private function getRawMonthlyDownloadsForPackage(string $packageName): array
    {
        $url = sprintf(self::URL_DOWNLOAD_STATS, $packageName);
        $json = $this->fileToJsonLoader->load($url);

        if (! isset($json['values'])) {
            throw new ShouldNotHappenException();
        }

        $values = $json['values'];
        // last value is uncompleted month, not needed
        array_pop($values);

        // put the highest first to keep convention
        return array_reverse($values);
    }

    private function correctInterveningPackages(array $monthlyDownloads, string $packageName): array
    {
        foreach ($this->interveningDependencies as $interveningDependency => $dependingPackages) {
            if (! in_array($packageName, $dependingPackages, true)) {
                continue;
            }

            $interveningDownloads = $this->getInterveningPackageDownloads($interveningDependency);
            foreach ($monthlyDownloads as $key => $value) {
                // too old
                if (! isset($interveningDownloads[$key])) {
                    break;
                }

                // correction here!
                $monthlyDownloads[$key] = $value - $interveningDownloads[$key];
            }
        }

        return $monthlyDownloads;
    }

    private function getInterveningPackageDownloads(string $packageName): array
    {
        if (isset($this->interveningPackagesDownloads[$packageName])) {
            return $this->interveningPackagesDownloads[$packageName];
        }

        $this->interveningPackagesDownloads[$packageName] = $this->getRawMonthlyDownloadsForPackage($packageName);

        return $this->interveningPackagesDownloads[$packageName];
    }
}
