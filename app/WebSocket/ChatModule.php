<?php declare(strict_types=1);
/**
 * This file is part of Swoft.
 *
 * @link     https://swoft.org
 * @document https://swoft.org/docs
 * @contact  group@swoft.org
 * @license  https://github.com/swoft-cloud/swoft/blob/master/LICENSE
 */

namespace App\WebSocket;

use App\WebSocket\Chat\HomeController;
use Swoft\Http\Message\Request;
use Swoft\WebSocket\Server\Annotation\Mapping\OnOpen;
use Swoft\WebSocket\Server\Annotation\Mapping\OnClose;
use Swoft\WebSocket\Server\Annotation\Mapping\OnMessage;
use Swoft\WebSocket\Server\Annotation\Mapping\WsModule;
use Swoft\WebSocket\Server\MessageParser\JsonParser;
use function server;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;
use Swoft\Db\DB;
use Swoft\Redis\Redis;

/**
 * Class ChatModule
 *
 * @WsModule("chat")
 */
class ChatModule
{
    /**
     * @OnOpen()
     * @param Request $request
     * @param int     $fd
     */
    public function onOpen(Request $request, int $fd): void
    {
        sgo(function () use ($request){
            $users = DB::table('user')->get();
//            var_dump($users);
//            foreach ($users as $user) {
//                echo $user['user_name'];
//            }

            $show = Redis::get('test');
            echo '---------------'.$show."----------";
            server()->push($request->getFd(), "1");
        });

    }

    /**
     * @OnMessage()
     * @param Server $server
     * @param Frame  $frame
     */
    public function onMessage(Server $server, Frame $frame): void
    {
        $server->push($frame->fd, '' . $frame->data);
    }

    /**
     * On connection closed
     * - you can do something. eg. record log
     *
     * @OnClose()
     * @param Server $server
     * @param int    $fd
     */
    public function onClose(Server $server, int $fd): void
    {
        echo '我完了';
        $server->push($fd,  "我觉得你可能8太行");
    }
}
