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

    }

    /**
     * @OnMessage()
     * @param Server $server
     * @param Frame  $frame
     */
    public function onMessage(Server $server, Frame $frame): void
    {
//        var_dump(json_decode($frame->data));
       sgo(function () use ($server,$frame){
           $client_info = $server->getClientInfo($frame->fd);
           $data = json_decode($frame->data,true);
           var_dump($data);
           if($data['type'] == 1 ){//上线
               echo 1111;
               $id = $this->user_online($frame,$client_info['remote_ip']);//当前客户端用户的id
//               Redis::sAdd('users',$data['user']);//添加到users集合中
               //离线消息推送
               $offline = Redis::get($id.'_offline_msg');
               if($offline) {
                   $this->offline_msg_send($id,$frame,$server);
               }
           }else if($data['type'] == 2){//发送消息
               $mine = $data['data']['mine'];
               $to = $data['data']['to'];
               if(isset($data['type']) && $data['type'] == 'all'){//to_user即用户id

               }else{
                   //信息接收方
                   $fd = $this->send_to($to['id'],$mine['content'],$mine['id']);
                   if($fd != 'offline'){

                       $message = $this->msg_create($mine['id'],$mine['content']);

                       $push_data = ['message'=>$message];
                       $server->push($fd,json_encode($push_data));
                   }
                   //self接收方
//                   $message = $this->msg_generate($data['type'],$frame,$data,$frame->fd);
//                   $push_data = ['message'=>$message];
//                   $server->push($frame->fd,json_encode($push_data));
               }
           }
       });
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
        sgo(function () use ($fd) {
            $this->user_offline_update($fd);
        });
    }

    public function user_online($frame,$ip){
        $data = json_decode($frame->data,true);
//        $user = $data['user'];
        $id = $data['id'];

//        $result = DB::table('user')->where(['user_name'=>$user])->get();
        DB::table('user')->where(['id'=>$id])->update(['fd'=>$frame->fd,'last_login_ip'=>$ip,'last_login'=>time()]);
//        $user_data = json_encode(['fd'=>$frame->fd,'name'=>$user,'id'=>$id]);
//        Redis::set($user,$user_data);
        return $id;

//        if(!empty($result)){
//            DB::table('user')->where(['id'=>$id])->update(['fd'=>$frame->fd,'last_login_ip'=>$ip,'last_login'=>time()]);
//            $user_data = json_encode(['fd'=>$frame->fd,'name'=>$user,'id'=>$id]);
//            Redis::set($user,$user_data);
//            return $id;
//        }else{
//            return false;
//        }
    }

    /**
     * @param $to_user_id 接收方id
     * @param $from_user_id 发送方$
     * return 接收方的fd
     */
    public function send_to($to_user_id,$msg,$from_user_id){

        $data = DB::table('user')->where(['id'=>$to_user_id])->select('fd')->get();

        if(!empty($data)){
            if($data[0]['fd']==-1){
                $offline_str = $to_user_id.'_offline_msg';
                $offline_id = Redis::get($offline_str);
                if($offline_id){
                    Redis::incr($offline_str);
                }else{
                    Redis::set($offline_str,1);
                }
                $this->save_msg($msg,$to_user_id,$from_user_id,$data[0]['fd']);
                return 'offline';
            }else{
                //todo
                //在线消息入库，发送消息，db,websocket
                $this->save_msg($msg,$to_user_id,$from_user_id,$data[0]['fd']);
                return $data[0]['fd'];
            }
        }
    }


    /**
     * 消息入库
     */
    public function save_msg($msg,$recever_id,$sender_id,$fd){
        $status = ($fd == -1)?"$fd":1;
        DB::table('msg')->insert(['sender_id'=>$sender_id,'receiver_id'=>$recever_id,'msg'=>$msg,'status'=>$status,'create_time'=>time()]);
    }

    /**
     * @param $id
     * 离线消息接收，更改msg中未读消息状态
     */
    public function offline_msg_send($id,$frame,$ws){
        $data = DB::table('user')
            ->join('msg','user.id','=','msg.sender_id')
            ->where(['msg.receiver_id'=>$id,'status'=>-1])
            ->select('msg.*','user.user_name')
            ->get();

        if(!empty($data)){
            foreach ($data as $key=>$val){
                $msg = $val['msg'];
                $msg_id = $val['id'];
                $name = $val['user_name'];
                //消息已读，更改status

                DB::table('msg')->where(['id'=>$msg_id])->update(['status'=>1,'update_time'=>time()]);

                $message = $this->msg_create($val['sender_id'],$msg);
                echo $frame->fd;
                echo json_encode(['message'=>$message]);
                $ws->push($frame->fd,json_encode(['message'=>$message]));
            }
            Redis::del($id.'_offline_msg');
        }
    }

    /**
     * 用户下线数据更新
     */
    public function user_offline_update($fd){

        $data = DB::table('user')->where(['fd'=>$fd])->first();
        if(!empty($data)){
            $id = $data['id'];
            DB::table('user')->where(['id'=>$id])->update(['fd'=>-1]);
//            Redis::del($user_name);
//            Redis::sRem('users',$user_name);
        }
    }

    /**
     * 媒体文件上传处理
     */
    public function upload(){

    }

    /**
     * @param int $user_id
     * @return \Swoft\Db\Eloquent\Collection
     * @throws \Swoft\Db\Exception\DbException
     * 获取好友列表
     */
    public function getFriends($user_id = 1){
        $result = DB::table('friend')
            ->join('user','user.id','=','friend.friend_id')
            ->where(['friend.user_id'=>$user_id])
            ->select('user.user_name','user.head','user.fd','user.sign','user.id','friend.group_name','friend.group_id')
            ->get();

        $groups = [];
        $friend = [];
        foreach ($result as $key => $value) {
            if(!in_array($value['group_name'],$groups)){
                $groups[] = $value['group_name'];
            }
        }
        foreach ($groups as $index => $group){
            $friend[$index]['groupname'] = $group;
            $friend[$index]['id'] = '';
            $friend[$index]['online'] = 0;
            $friend[$index]['list'] = [];
            foreach ($result as $k => $val){
                if($group == $val['group_name']){
                    if($val['fd'] != -1){
                        $friend[$index]['online'] = $friend[$index]['online'] + 1;
                    }
                    if(empty($friend[$index]['id'])){
                        $friend[$index]['id'] = $val['group_id'];
                    }
                    $friend[$index]['list'][] = [
                        'username' => $val['user_name'],
                        'id' => $val['id'],
                        'avatar' => $val['head'],
                        'sign' => $val['sign']
                    ];
                }
            }
        }

        return $friend;
    }

    /**
     * @param int $user_id
     * @return \Swoft\Db\Eloquent\Collection
     * @throws \Swoft\Db\Exception\DbException
     * 获取个人信息
     */
    public function getOwner($user_id = 1){
        $owner = DB::table('user')
            ->where(['id'=>$user_id])
            ->select('id','user_name','head','sign')
            ->first();
        $data = [];
        foreach ($owner as $key => $value){
            $data['username'] = $owner['user_name'];
            $data['id'] = $owner['id'];
            $data['status'] = 'online';
            $data['sign'] = $owner['sign'];
            $data['avatar'] = $owner['head'];
        }
        return $data;
    }

    public function msg_create($sender_id,$content){
        $data = DB::table('user')
            ->where(['id'=>$sender_id])
            ->first();
        $msg = [];
        foreach ($data as $key=>$val){
            $msg['username'] = $data['user_name'];
            $msg['avatar'] = $data['head'];
            $msg['id'] = $data['id'];
            $msg['type'] = "friend";
            $msg['content'] = $content;
            $msg['mine'] = false;
            $msg['fromid'] = $sender_id;
            $msg['timestamp'] = time() * 1000;
        }
        return $msg;
    }

}
