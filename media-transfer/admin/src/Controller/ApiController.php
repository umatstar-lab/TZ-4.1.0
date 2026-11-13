<?php
namespace Umatstar\Component\TzMediaTransfer\Administrator\Controller;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Database\DatabaseInterface;
use Umatstar\Component\TzMediaTransfer\Administrator\Service\TransferService;
class ApiController extends BaseController {
 public function run() {
  $app=Factory::getApplication();
  header('Cache-Control: no-store');header('Content-Type: application/json; charset=utf-8');header('X-Content-Type-Options: nosniff');
  \Joomla\CMS\Log\Log::addLogger(['text_file'=>'com_tz_media_transfer.php'],\Joomla\CMS\Log\Log::ALL,['com_tz_media_transfer']);
  try {
   if (!$app->isClient('administrator') || !$app->getIdentity()->authorise('core.manage','com_tz_media_transfer')) throw new \RuntimeException('Недостаточно прав доступа.',403);
   if (!Session::checkToken('post')) throw new \RuntimeException('Сессия истекла. Обновите страницу и повторите запрос.',403);
   $raw=$app->input->post->get('payload','{}','raw');
   if(!is_string($raw)||strlen($raw)>262144)throw new \RuntimeException('Запрос слишком большой или имеет неверный формат.');
   $p=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
   if (!is_array($p)||!isset($p['action'])||!is_string($p['action'])||strlen($p['action'])>32) throw new \RuntimeException('Неверный запрос.');
   $service=new TransferService(Factory::getContainer()->get(DatabaseInterface::class),$app->getIdentity(),JPATH_ROOT);
   $result=$service->dispatch((string)($p['action']??''),$p);
   echo new JsonResponse($result);
  } catch (\Throwable $e) {
   $code=$e->getCode()===403?403:400;http_response_code($code);
   // Do not send SQL errors, connection details or stack traces to the browser.
   $message=get_class($e)===\RuntimeException::class?$e->getMessage():'Не удалось выполнить операцию. Подробности доступны администратору в журнале Joomla.';
   echo new JsonResponse(null,$message,true);
   if (get_class($e)!==\RuntimeException::class) {\Joomla\CMS\Log\Log::add((string)$e,\Joomla\CMS\Log\Log::ERROR,'com_tz_media_transfer');}
  }
  $app->close();
 }
}
