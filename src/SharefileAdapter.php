<?php

namespace Citrix\FlysystemSharefile;

use Exception;
use League\Flysystem\Util;
use Citrix\Sharefile\Client;
use League\Flysystem\Config;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\Polyfill\NotSupportingVisibilityTrait;

class SharefileAdapter extends AbstractAdapter
{
    use NotSupportingVisibilityTrait;

    private const CAN_ADD_FOLDER = 'CanAddFolder';
    private const ADD_NODE = 'CanAddNode';
    private const CAN_VIEW = 'CanView';
    private const CAN_DOWNLOAD = 'CanDownload';
    private const CAN_UPLOAD = 'CanUpload';
    private const CAN_SEND = 'CanSend';
    private const CAN_DELETE_CURRENT_ITEM = 'CanDeleteCurrentItem';
    private const CAN_DELETE_CHILD_ITEMS = 'CanDeleteChildItems';
    private const CAN_MANAGE_PERMISSIONS = 'CanManagePermissions';
    private const CAN_CREATEOFFICE_DOCUMENTS = 'CanCreateOfficeDocuments';
    public const PERSONAL_FOLDERS = 'Personal Folders';

    protected Client $client;

    protected bool $returnShareFileItem;

    public function __construct(Client $client, string $prefix = '', bool $returnShareFileItem = false)
    {
        $this->client = $client;

        $this->returnShareFileItem = $returnShareFileItem;

        $this->setPathPrefix($prefix);
    }

    /**
     * @param string $path
     * @return array|false
     * @throws Exception
     */
    public function has($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * @param string $path
     * @return array|false
     * @throws Exception
     */
    public function read($path)
    {
        if (!$item = $this->getItemByPath($path)) {
            return false;
        }

        if (!$this->checkAccessControl($item, self::CAN_DOWNLOAD)) {
            return false;
        }

        $contents = $this->client->getItemContents($item['Id']);

        return $this->mapItemInfo($item, Util::dirname($path), $contents);
    }

    /**
     * @param $path
     * @return array|false
     * @throws Exception
     */
    public function readStream($path)
    {
        if (!$item = $this->getItemByPath($path)) {
            return false;
        }

        if (!$this->checkAccessControl($item, self::CAN_DOWNLOAD)) {
            return false;
        }

        $url = $this->client->getItemDownloadUrl($item['Id']);

        $stream = fopen($url['DownloadUrl'], 'rb');

        return $this->mapItemInfo($item, Util::dirname($path), null, $stream);
    }

    /**
     * @param string $directory
     * @param bool $recursive
     * @return array|false
     * @throws Exception
     */
    public function listContents($directory = '', $recursive = false)
    {
        if ($directory === '/' || $directory === '') {
            $homeDir = $this->client->getHomeFolder();
            $directory = $homeDir['Name'] ?? $directory;
        }

        if (!$item = $this->getItemByPath($directory)) {
            return false;
        }

        return $this->buildItemList($item, $directory, $recursive);
    }

    /**
     * @param string $path
     * @return array|false
     * @throws Exception
     */
    public function getMetadata($path)
    {
        if (!$item = $this->getItemByPath($path)) {
            return false;
        }
        $metadata = $this->mapItemInfo($item, Util::dirname($path));

        if (in_array($path, ['/', ''], true)) {
            $metadata['path'] = $path;
        }

        return $metadata;
    }

    /**
     * @param string $path
     * @return array|false
     * @throws Exception
     */
    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * @param string $path
     * @return array|false
     * @throws Exception
     */
    public function getMimetype($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * @param string $path
     * @return array|false
     * @throws Exception
     */
    public function getTimestamp($path)
    {
        return $this->getmetaData($path);
    }

    /**
     * @param string $path
     * @param $contents
     * @param Config|null $config
     * @return array|bool
     * @throws Exception
     */
    public function write($path, $contents, Config $config = null)
    {
        return $this->uploadFile($path, $contents, true);
    }

    /**
     * @param string $path
     * @param $resource
     * @param Config|null $config
     * @return array|bool
     * @throws Exception
     */
    public function writeStream($path, $resource, Config $config = null)
    {
        return $this->uploadFile($path, $resource, true);
    }

    /**
     * @param string $path
     * @param $contents
     * @param Config|null $config
     * @return array|bool
     * @throws Exception
     */
    public function update($path, $contents, Config $config = null)
    {
        return $this->uploadFile($path, $contents, true);
    }

    /**
     * @param string $path
     * @param $resource
     * @param Config|null $config
     * @return array|bool
     * @throws Exception
     */
    public function updateStream($path, $resource, Config $config = null)
    {
        return $this->uploadFile($path, $resource, true);
    }

    /**
     * @throws Exception
     */
    public function rename($path, $newpath): bool
    {
        if (!$targetFolderItem = $this->getItemByPath(Util::dirname($newpath))) {
            return false;
        }

        if (!$this->checkAccessControl($targetFolderItem, self::CAN_UPLOAD)) {
            return false;
        }

        if (!$item = $this->getItemByPath($path)) {
            return false;
        }

        $data = [
            'FileName' => basename($newpath),
            'Name' => basename($newpath),
            'Parent' => [
                'Id' => $targetFolderItem['Id'],
            ],
        ];

        $this->client->updateItem($item['Id'], $data);

        return is_array($this->has($newpath));
    }

    /**
     * @param string $path
     * @param string $newpath
     * @return bool
     * @throws Exception
     */
    public function copy($path, $newpath): bool
    {
        if (!$targetFolderItem = $this->getItemByPath(Util::dirname($newpath))) {
            return false;
        }

        if (!$this->checkAccessControl($targetFolderItem, self::CAN_UPLOAD)) {
            return false;
        }

        if (!$item = $this->getItemByPath($path)) {
            return false;
        }

        if (strcasecmp(Util::dirname($path), Util::dirname($newpath)) !== 0 &&
            strcasecmp(basename($path), basename($newpath)) === 0) {
            $this->client->copyItem($targetFolderItem['Id'], $item['Id'], true);
        } else {
            $contents = $this->client->getItemContents($item['Id']);
            $this->uploadFile($newpath, $contents, true);
        }

        return is_array($this->has($newpath));
    }

    /**
     * @param string $path
     * @return bool
     * @throws Exception
     */
    public function delete($path): bool
    {
        return $this->deleteDir($path);
    }

    /**
     * @param string $dirname
     * @return bool
     * @throws Exception
     */
    public function deleteDir($dirname): bool
    {
        if (!$item = $this->getItemByPath($dirname)) {
            return false;
        }

        if (!$this->checkAccessControl($item, self::CAN_DELETE_CURRENT_ITEM)) {
            return false;
        }

        $this->client->deleteItem($item['Id']);

        return $this->has($dirname) === false;
    }

    /**
     * @param string $dirname
     * @param Config|null $config
     * @return array|false
     * @throws Exception
     */
    public function createDir($dirname, Config $config = null)
    {
        $parentFolder = Util::dirname($dirname);
        $folder = basename($dirname);

        if (!$parentFolderItem = $this->getItemByPath($parentFolder)) {
            return false;
        }

        if (!$this->checkAccessControl($parentFolderItem, self::CAN_ADD_FOLDER)) {
            return false;
        }

        $this->client->createFolder($parentFolderItem['Id'], $folder, $folder, true);

        return $this->has($dirname);
    }

    /**
     * @param $path
     * @param $contents
     * @return array|false
     * @throws Exception
     */
    public function put($path, $contents)
    {
        return $this->uploadFile($path, $contents, true);
    }

    /**
     * @param $path
     * @return false|mixed
     * @throws Exception
     */
    public function readAndDelete($path)
    {
        if (!$item = $this->getItemByPath($path)) {
            return false;
        }

        if (!$this->checkAccessControl($item, self::CAN_DOWNLOAD) ||
            !$this->checkAccessControl($item, self::CAN_DELETE_CURRENT_ITEM)) {
            return false;
        }

        $itemContents = $this->client->getItemContents($item['Id']);

        $this->delete($path);

        return $itemContents;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Upload a file to ShareFile.
     *
     * @param string $path File path
     * @param resource|string $contents Resource or contents of the file
     * @param bool $overwrite Overwrite file when it exists
     *
     * @return array|false
     * @throws Exception
     */
    protected function uploadFile(string $path, $contents, bool $overwrite = false)
    {
        if (!$parentFolderItem = $this->getItemByPath(dirname($path))) {
            return false;
        }

        if (!$this->checkAccessControl($parentFolderItem, self::CAN_UPLOAD)) {
            return false;
        }

        if (is_string($contents)) {
            $stream = fopen('php://memory', 'rb+');
            fwrite($stream, $contents);
            rewind($stream);
        } else {
            $stream = $contents;
        }

        $this->client->uploadFileStreamed($stream, $parentFolderItem['Id'], basename($path), false, $overwrite);

        if ($metadata = $this->getMetadata($path)) {
            if (is_string($contents)) {
                $metadata['contents'] = $contents;
            }

            return $metadata;
        }

        return false;
    }

    /**
     * Map ShareFile item to FlySystem metadata.
     *
     * @param array $item ShareFile item
     * @param string $path Base path
     * @param string|null $contents Contents of the file (optional)
     * @param mixed|null $stream Resource handle of the file (optional)
     *
     * @return array
     */
    protected function mapItemInfo(array $item, string $path = '', string $contents = null, $stream = null): array
    {
        $timestamp = $item['ClientModifiedDate'] ?? $item['ClientCreatedDate'] ??
            $item['CreationDate'] ?? $item['ProgenyEditDate'] ?? '';
        $timestamp = !empty($timestamp) ? strtotime($timestamp) : false;

        if ($path === '.') {
            $path = '';
        }
        $path = trim($path . '/' . $item['FileName'], '/');

        if ($this->isShareFileApiModelsFile($item)) {
            $mimetype = Util::guessMimeType($item['FileName'], $contents);
            $type = 'file';
        } else {
            $mimetype = 'inode/directory';
            $type = 'dir';
        }
        $pathInfo = $this->getDirnameForItemInfo($path);
        return array_merge(
            [
                'timestamp' => $timestamp,
                'path' => $path,
                'parentId' => $item['Parent']['Id'] ?? null,
                'id' => $item['Id'],
                'odata.type' => $item['odata.type'],
                'mimetype' => $mimetype,
                'dirname' => $pathInfo,
                'extension' => pathinfo($item['FileName'], PATHINFO_EXTENSION),
                'filename' => pathinfo($item['FileName'], PATHINFO_FILENAME),
                'basename' => pathinfo($item['FileName'], PATHINFO_FILENAME),
                'type' => $type,
                'size' => $item['FileSizeBytes'],
                'contents' => !empty($contents) ? $contents : false,
                'stream' => !empty($stream) ? $stream : false,
            ],
            $this->returnShareFileItem ? ['sharefile_item' => $item] : []
        );
    }

    protected function getDirnameForItemInfo(string $path)
    {
        $pathInfo = pathinfo($path, PATHINFO_DIRNAME);
        if ($pathInfo === '.' || $pathInfo === self::PERSONAL_FOLDERS) {
            $pathInfo = '';
        }
        return $pathInfo;
    }

    protected function mapItemList(array $items, string $path): array
    {
        return array_map(
            function ($item) use ($path) {
                return $this->mapItemInfo($item, $path);
            },
            $items
        );
    }

    protected function buildItemList(array $item, string $path, bool $recursive = false): array
    {
        if ($this->isShareFileApiModelsFile($item)) {
            return [];
        }

        $children = $this->client->getItemById($item['Id'], true);

        if (!isset($children['Children']) || count($children['Children']) < 1) {
            return [];
        }

        $children = $this->removeAllExceptFilesAndFolders($children['Children']);

        $itemList = $this->mapItemList($children, $path);

        if ($recursive) {
            $itemListChild = [];
            foreach ($children as $child) {
                $path .= '/' . $child['FileName'];
                $itemListChild[] = $this->buildItemList($child, $path, true);
            }
            $itemList = [...$itemList, ...$itemListChild];
        }

        return $itemList;
    }

    protected function removeAllExceptFilesAndFolders(array $items): array
    {
        return array_filter(
            $items,
            function ($item) {
                return $this->isShareFileApiModelsFolder($item) || $this->isShareFileApiModelsFile($item);
            }
        );
    }

    protected function isShareFileApiModelsFolder(array $item): bool
    {
        return $item['odata.type'] === 'ShareFile.Api.Models.Folder';
    }

    protected function isShareFileApiModelsFile(array $item): bool
    {
        return $item['odata.type'] === 'ShareFile.Api.Models.File';
    }

    /**
     * @param string $path Path of the requested file
     * @return array|false
     * @throws Exception
     */
    protected function getItemByPath(string $path)
    {
        if ($path === '.') {
            $path = '';
        }
        $path = '/' . trim($this->applyPathPrefix($path), '/');

        try {
            $item = $this->client->getItemByPath($path);
            if ($this->isShareFileApiModelsFolder($item) || $this->isShareFileApiModelsFile($item)) {
                return $item;
            }
        } catch (exception $e) {
            throw new Exception($e);
        }

        return false;
    }

    protected function checkAccessControl(array $item, string $rule): bool
    {
        if ($this->isShareFileApiModelsFile($item)) {
            if (isset($item['Parent']['Id'])) {
                $item = $this->client->getItemById((string)$item['Parent']['Id']);
            }
            if ($rule === self::CAN_DELETE_CURRENT_ITEM) {
                $rule = self::CAN_DELETE_CHILD_ITEMS;
            }
        }

        if (isset($item['Info'][$rule])) {
            return $item['Info'][$rule] === true;
        }
        return false;
    }
}
