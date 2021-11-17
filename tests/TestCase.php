<?php

namespace Citrix\FlysystemSharefile\Tests;

use Faker\Factory;
use Faker\Generator;
use Sabre\DAV\Exception;
use Citrix\Sharefile\Client;
use Sabre\HTTP\HttpException;
use Sabre\DAV\Client as WebDAVClient;
use Citrix\FlysystemSharefile\SharefileAdapter;
use PHPUnit\Framework\TestCase as PHPUnit_Framework_Testcase;

abstract class TestCase extends PHPUnit_Framework_Testcase
{
    protected string $sharefileRoot;

    protected SharefileAdapter $adapter;

    protected WebDAVClient $webdavClient;

    protected string $webdavRoot;

    /**
     * @return void
     * @throws \Exception
     */
    public function setUp(): void
    {
        if (!$this->checkEnvironmentVariables()) {
            $this->markTestSkipped(
                'No ShareFile credentials are found. ' .
                'Fill in your ShareFile credentials under section <PHP> in the file ' .
                'phpunit.xml.dist in the project root folder.'
            );
        }

        $this->initializeAdapter();
        $this->initializeResource();

        $this->clearResources();
        $this->createResourceDir('/');
    }

    /**
     * Tear down tests.
     */
    public function tearDown(): void
    {
        unset($this->adapter);
        $this->clearResources();
    }

    /**
     * Check if all the necessary environment variables are filled.
     *
     * @return bool
     */
    protected function checkEnvironmentVariables(): bool
    {
        return !$this->varEmpty(
            getenv('SHAREFILE_ROOT'),
            getenv('SHAREFILE_HOSTNAME'),
            getenv('SHAREFILE_CLIENT_ID'),
            getenv('SHAREFILE_CLIENT_SECRET'),
            getenv('SHAREFILE_USERNAME'),
            getenv('SHAREFILE_PASSWORD'),
            getenv('WEBDAV_ROOT')
        );
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function initializeAdapter(): void
    {
        $this->sharefileRoot = getenv('SHAREFILE_ROOT');

        $sharefileClient = new Client(
            getenv('SHAREFILE_HOSTNAME'),
            getenv('SHAREFILE_CLIENT_ID'),
            getenv('SHAREFILE_CLIENT_SECRET'),
            getenv('SHAREFILE_USERNAME'),
            getenv('SHAREFILE_PASSWORD')
        );

        $this->adapter = new SharefileAdapter($sharefileClient, $this->sharefileRoot, false);
    }

    /**
     * Initialize WebDav resource to ShareFile.
     */
    protected function initializeResource(): void
    {
        $this->webdavRoot = getenv('WEBDAV_ROOT');

        $guessedUrl = 'https://' . str_replace(
            'sharefile.com',
            'sharefile-webdav.com',
            strtolower(getenv('SHAREFILE_HOSTNAME'))
        );

        $this->webdavClient = new WebDAVClient([
            'baseUri' => getenv('WEBDAV_URL') ?: $guessedUrl,
            'userName' => getenv('WEBDAV_USERNAME') ?: getenv('SHAREFILE_USERNAME'),
            'password' => getenv('WEBDAV_PASSWORD') ?: getenv('SHAREFILE_PASSWORD'),
        ]);
    }

    /**
     * Get the contents of a resource file,.
     *
     * @param string $path Path of the file
     *
     * @return string
     */
    protected function getResourceContent(string $path): string
    {
        $location = $this->getResourceLocation($path);

        return $this->webdavClient->request('GET', $location)['body'];
    }

    /**
     * Checks if resource file exists.
     *
     * @param string $path Path of the file
     *
     * @return bool
     */
    protected function hasResource(string $path): bool
    {
        $location = $this->getResourceLocation($path);

        try {
            $this->webdavClient->propFind($location, ['{DAV:}displayname']);

            return true;
        } catch (Exception|HttpException $e) {
            return false;
        }
    }

    /**
     * Calculates location URL of a resource path.
     *
     * @param string $path Resource path
     *
     * @return string
     */
    protected function getResourceLocation(string $path): string
    {
        $path = implode(
            '/',
            array_filter([
                trim($this->webdavRoot, '/'),
                trim($path, '/'),
            ])
        );

        $path = explode('/', $path);
        foreach ($path as $i => $iValue) {
            $path[$i] = rawurlencode($iValue);
        }

        return implode('/', $path);
    }

    /**
     * Creates resource directory.
     *
     * @param string $path Path of the file
     *
     * @return void
     */
    protected function createResourceDir(string $path)
    {
        if (empty($path) || $path === '.') {
            return;
        }
        $location = $this->getResourceLocation($path);
        $this->webdavClient->request('MKCOL', $location);
    }

    /**
     * Creates a resource file with content.
     *
     * @param string $path Path of the file
     * @param string $contents Contents of the file
     *
     * @return void
     */
    protected function createResourceFile(string $path, string $contents = '')
    {
        $this->createResourceDir(dirname($path));

        $location = $this->getResourceLocation($path);

        $this->webdavClient->request('PUT', $location, $contents);
    }

    /**
     * Clears all resources.
     */
    protected function clearResources()
    {
        $location = $this->getResourceLocation('/');

        $this->webdavClient->request('DELETE', $location . '/');
    }

    /**
     * Create random filename.
     */
    protected function randomFileName(): string
    {
        return $this->faker()->name . '.' . $this->faker()->fileExtension;
    }

    /**
     * Return Faker Generator.
     *
     * @return Generator
     */
    protected function faker(): Generator
    {
        return Factory::create();
    }

    /**
     * Check if one ore more variables is empty.
     *
     * @param array ...$args
     *
     * @return bool
     */
    protected function varEmpty(...$args): bool
    {
        $arguments = func_get_args();
        foreach ($arguments as $argument) {
            if (empty($argument)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Provider for filenames.
     *
     * @return array
     */
    public function filesProvider()
    {
        if (getenv('QUICK_ONLINE_TEST') === '1') {
            return [
                ['test.txt'],
            ];
        }

        return [
            ['test.txt'],
            ['test 1.txt'],
            ['test  2.txt'],
            ['тест.txt'],
            [$this->randomFileName()],
            [$this->randomFileName()],
            [$this->randomFileName()],
            [$this->randomFileName()],
            [$this->randomFileName()],
        ];
    }

    /**
     * Provider for filenames with subfolder.
     *
     * @return array
     */
    public function withSubFolderProvider()
    {
        if (getenv('QUICK_ONLINE_TEST') === '1') {
            return [
                ['test/test.txt'],
            ];
        }

        return [
            ['test/test.txt'],
            ['тёст/тёст.txt'],
            ['test 1/test.txt'],
            ['test/test 1.txt'],
            ['test  1/test  2.txt'],
            [$this->faker()->word . '/' . $this->randomFileName()],
            [$this->faker()->word . '/' . $this->randomFileName()],
            [$this->faker()->word . '/' . $this->randomFileName()],
            [$this->faker()->word . '/' . $this->randomFileName()],
            [$this->faker()->word . '/' . $this->randomFileName()],
        ];
    }

    /**
     * Provider for copying files.
     *
     * @return array
     */
    public function copyFilesProvider()
    {
        $provider = [];
        foreach ($this->filesProvider() as $filename) {
            $filename = $filename[0];
            $provider[] = [$filename, '/target/' . $filename];
            $provider[] = [$filename, 'copy of ' . basename($filename)];
        }

        return $provider;
    }
}
