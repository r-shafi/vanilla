<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

namespace Vanilla\Models;

use Garden\Web\RequestInterface;
use Gdn_Session;
use UserModel;
use Vanilla\Addon;
use Vanilla\AddonManager;
use Vanilla\Contracts;
use Vanilla\Dashboard\Models\BannerImageModel;
use Vanilla\FeatureFlagHelper;
use Vanilla\Formatting\Formats\HtmlFormat;
use Vanilla\Forum\Models\CommunityManagement\EscalationModel;
use Vanilla\Logging\ErrorLogger;
use Vanilla\Search\SearchService;
use Vanilla\Site\OwnSite;
use Vanilla\Site\SiteSectionModel;
use Vanilla\Theme\ThemeFeatures;
use Vanilla\Theme\ThemeService;
use Vanilla\Utility\ArrayUtils;
use Vanilla\Web\Asset\DeploymentCacheBuster;
use Vanilla\Formatting\FormatService;
use Vanilla\Web\RoleTokenFactory;

/**
 * A class for gathering particular data about the site.
 */
class SiteMeta implements \JsonSerializable
{
    /** @var string */
    private $host;

    /** @var string */
    private $basePath;

    /** @var string */
    private $assetPath;

    /** @var bool */
    private $debugModeEnabled;

    /** @var bool */
    private $translationDebugModeEnabled;

    /** @var bool */
    private $conversationsEnabled;

    /** @var string */
    private $siteTitle;

    /** @var UserModel $userModel */
    private $userModel;

    /** @var Contracts\ConfigurationInterface */
    private $config;

    /** @var string[] */
    private $allowedExtensions;

    /** @var int */
    private $maxUploadSize;

    /** @var int */
    private $maxUploads;

    /** @var string */
    private $activeThemeKey;

    /** @var int $activeThemeRevisionID */
    private $activeThemeRevisionID;

    /** @var string */
    private $mobileThemeKey;

    /** @var string */
    private $desktopThemeKey;

    /** @var string */
    private $activeThemeViewPath;

    /** @var ThemeFeatures */
    private $themeFeatures;

    /** @var array $themePreview */
    private $themePreview;

    /** @var string */
    private $favIcon;

    /** @var string */
    private $mobileAddressBarColor;

    /** @var string|null */
    private $bannerImage;

    /** @var array */
    private $featureFlags;

    /** @var string */
    private $logo;

    /** @var string */
    private $orgName;

    /** @var string */
    private $cacheBuster;

    /** @var Gdn_Session */
    private $session;

    /** @var string */
    private $reCaptchaKey = "";

    /** @var FormatService */
    private $formatService;

    /** @var int */
    private $editContentTimeout = -1;

    /** @var bool  */
    private $bannedPrivateProfiles = false;

    /** @var SiteMetaExtra[] */
    private $extraMetas = [];

    /**
     * @var int
     */
    private $siteID;

    /** @var string $roleTokenEncoded */
    private $roleTokenEncoded;

    private RequestInterface $request;

    private SiteSectionModel $siteSectionModel;

    /**
     * SiteMeta constructor.
     *
     * @param RequestInterface $request The request to gather data from.
     * @param Contracts\ConfigurationInterface $config The config object.
     * @param SiteSectionModel $siteSectionModel
     * @param DeploymentCacheBuster $deploymentCacheBuster
     * @param ThemeService $themeService
     * @param Gdn_Session $session
     * @param FormatService $formatService
     * @param UserModel $userModel
     * @param AddonManager $addonManager
     * @param OwnSite $site
     * @param RoleTokenFactory $roleTokenFactory
     */
    public function __construct(
        RequestInterface $request,
        Contracts\ConfigurationInterface $config,
        SiteSectionModel $siteSectionModel,
        DeploymentCacheBuster $deploymentCacheBuster,
        ThemeService $themeService,
        Gdn_Session $session,
        FormatService $formatService,
        UserModel $userModel,
        AddonManager $addonManager,
        OwnSite $site,
        RoleTokenFactory $roleTokenFactory,
        private EscalationModel $escalationModel
    ) {
        $this->request = $request;
        $this->siteSectionModel = $siteSectionModel;
        $this->host = $request->getHost();
        $this->config = $config;
        $this->formatService = $formatService;

        // We expect the roots from the request in the form of "" or "/asd" or "/asdf/asdf"
        // But never with a trailing slash.
        $this->basePath = rtrim("/" . trim($request->getRoot(), "/"), "/");
        $this->assetPath = rtrim("/" . trim($request->getAssetRoot(), "/"), "/");
        $this->debugModeEnabled = $config->get("Debug");
        $this->translationDebugModeEnabled = $config->get("TranslationDebug");
        $this->conversationsEnabled = $addonManager->isEnabled("conversations", Addon::TYPE_ADDON);

        $this->featureFlags = $config->get("Feature", []);

        // Get some ui metadata
        // This title may become knowledge base specific or may come down in a different way in the future.
        // For now it needs to come from some where, so I'm putting it here.
        $this->siteTitle = $this->formatService->renderPlainText(
            $config->get("Garden.Title", ""),
            HtmlFormat::FORMAT_KEY
        );

        $this->orgName = $config->get("Garden.OrgName") ?: $this->siteTitle;

        // Fetch Uploading metadata.
        $this->allowedExtensions = $config->get("Garden.Upload.AllowedFileExtensions", []);
        if ($session->getPermissions()->has("Garden.Community.Manage")) {
            $this->allowedExtensions = array_merge(
                $this->allowedExtensions,
                \MediaApiController::UPLOAD_RESTRICTED_ALLOWED_FILE_EXTENSIONS
            );
        }
        $maxSize = $config->get("Garden.Upload.MaxFileSize", ini_get("upload_max_filesize"));
        $this->maxUploadSize = \Gdn_Upload::unformatFileSize($maxSize);
        $this->maxUploads = (int) $config->get("Garden.Upload.maxFileUploads", ini_get("max_file_uploads"));

        // DeploymentCacheBuster
        $this->cacheBuster = $deploymentCacheBuster->value();

        $this->session = $session;
        $this->userModel = $userModel;
        if ($this->session->isValid()) {
            $roleIDs = $this->userModel->getRoleIDs($this->session->UserID);
            if (!empty($roleIDs)) {
                $roleToken = $roleTokenFactory->forEncoding($roleIDs);
                $this->roleTokenEncoded = $roleToken->encode();
            }
        }

        // Theming
        $currentTheme = $themeService->getCurrentTheme();
        $currentThemeAddon = $themeService->getCurrentThemeAddon();

        $this->activeThemeKey = $currentTheme->getThemeID();
        $this->activeThemeRevisionID = $currentTheme->getRevisionID() ?? null;
        $this->activeThemeViewPath = $currentThemeAddon->path("/views/");
        $this->mobileThemeKey = $config->get("Garden.MobileTheme", "Garden.Theme");
        $this->desktopThemeKey = $config->get("Garden.Theme", ThemeService::FALLBACK_THEME_KEY);
        $this->themePreview = $themeService->getPreviewTheme();

        $editContentTimeout = $config->get("Garden.EditContentTimeout");
        $this->editContentTimeout = intval($editContentTimeout);

        if ($favIcon = $config->get("Garden.FavIcon")) {
            $this->favIcon = \Gdn_Upload::url($favIcon);
        }

        if ($logo = $config->get("Garden.Logo")) {
            $this->logo = \Gdn_Upload::url($logo);
        }

        $this->bannerImage = BannerImageModel::getCurrentBannerImageLink() ?: null;

        $this->mobileAddressBarColor = $config->get("Garden.MobileAddressBarColor", null);

        $this->reCaptchaKey = $config->get("RecaptchaV3.PublicKey", "");

        $this->bannedPrivateProfiles = $config->get("Vanilla.BannedUsers.PrivateProfiles", false);

        $this->siteID = $site->getSiteID();
    }

    /**
     * Add an extra meta to the site meta.
     *
     * Notably `SiteMeta` is often used as a singleton, so extas given here will apply everywhere.
     * if you want a localized instance use the `$localizedExtraMetas` param when fetching the value.
     *
     * @param SiteMetaExtra $extra
     */
    public function addExtra(SiteMetaExtra $extra)
    {
        $this->extraMetas[] = $extra;
    }

    /**
     * Return array for json serialization.
     */
    public function jsonSerialize(): array
    {
        return $this->value();
    }

    /**
     * Make a method call and catch any throwables it provides.
     *
     * @param callable $fn
     * @param mixed $fallback
     *
     * @return mixed
     */
    private function tryWithFallback(callable $fn, $fallback)
    {
        try {
            return call_user_func($fn);
        } catch (\Throwable $t) {
            logException($t);
            return $fallback;
        }
    }

    /**
     * Get the value of the site meta.
     *
     * @param SiteMetaExtra[] $localizedExtraMetas Extra metas for this one specific fetch of the value.
     * Since `SiteMeta` is often used as a singleton, `SiteMeta::addExtra` will apply globally.
     * By passing extra metas here they can be used for one specific instance.
     *
     * @return array
     */
    public function value(array $localizedExtraMetas = []): array
    {
        $extras = array_map(function (SiteMetaExtra $extra) {
            try {
                return $extra->getValue();
            } catch (\Throwable $throwable) {
                ErrorLogger::error(
                    "Failed to load site meta   value for class " . get_class($extra),
                    ["siteMeta"],
                    [
                        "exception" => $throwable,
                    ]
                );
                return [];
            }
        }, array_merge($this->extraMetas, $localizedExtraMetas));

        $embedAllowValue = $this->config->get("Garden.Embed.Allow", false);
        $hasNewEmbed = FeatureFlagHelper::featureEnabled("newEmbedSystem");

        $currentSiteSection = $this->siteSectionModel->getCurrentSiteSection();

        $siteSectionSlugs = [];
        foreach ($this->siteSectionModel->getAll() as $siteSection) {
            if ($basePath = $siteSection->getBasePath()) {
                $siteSectionSlugs[] = $basePath;
            }
        }

        $defaultSiteSection = $this->siteSectionModel->getDefaultSiteSection();

        // Deferred for performance reasons.
        $themeFeatures = \Gdn::getContainer()->get(ThemeFeatures::class);
        $activeDriverInstance = \Gdn::getContainer()
            ->get(SearchService::class)
            ->getActiveDriver();

        return array_replace_recursive(
            [
                "context" => [
                    "requestID" => $this->request->getMeta("requestID"),
                    "host" => $this->assetPath,
                    "basePath" => $this->basePath,
                    "assetPath" => $this->assetPath,
                    "debug" => $this->debugModeEnabled,
                    "translationDebug" => $this->translationDebugModeEnabled,
                    "conversationsEnabled" => $this->conversationsEnabled,
                    "cacheBuster" => $this->cacheBuster,
                    "siteID" => $this->siteID,
                ],
                "embed" => [
                    "enabled" => (bool) $embedAllowValue,
                    "isAdvancedEmbed" => !$hasNewEmbed && $embedAllowValue === 2,
                    "isModernEmbed" => $hasNewEmbed,
                    "forceModernEmbed" => (bool) $this->config->get("Garden.Embed.ForceModernEmbed", false),
                    "remoteUrl" => $this->config->get("Garden.Embed.RemoteUrl", null),
                ],
                "ui" => [
                    "siteName" => $this->siteTitle,
                    "orgName" => $this->orgName,
                    "localeKey" => $this->getLocaleKey(),
                    "themeKey" => $this->activeThemeKey,
                    "mobileThemeKey" => $this->mobileThemeKey,
                    "desktopThemeKey" => $this->desktopThemeKey,
                    "logo" => $this->logo,
                    "favIcon" => $this->favIcon,
                    "shareImage" => $this->getShareImage(),
                    "bannerImage" => $this->bannerImage,
                    "mobileAddressBarColor" => $this->mobileAddressBarColor,
                    "fallbackAvatar" => UserModel::getDefaultAvatarUrl(),
                    "currentUser" => $this->userModel->currentFragment(),
                    "editContentTimeout" => $this->editContentTimeout,
                    "bannedPrivateProfile" => $this->bannedPrivateProfiles,
                    "useAdminCheckboxes" => boolval($this->config->get("Vanilla.AdminCheckboxes.Use", false)),
                    "autoOffsetComments" => boolval($this->config->get("Vanilla.Comments.AutoOffset", true)),
                    "allowSelfDelete" => boolval($this->config->get("Vanilla.Comments.AllowSelfDelete", false)),
                    "isDirectionRTL" => $this->getDirectionRTL(),
                ],
                "search" => [
                    "defaultScope" => $this->config->get("Search.DefaultScope", "site"),
                    "supportsScope" =>
                        (bool) $this->config->get("Search.SupportsScope", false) &&
                        $activeDriverInstance->supportsForeignRecords(),
                    "activeDriver" => $activeDriverInstance->getName(),
                    "externalSearch" => [
                        "query" => $this->config->get("Garden.ExternalSearch.Query", false),
                        "resultsInNewTab" => $this->config->get("Garden.ExternalSearch.ResultsInNewTab", false),
                    ],
                ],
                "upload" => [
                    "maxSize" => $this->maxUploadSize,
                    "maxUploads" => $this->maxUploads,
                    "allowedExtensions" => $this->allowedExtensions,
                ],

                // In case there is a some failure here we don't want the site to crash.
                "registrationUrl" => $this->tryWithFallback("registerUrl", ""),
                "signInUrl" => $this->tryWithFallback("signInUrl", ""),
                "signOutUrl" => $this->tryWithFallback("signOutUrl", ""),
                "featureFlags" => $this->featureFlags,
                "themeFeatures" => $themeFeatures->allFeatures(),
                "addonFeatures" => $themeFeatures->allAddonFeatures(),
                "defaultSiteSection" => $defaultSiteSection->jsonSerialize(),
                "siteSection" => $currentSiteSection->jsonSerialize(),
                "siteSectionSlugs" => $siteSectionSlugs,
                "themePreview" => $this->themePreview,
                "reCaptchaKey" => $this->reCaptchaKey,
                "TransientKey" => $this->session->transientKey(),
                "roleToken" => $this->roleTokenEncoded ?? "",
            ],
            ...$extras
        );
    }

    /**
     * @return string
     */
    public function getSiteTitle(): string
    {
        return $this->siteTitle;
    }

    /**
     * @return string
     */
    public function getOrgName(): string
    {
        return $this->orgName;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return string
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * @return string
     */
    public function getAssetPath(): string
    {
        return $this->assetPath;
    }

    /**
     * @return bool
     */
    public function getDebugModeEnabled(): bool
    {
        return $this->debugModeEnabled;
    }

    /**
     * @return string[]
     */
    public function getAllowedExtensions(): array
    {
        return $this->allowedExtensions;
    }

    /**
     * @return int
     */
    public function getMaxUploadSize(): int
    {
        return $this->maxUploadSize;
    }

    /**
     * @return string
     */
    public function getLocaleKey(): string
    {
        return $this->siteSectionModel->getCurrentSiteSection()->getContentLocale();
    }

    /**
     * @return string
     */
    public function getActiveThemeKey(): string
    {
        return $this->activeThemeKey;
    }

    /**
     * @return int
     */
    public function getActiveThemeRevisionID(): ?int
    {
        return $this->activeThemeRevisionID;
    }

    /**
     * @return string
     */
    public function getActiveThemeViewPath(): string
    {
        return $this->activeThemeViewPath;
    }

    /**
     * Get the configured "favorite icon" for the site.
     *
     * @return string|null
     */
    public function getFavIcon(): ?string
    {
        return $this->favIcon;
    }

    /**
     * Get the configured "Share Image" for the site.
     *
     * @return string|null
     */
    public function getShareImage(): ?string
    {
        $shareImage = $this->config->get("Garden.ShareImage");
        if (!empty($shareImage)) {
            return \Gdn_Upload::url($shareImage);
        }

        return null;
    }

    /**
     * @return string
     */
    public function getLogo(): ?string
    {
        return $this->logo;
    }

    /**
     * Get the configured "theme color" for the site.
     *
     * @return string|null
     */
    public function getMobileAddressBarColor(): ?string
    {
        return $this->mobileAddressBarColor;
    }

    /**
     * Get the configured banned profile setting.
     *
     * @return bool
     */
    public function getBannedPrivateProfiles(): bool
    {
        return $this->bannedPrivateProfiles;
    }

    /**
     * @return bool
     */
    public function getDirectionRTL(): bool
    {
        return in_array($this->getLocaleKey(), \LocaleModel::getRTLLocales()) &&
            in_array($this->getLocaleKey(), $this->config->get("Garden.RTLLocales", []));
    }
}
