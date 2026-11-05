<?php
// Isolated response-contract tests; no network requests or license files are created.
namespace Joomla\CMS\Http {
    class HttpFactory {
        public static $next;
        public static $request;
        public static function getHttp($options = []) { return new self(); }
        public function post($url, $data) { self::$request = [$url, $data]; return (self::$next)(); }
        public function get($url) { self::$request = [$url]; return (self::$next)(); }
    }
}
namespace Joomla\CMS\Language {
    class Text {
        public static function _($key) { return $key; }
        public static function sprintf($key, ...$args) { return $key . ' ' . implode(' ', $args); }
    }
}
namespace Joomla\CMS\Helper { class ContentHelper {} }
namespace Joomla\CMS\MVC\Controller { class BaseController {} }
namespace Joomla\CMS {
    class Factory { public static function getApplication() { return new class { public function enqueueMessage($message, $type) {} }; } }
}
namespace Joomla\CMS\Log { class Log { const WARNING = 1; public static function add(...$args) {} } }
namespace {
    define('_JEXEC', 1);
    define('COM_TZ_PORTFOLIO_VERIFY_LICENSE', 'https://www.tzportfolio.com/download.html?task=license.verify');
    $root = $argv[1] ?? '/opt/tz-source/tz_portfolio-4.1.0/setup/packages/admin/src';
    require $root . '/Helper/ServerHelper.php';
    require $root . '/Helper/TZ_PortfolioHelper.php';
    require $root . '/Controller/LicenseController.php';
    class PsrOnlyResponse {
        private $status; private $body;
        public function __construct($status, $body) { $this->status=$status; $this->body=$body; }
        public function getStatusCode() { return $this->status; }
        public function getBody() { return $this->body; }
        public function __get($key) { throw new \RuntimeException('Legacy response property used: '.$key); }
    }
    function check($condition, $name) { if (!$condition) throw new \RuntimeException($name); echo "PASS $name\n"; }
    $controller = new \TemPlaza\Component\TZ_Portfolio\Administrator\Controller\LicenseController();
    $factory = \Joomla\CMS\Http\HttpFactory::class;
    $factory::$next = fn() => new PsrOnlyResponse(200, '{"state":200,"licenses":[{"reference":"test-reference"}]}');
    $result = $controller->verifyLicense('test-token');
    check($result->state === 200 && count($result->licenses) === 1, 'valid response accepted through PSR-7 methods');
    check($factory::$request[1] === ['token_key'=>'test-token','produce'=>'tz-portfolio-plus'], 'vendor request contract preserved');
    foreach ([401, 403, 500] as $status) {
        $factory::$next = fn() => new PsrOnlyResponse($status, 'secret must not be shown');
        $result = $controller->verifyLicense('test-token');
        check($result->state === 400 && str_contains($result->message, (string) $status) && !str_contains($result->message, 'secret'), "HTTP $status diagnosed without body disclosure");
    }
    foreach (['<html>login page</html>', '{broken', 'null', '[]', '{}'] as $body) {
        $factory::$next = fn() => new PsrOnlyResponse(200, $body);
        $result = $controller->verifyLicense('test-token');
        check($result->state === 400, 'malformed or unexpected JSON rejected');
    }
    $factory::$next = fn() => throw new \RuntimeException('Sensitive transport details: test-token');
    ob_start(); $result = $controller->verifyLicense('test-token'); $output = ob_get_clean();
    check($result->state === 400 && $output === '' && !str_contains($result->message, 'test-token'), 'transport exception contained without var_dump, exit or token leak');
    $factory::$next = fn() => new PsrOnlyResponse(200, '{"state":400,"message":"License rejected by vendor"}');
    $result = $controller->verifyLicense('test-token');
    check($result->state === 400 && $result->message === 'License rejected by vendor', 'license rejection remains rejection');
    $factory::$next = fn() => new PsrOnlyResponse(200, '<updates/>');
    $helper = \TemPlaza\Component\TZ_Portfolio\Administrator\Helper\TZ_PortfolioHelper::class;
    $response = $helper::getDataFromServer('https://www.tzportfolio.com/mock-catalogue');
    check($response && (string) $response->getBody() === '<updates/>', 'catalogue transport accepts PSR-only response and caches it');
    $factory::$next = fn() => new PsrOnlyResponse(503, 'private server detail');
    check($helper::getDataFromServer('https://www.tzportfolio.com/mock-failure') === false, 'catalogue HTTP error remains failure');
}
