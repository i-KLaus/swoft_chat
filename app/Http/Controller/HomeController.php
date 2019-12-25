<?php declare(strict_types=1);
/**
 * This file is part of Swoft.
 *
 * @link     https://swoft.org
 * @document https://swoft.org/docs
 * @contact  group@swoft.org
 * @license  https://github.com/swoft-cloud/swoft/blob/master/LICENSE
 */

namespace App\Http\Controller;

use Swoft;
use Swoft\Http\Message\ContentType;
use Swoft\Http\Message\Response;
use Swoft\Http\Server\Annotation\Mapping\Controller;
use Swoft\Http\Server\Annotation\Mapping\RequestMapping;
use Swoft\Http\Server\Annotation\Mapping\Middleware;
use App\Http\Middleware\CorsMiddleware;
use Swoft\View\Renderer;
use Swoft\Db\DB;
use Swoft\Http\Message\Request;
use Swoft\Redis\Redis;
use Throwable;
use function context;

/**
 * Class HomeController
 * @Controller()
 */
class HomeController
{
    /**
     * @RequestMapping("/")
     * @throws Throwable
     */
    public function index(): Response
    {
        /** @var Renderer $renderer */
        $renderer = Swoft::getBean('view');
        $content  = $renderer->render('home/index');

        return context()->getResponse()->withContentType(ContentType::HTML)->withContent($content);
    }

    /**
     * @RequestMapping()
     * @Middleware(CorsMiddleware::class)
     *
     * @RequestMapping("/hi")
     * @return Response
     */
    public function hi(Request $request): Response
    {
        $id = $request->input('id');
        if(!empty($id)){
            $data = [
                'mine'=>$this->getOwner($id),
                'friend'=>$this->getFriends($id)
            ];
            $content = json_msg($data);
            return context()->getResponse()->withContent($content);
        }
        return context()->getResponse()->withContent('please set id');
    }


    /**
     *
     * @RequestMapping()
     * @Middleware(CorsMiddleware::class)
     *
     * @RequestMapping("/upload")
     * @return Response
     */
    public function upload(Request $request):Response
    {
        $file = $request->file("file");
        var_dump($file);
//        $hash = sha1(time().uniqid());

        $filename = time().".png";
        $datedir = date("Ymd");
        $target_dir = "/home/www/cj/public/images/$datedir";
        if(!is_dir($target_dir)){
            mkdir($target_dir);
        }
        $target_path = $target_dir."/".$filename;
        $result = $file->moveTo($target_path);
        var_dump($file);
        $data = [
            'code' => 0,
            "msg" => "",
            "data" => ["src" => "http://award.dajitui.online/images/".$datedir."/".$filename]
        ];
        $content = json_encode($data);
        echo $content;

        return context()->getResponse()->withContent($content);
    }

    /**
     * @RequestMapping("/hello[/{name}]")
     * @param string $name
     *
     * @return Response
     */
    public function hello(string $name): Response
    {
        return context()->getResponse()->withContent('Hello' . ($name === '' ? '' : ", {$name}"));
    }

    /**
     * @param int $user_id
     * @return \Swoft\Db\Eloquent\Collection
     * @throws \Swoft\Db\Exception\DbException
     * 获取好友列表
     */
    public function getFriends($user_id){
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
    public function getOwner($user_id){
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
}
