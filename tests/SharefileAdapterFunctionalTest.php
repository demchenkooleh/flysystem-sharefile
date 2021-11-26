<?php

namespace Citrix\FlysystemSharefile\Tests;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use League\Flysystem\Util;
use League\Flysystem\Config;

class SharefileAdapterFunctionalTest extends TestCase
{
    use ArraySubsetAsserts;

    /**
     * @test
     * @dataProvider  filesProvider
     */
    public function testCanFindFiles(string $name): void
    {
        $contents = $this->faker()->text;
        $this->createResourceFile($name, $contents);

        $this->assertTrue((bool) $this->hasResource($name));
    }

    /**
     * @dataProvider  withSubFolderProvider
     */
    public function testFindFilesInSubfolders(string $path): void
    {
        $contents = $this->faker()->text;
        $this->createResourceFile($path, $contents);

        $this->assertTrue((bool) $this->hasResource($path));
    }

    /**
     * @test
     * @dataProvider filesProvider
     */
    public function testCanRead(string $name): void
    {
        $contents = $this->faker()->text;

        $this->createResourceFile($name, $contents);

        $response = $this->adapter->read($name);

        self::assertArraySubset([
            'type' => 'file',
            'path' => $name,
            'contents' => $contents,
        ], $response);
    }

    /**
     * @test
     * @dataProvider filesProvider
     */
    public function testCanReadstream(string $name): void
    {
        $contents = $this->faker()->text;
        $this->createResourceFile($name, $contents);

        $response = $this->adapter->readstream($name);

        self::assertArraySubset([
            'type' => 'file',
            'path' => $name,
        ], $response);

        $this->assertIsResource($response['stream']);
    }

    /**
     * @test
     * @dataProvider withSubFolderProvider
     */
    public function testCanListContents(string $path): void
    {
        // No file
        $this->createResourceDir(UTIL::dirname($path));
        $this->assertCount(0, $this->adapter->listContents(Util::dirname($path)));

        // Single file
        $contents = $this->faker()->text;
        $this->createResourceFile($path, $contents);

        $this->assertCount(1, $this->adapter->listContents(Util::dirname($path)));

        // Multiple files
        $this->createResourceFile(str_replace('/', '/first copy of ', $path), $contents);
        $this->createResourceFile(str_replace('/', '/second copy of ', $path), $contents);

        $this->assertCount(3, $this->adapter->listContents(Util::dirname($path)));
    }

    /**
     * @test
     * @dataProvider withSubFolderProvider
     */
    public function testCanGetMetadata(string $path): void
    {
        $contents = $this->faker()->text;
        $this->createResourceFile($path, $contents);

        self::assertArraySubset(['type' => 'file', 'path' => $path], $this->adapter->getMetadata($path));
    }

    public function testItCanGetSize()
    {
        $contents = $this->faker()->text;
        $this->createResourceFile('foo', $contents);

        $this->assertSame(strlen($contents), $this->adapter->getSize('foo')['size']);
    }

    public function testItCanGetMimetypes(): void
    {
        $this->createResourceFile('foo.json', 'bar');

        $this->assertSame('application/json', $this->adapter->getMimetype('foo.json')['mimetype']);
    }

    public function testItCanGetTimestamps(): void
    {
        $this->createResourceFile('foo', 'bar');

        $this->assertLessThan(time() + 1, $this->adapter->getTimestamp('foo')['timestamp']);
        $this->assertGreaterThan($this->adapter->getTimestamp('foo')['timestamp'], time() - 60);
    }

    /**
     * @test
     * @dataProvider filesProvider
     */
    public function testCanWrite(string $filename): void
    {
        $contents = $this->faker()->text;

        $result = $this->adapter->write($filename, $contents, new Config);

        self::assertArraySubset([
            'type' => 'file',
            'path' => $filename,
            'contents' => $contents,
            'mimetype' => Util::guessMimeType($filename, $contents),
        ], $result);

        $this->assertEquals($contents, $this->getResourceContent($filename));
    }

    /**
     * @test
     * @dataProvider filesProvider
     */
    public function testCanUpdate(string $filename): void
    {
        $contents = $this->faker()->text;

        $this->createResourceFile($filename, $contents);
        $this->assertEquals($contents, $this->getResourceContent($filename));

        $newContents = $this->faker()->text;
        $result = $this->adapter->update($filename, $newContents, new Config);

        self::assertArraySubset([
            'type' => 'file',
            'path' => $filename,
            'contents' => $newContents,
            'mimetype' => Util::guessMimeType($filename, $contents),
        ], $result);

        $this->assertNotEquals($contents, $this->getResourceContent($filename));
        $this->assertEquals($newContents, $this->getResourceContent($filename));
    }

    /**
     * @test
     * @dataProvider filesProvider
     */
    public function testCanWritestreamAndUpdatestream(string $filename): void
    {
        $contents = $this->faker()->text;

        $stream = fopen('php://memory', 'rb+');
        fwrite($stream, $contents);
        rewind($stream);

        $this->adapter->writeStream($filename, $stream, new Config);
        $this->assertEquals($contents, $this->getResourceContent($filename));

        $newContents = $this->faker()->text;

        $stream = fopen('php://memory', 'rb+');
        fwrite($stream, $newContents);
        rewind($stream);

        $this->adapter->updateStream($filename, $stream, new Config);

        $this->assertNotEquals($contents, $this->getResourceContent($filename));
        $this->assertEquals($newContents, $this->getResourceContent($filename));
    }

    /**
     * @dataProvider filesProvider
     */
    public function testCanRenameFiles(string $filename)
    {
        $this->createResourceFile($filename, 'foo');
        $newFilename = $this->randomFileName();

        $result = $this->adapter->rename($filename, $newFilename);

        $this->assertTrue($result);
        // $this->assertFalse($this->hasResource($filename)); We'll leave this one out for now (see https://community.sharefilesupport.com/citrixsharefile/topics/uploading-files-with-webdav-and-renaming-files-with-api-results-in-empty-files)
        $this->assertTrue($this->hasResource($newFilename));
    }

    /**
     * @dataProvider copyFilesProvider
     */
    public function testCanCopyFiles(string $path, string $newpath): void
    {
        $this->createResourceFile($path, 'foo');
        $this->createResourceDir(Util::dirname($newpath));

        $result = $this->adapter->copy($path, $newpath);

        $this->assertTrue($result);
        $this->assertNotFalse($this->hasResource($path));
        $this->assertNotFalse($this->hasResource($newpath));
        $this->assertEquals($this->getResourceContent($path), $this->getResourceContent($newpath));
    }

    /**
     * @dataProvider filesProvider
     */
    public function testCanDeleteFiles(string $filename): void
    {
        $this->createResourceFile($filename, 'foo');

        $result = $this->adapter->delete($filename);

        $this->assertTrue($result);
        $this->assertFalse($this->hasResource($filename));
    }

    /**
     * @dataProvider filesProvider
     */
    public function testCanCreateAndDeleteDirectories(string $filename): void
    {
        $path = substr($filename, 0, -4);

        $result = $this->adapter->createDir($path, new Config);
        $this->assertTrue($this->hasResource($path));
        self::assertArraySubset(['type' => 'dir', 'path' => $path], $result);

        $result = $this->adapter->deleteDir($path);
        $this->assertTrue($result);
        $this->assertFalse($this->hasResource($path));
    }

    /**
     * @dataProvider filesProvider
     */
    public function testCanPut(string $filename): void
    {
        $contents = $this->faker()->text;
        $this->createResourceFile($filename, $contents);

        $newContents = $this->faker()->text;

        $result = $this->adapter->put($filename, $newContents, new Config);

        self::assertArraySubset([
            'type' => 'file',
            'path' => $filename,
            'contents' => $newContents,
            'mimetype' => Util::guessMimeType($filename, $contents),
        ], $result);

        $this->assertEquals($newContents, $this->getResourceContent($filename));
    }

    /**
     * @dataProvider filesProvider
     */
    public function testCanReadAndDeleteFiles(string $filename): void
    {
        $contents = $this->faker()->text;
        $this->createResourceFile($filename, $contents);

        $response = $this->adapter->readAndDelete($filename);

        $this->assertSame($contents, $response);

        $this->assertFalse($this->hasResource($filename));
    }

    public function testCanFail(): void
    {
        $this->assertFalse($this->adapter->has('/Foo'));
        $this->assertFalse($this->adapter->read('/Foo'));
        $this->assertFalse($this->adapter->listContents('/Foo'));
        $this->assertFalse($this->adapter->getMetadata('/Foo'));
        $this->assertFalse($this->adapter->getSize('/Foo'));
        $this->assertFalse($this->adapter->getMimetype('/Foo'));
        $this->assertFalse($this->adapter->getTimestamp('/Foo'));
        $this->assertFalse($this->adapter->rename('/Foo', '/Bar'));
        $this->assertFalse($this->adapter->copy('/Foo', '/Bar'));
        $this->assertFalse($this->adapter->delete('/Foo'));
        $this->assertFalse($this->adapter->createDir('/Foo/Bar'));
        $this->assertFalse($this->adapter->delete('/Foo'));
        $this->assertFalse($this->adapter->readAndDelete('/Foo'));
    }
}
