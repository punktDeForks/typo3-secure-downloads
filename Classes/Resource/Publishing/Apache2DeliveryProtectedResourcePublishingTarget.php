<?php
declare(strict_types=1);
namespace Bitmotion\SecureDownloads\Resource\Publishing;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Bitmotion GmbH (typo3-ext@bitmotion.de)
 *
 *  All rights reserved
 *
 *  This script is part of the Typo3 project. The Typo3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
use Bitmotion\SecureDownloads\Security\Authorization\Resource\AccessRestrictionPublisherInterface;
use TYPO3\CMS\Core\Resource\ResourceInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Apache2DeliveryProtectedResourcePublishingTarget extends AbstractResourcePublishingTarget
{
    /**
     * @var AccessRestrictionPublisherInterface
     */
    protected $accessRestrictionPublisher;

    public function injectAccessRestrictionPublisher(AccessRestrictionPublisherInterface $accessRestrictionPublisher)
    {
        $this->accessRestrictionPublisher = $accessRestrictionPublisher;
    }

    /**
     * Publishes a persistent resource to the web accessible resources directory
     *
     * @param ResourceInterface $resource The resource to publish
     *
     * @return mixed Either the web URI of the published resource or FALSE if the resource source file doesn't exist or
     *     the resource could not be published for other reasons
     */
    public function publishResource(ResourceInterface $resource)
    {
        $this->setResourcesSourcePath($this->getResourcesSourcePathByResourceStorage($resource->getStorage()));
        $publishedResourcePathAndFilename = $this->buildResourcePublishPathAndFilename($resource);
        $publishedResourceWebUri = $this->buildResourceWebUri($resource);

        if (!file_exists($publishedResourcePathAndFilename)) {
            $unpublishedResourcePathAndFilename = $this->getResourceSourcePathAndFileName($resource);
            if ($unpublishedResourcePathAndFilename === false) {
                return false;
            }
            $this->mirrorFile($unpublishedResourcePathAndFilename, $publishedResourcePathAndFilename);
        }

        return $publishedResourceWebUri;
    }

    protected function buildResourcePublishPathAndFilename(ResourceInterface $resource): string
    {
        return $this->resourcesPublishingPath . $this->buildPublishingPathPartBySourcePath($this->getResourceSourcePathAndFileName($resource));
    }

    protected function buildPublishingPathPartBySourcePath(string $sourcePath): string
    {
        $contextHash = '0';
        if ($this->getRequestContext()->isUserLoggedIn()) {
            $contextHash = sha1($this->getRequestContext()->getAccessToken());
        }
        $pathParts = array_merge(
            [$this->getRequestContext()->getLocationId()],
            [$contextHash],
            [sha1(dirname($sourcePath))],
            [basename($sourcePath)]
        );

        return implode('/', $pathParts);
    }

    /**
     * @return bool|string
     */
    protected function getResourceSourcePathAndFileName(ResourceInterface $resource)
    {
        $pathAndFilename = $this->resourcesSourcePath . ltrim($resource->getIdentifier(), '/');

        return (file_exists($pathAndFilename)) ? $pathAndFilename : false;
    }

    protected function buildResourceWebUri(ResourceInterface $resource): string
    {
        // TODO: PATH_site is deprecated since TYPO3 9.0 use TYPO3\CMS\Core\Core\Environment::getPublicPath() . '/' instead
        return substr($this->buildResourcePublishPathAndFilename($resource), strlen(PATH_site));
    }

    protected function mirrorFile(string $fileSourcePath, string $fileTargetPath)
    {
        $publishingDirectory = dirname($fileTargetPath) . '/';
        $this->assureDirectoryPathExists($publishingDirectory);
        if ($this->getRequestContext()->isUserLoggedIn()) {
            $this->accessRestrictionPublisher->publishAccessRestrictionsForPath($publishingDirectory);
        }
        symlink($fileSourcePath, $fileTargetPath);
    }

    protected function assureDirectoryPathExists(string $absolutePath)
    {
        if (!is_dir($absolutePath)) {
            // TODO: PATH_site is deprecated since TYPO3 9.0 use TYPO3\CMS\Core\Core\Environment::getPublicPath() . '/' instead
            GeneralUtility::mkdir_deep(PATH_site, substr($absolutePath, strlen(PATH_site)));
        }
    }

    /**
     * Builds a delivery URI from a URI which is in document root but protected through the webserver
     *
     * @return string|bool
     */
    public function publishResourceUri(string $resourceUri)
    {
        // TODO: PATH_site is deprecated since TYPO3 9.0 use TYPO3\CMS\Core\Core\Environment::getPublicPath() . '/' instead
        $this->setResourcesSourcePath(PATH_site);
        $publishedResourcePathAndFilename = $this->buildResourceUriPublishPathAndFilename($resourceUri);
        $publishedResourceWebUri = $this->buildResourceUriWebUri($resourceUri);

        if (!file_exists($publishedResourcePathAndFilename)) {
            $unpublishedResourcePathAndFilename = $this->getResourceUriSourcePathAndFileName($resourceUri);
            if ($unpublishedResourcePathAndFilename === false) {
                return false;
            }
            $this->mirrorFile($unpublishedResourcePathAndFilename, $publishedResourcePathAndFilename);
        }

        return $publishedResourceWebUri;
    }

    protected function buildResourceUriPublishPathAndFilename(string $resourceUri): string
    {
        return $this->resourcesPublishingPath . $this->buildPublishingPathPartBySourcePath($this->getResourceUriSourcePathAndFileName($resourceUri));
    }

    protected function getResourceUriSourcePathAndFileName(string $resourceUri): string
    {
        //TODO: Check if we need to check for backpaths here
        // TODO: PATH_site is deprecated since TYPO3 9.0 use TYPO3\CMS\Core\Core\Environment::getPublicPath() . '/' instead
        return PATH_site . $resourceUri;
    }

    protected function buildResourceUriWebUri(string $resourceUri): string
    {
        // TODO: PATH_site is deprecated since TYPO3 9.0 use TYPO3\CMS\Core\Core\Environment::getPublicPath() . '/' instead
        return substr($this->buildResourceUriPublishPathAndFilename($resourceUri), strlen(PATH_site));
    }

    /**
     * Sets the publishing path depending on the resources path being in document root or not
     */
    protected function detectResourcesPublishingPath()
    {
        if ($this->resourcesPublishingPath === null) {
            // TODO: PATH_site is deprecated since TYPO3 9.0 use TYPO3\CMS\Core\Core\Environment::getPublicPath() . '/' instead
            $this->resourcesPublishingPath = PATH_site . 'typo3temp/secure_downloads/';
            $this->assureDirectoryPathExists($this->resourcesPublishingPath);
        }
    }
}
