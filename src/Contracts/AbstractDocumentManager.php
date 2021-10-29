<?php

namespace Nagi\LaravelWopi\Contracts;

use Closure;
use Exception;
use Nagi\LaravelWopi\Facades\Discovery;

abstract class AbstractDocumentManager
{
    /**
     * No properties should be set to null. If you do not wish
     * to set a property, simply omit it from the response
     * and WOPI clients will use the default value.
     */
    protected static array $propertyMethodMapping = [
        // Required proprties
        'BaseFileName' => 'basename',
        'OwnerId' => 'owner',
        'Size' => 'size',
        'Version' => 'version',
        'UserId' => 'userId',

        // Permission properties
        'ReadOnly' => 'isReadOnly',
        'UserCanNotWriteRelative' => 'userCanNotWriteRelative',
        'UserCanRename' => 'canUserRename',
        'UserCanWrite' => 'canUserWrite',

        // File URl proprties
        'CloseUrl' => 'closeUrl',
        'DownloadUrl' => 'downloadUrl',
        'FileVersionUrl' => 'getFileVersionUrl',

        // Sharable
        'FileSharingUrl' => 'sharingUrl',
        'SupportedShareUrlTypes' => 'supportedShareUrlTypes',

        // Override getting file content url
        'FileUrl' => 'getFileContentUrl',

        // Override getting file extension logic
        'FileExtension' => 'extension',

        // Meta data
        'LastModifiedTime' => 'lastModifiedTime',

        // hash
        'SHA256' => 'sha256Hash',

        // Disable Printing
        'DisablePrint' => 'disablePrint',
        'HidePrintOption' => 'hidePrintOption',

        // Disable Exporing
        'DisableExport' => 'disableExport',
        'HideExportOption' => 'hideExportOption',

        // Disable copy
        'DisableCopy' => 'disableCopy',

        // Override supported features
        'SupportsDeleteFile' => 'supportDelete',
        'SupportsLocks' => 'supportLocks',
        'SupportsUpdate' => 'supportUpdate',
        'SupportsRename' => 'supportRename',
        // 'SupportsUserInfo' => 'supportUserInfo',

    ];

    /**
     * Resloved User Id.
     *
     * @var string|Closure
     */
    protected $userId = '';

    /**
     * Preform look up for the file/document.
     *
     * @param string $fileId unique ID, Represent a single file and URL safe.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    abstract public static function find(string $fileId): static;

    /**
     * Preform look up for the file/document by filename.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    abstract public static function findByName(string $filename): static;

    /**
     * Create new document instace on the host.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    abstract public static function create(string $name, string $content, int $size): static;

    /**
     * Unique id that identifies single file could be numbers
     * or string, but also should be url safe. It should
     * match fileId parameter passed to static::find.
     */
    abstract public function id(): string;

    public function supportLocks(): bool
    {
        /** @var ConfigRepositoryInterface */
        $config = app(ConfigRepositoryInterface::class);

        return $config->supportLocks();
    }

    public function supportUpdate(): bool
    {
        /** @var ConfigRepositoryInterface */
        $config = app(ConfigRepositoryInterface::class);

        return $config->supportUpdate();
    }

    public function supportRename(): bool
    {
        /** @var ConfigRepositoryInterface */
        $config = app(ConfigRepositoryInterface::class);

        return $config->supportRename();
    }

    public function supportDelete(): bool
    {
        /** @var ConfigRepositoryInterface */
        $config = app(ConfigRepositoryInterface::class);

        return $config->supportDelete();
    }

    /**
     * Name of the file, including extension, without a path. Used
     * for display in user interface (UI), and determining
     * and  determining the extension of the file.
     */
    abstract public function basename(): string;

    /**
     * Uniquely identifies the owner of the file. In most
     * cases, the user who uploaded or created the file
     * should be considered the owner.
     */
    abstract public function owner(): string;

    /**
     * The size of the file in bytes, expressed
     * as a long, a 64-bit signed integer.
     */
    abstract public function size(): int;

    /**
     * The current version of the file based on the server’s file
     * version schema, as a string. This value must change when
     * the file changes, and version values must never repeat.
     */
    abstract public function version(): string;

    /**
     * Binary contents of the file. Not the url!
     */
    abstract public function content(): string;

    /**
     * Determin if the document is locked or not.
     */
    abstract public function isLocked(): bool;

    /**
     * Get current lock on the document.
     */
    abstract public function getLock(): string;

    /**
     * Change document contents.
     */
    abstract public function put(string $content, array $editorsIds = []);

    /**
     * Delete the lock on the document.
     */
    abstract public function deleteLock(): void;

    /**
     * Lock the document prevent it from being altered or deleted.
     */
    abstract public function lock(string $lockId): void;

    /**
     * Manually set user id.
     */
    public function setUserId(string $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * Value uniquely identifying the user currently accessing the
     * file. Can be set to the current logged user ideally.
     */
    public function userId(): string
    {
        $defaultUserId = $this->defaultUser();

        if ($this->userId instanceof Closure) {
            $userId = call_user_func($this->userId);

            return empty($userId) ? $defaultUserId : $userId;
        }

        return empty($this->userId) ? $defaultUserId : $this->userId;
    }

    /**
     * When there's no user id this value will be used.
     */
    protected function defaultUser(): string
    {
        return 'Unknown User';
    }

    /**
     * Indicates that the user has permission to alter the
     * file. Setting this to true tells the WOPI client
     * that it can call PutFile on behalf of the user.
     *
     * @default-value false
     */
    public function canUserWrite(): bool
    {
        return true;
    }

    /**
     * Manually set user id using closure.
     */
    public function getUserUsing(Closure $calback): static
    {
        $this->userId = $calback;

        return $this;
    }

    public function getUrlForAction(string $action): string
    {
        $extension = method_exists($this, 'extension')
            ? $this->extension()
            : optional(pathinfo($this->basename()))['extension'];

        $url = route('wopi.checkFileInfo', [
            'file_id' => $this->id(),
        ]);

        $actionUrl = optional(Discovery::discoverAction($extension, $action));

        if (is_null($actionUrl['urlsrc'])) {
            // todo proper exception
            throw new Exception('Unsupported action for this extension');
        }

        return "{$actionUrl['urlsrc']}WOPISrc={$url}";
    }

    /**
     * Get CheckfileInfo response proprites based
     * on implemented interfaces/features.
     */
    public function getResponseProprties(): array
    {
        return  collect(static::$propertyMethodMapping)
                ->flatMap(function (string $methodName, string $propertyName) {
                    if (method_exists($this, $methodName)) {
                        return [
                             $propertyName => $this->$methodName(),
                        ];
                    }
                })
                ->filter(fn ($value) => $value !== null)
                ->toArray();
    }
}