<?php

namespace DevGroup\Multilingual\components;

use DevGroup\Multilingual\models\Language;
use Yii;
use yii\web\ServerErrorHttpException;
use yii\web\UrlManager as BaseUrlManager;

class UrlManager extends BaseUrlManager
{

    public $cache = 'cache';

    public $cacheLifetime = 86400;

    /** @var bool|array  */
    public $includeRoutes = false;

    /** @var bool|array  */
    public $excludeRoutes = [
        'site/login',
        'site/logout',
    ];

    public $languageParam = 'language_id';

    private $forceHostInUrl = false;

    public $enablePrettyUrl = true;

    public $showScriptName = false;

    public $rules = [
        '' => 'site/index',
    ];

    /** @var null|string null to set scheme as it is requested, string(http or https) for exact scheme forcing */
    public $forceScheme = null;

    /** @var null|integer null to set port as it is requested, integer(ie 8080) for exact port */
    public $forcePort = null;

    /**
     * @return \yii\caching\Cache
     */
    public function cache()
    {
        return Yii::$app->get($this->cache);
    }

    /**
     * @inheritdoc
     */
    public function createUrl($params)
    {
        $params = (array) $params;
        $route = trim($params[0], '/');

        if ($this->excludeRoutes !== false) {
            if (in_array($route, $this->excludeRoutes)) {
                return parent::createUrl($params);
            }
        }

        if ($this->includeRoutes !== false) {
            if (in_array($route, $this->includeRoutes) === false) {
                return parent::createUrl($params);
            }
        }
        return $this->createLanguageUrl($params);

    }

    /**
     * Creates URL with language identifiers(domain and/or folder)
     * @param $params
     * @return string
     * @throws ServerErrorHttpException
     */
    private function createLanguageUrl($params)
    {
        /** @var \DevGroup\Multilingual\Multilingual $multilingual */
        $multilingual = Yii::$app->multilingual;

        $requested_language_id = isset($params[$this->languageParam]) ? $params[$this->languageParam] : null;
        if ($requested_language_id === null) {
            $requested_language_id = $multilingual->language_id;
        } else {
            unset($params[$this->languageParam]);
        }

        /** @var Language $requested_language */
        $requested_language = Language::findOne(['id' => $requested_language_id]);
        if ($requested_language === null) {
            throw new ServerErrorHttpException('Requested language not found');
        }
        $current_language_id = $multilingual->language_id;

        $url = parent::createUrl($params);
        if (!empty($requested_language->folder)) {
            $url = '/' . $requested_language->folder .'/' . ltrim($url, '/');
        }
        if ($current_language_id === $requested_language->id && $this->forceHostInUrl === false) {
            return $url;
        }

        if ($this->forceScheme !== null) {
            $scheme = $this->forceScheme;
        } else {
            $scheme = Yii::$app->request->getIsSecureConnection() ? 'https' : 'http';
        }

        if ($this->forcePort !== null) {
            $port = $this->forcePort === 80 ? '' : ':' . $this->forcePort;
        } else {
            $port = Yii::$app->request->port === 80 ? '' : ':' . Yii::$app->request->port;
        }
        return $scheme . '://' . $requested_language->domain . $port . '/' . ltrim($url, '/');
    }

    /**
     * @return string Requested domain
     */
    private function requestedDomain()
    {
        return isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : Yii::$app->request->serverName;
    }

    /**
     * @inheritdoc
     */
    public function parseRequest($request)
    {
        /** @var \DevGroup\Multilingual\Multilingual $multilingual */
        $multilingual = Yii::$app->multilingual;
        $domain = $this->requestedDomain();

        $path = explode('/', $request->pathInfo);
        $folder = array_shift($path);
        $languages = Language::find()->all();
        /** @var bool|Language $languageMatched */
        $languageMatched = false;
        foreach ($languages as $language) {
            $matchedDomain = $language->domain === $domain;
            if (empty($language->folder)) {
                $matchedFolder = $matchedDomain;
            } else {
                $matchedFolder = $language->folder === $folder;
            }
            if ($matchedDomain && $matchedFolder) {
                $languageMatched = $language;
                $multilingual->language_id = $language->id;
                Yii::$app->language = $language->yii_language;
                break;
            }
        }
        if (is_array($this->excludeRoutes)) {
            $resolved = parent::parseRequest($request);
            if (is_array($resolved)) {
                $route = reset($resolved);
                if (in_array($route, $this->excludeRoutes)) {
                    $multilingual->language_id = $multilingual->cookie_language_id;
                    /** @var Language $lang */
                    $lang = Language::findOne($multilingual->cookie_language_id);
                    Yii::$app->language = $lang->yii_language;
                    return $resolved;
                }
            }
        }

        if ($languageMatched === false) {
            // no matched language and not in excluded routes - should redirect to user's regional domain with 302

            $this->forceHostInUrl = true;
            $url = $this->createUrl([$request->pathInfo, 'language_id' => $multilingual->language_id_geo]);
            Yii::$app->response->redirect($url, 302, false);
            $this->forceHostInUrl = false;
            Yii::$app->end();
        }



        if (!empty($languageMatched->folder)) {
            if ($languageMatched->folder === $request->pathInfo) {
                Yii::$app->response->redirect('/'.$request->pathInfo.'/', 301, false);
                Yii::$app->end();
            }
            // matched language urls are made with subfolders
            // cut them down(path was already shifted)
            $request->setPathInfo(implode('/', $path));
        }


        return parent::parseRequest($request);
    }
}