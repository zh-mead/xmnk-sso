<?php

namespace ZhMead\XmnkSso\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Jiannei\Response\Laravel\Support\Facades\Response;
use Laravel\Lumen\Routing\Controller;
use Illuminate\Support\Facades\Http;

class SsoClientApiController extends Controller
{
    /**
     * 返回SSO认证中心登录地址 （前后台分离环境下专用）
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
     * @throws \Illuminate\Validation\ValidationException
     */
    public function getSsoAuthUrl(Request $request)
    {
        $data = $this->validateData($request, [
            'clientLoginUrl' => 'required|string',
        ], [
            'clientLoginUrl' => '登录地址',
        ]);
        $serverAuthUrl = $this->buildServerAuthUrl($data['clientLoginUrl']);
        return Response::success($serverAuthUrl);
    }

    /**
     * 根据ticket进行登录（前后台分离环境下专用）
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
     * @throws \Illuminate\Validation\ValidationException
     */
    public function doLoginByTicket(Request $request)
    {
        $data = $this->validateData($request, [
            'ticket' => 'required|string',
        ], [
            'ticket' => '登录地址',
        ]);

        if (empty($data['ticket'])) return Response::fail('未登录', 599);
        $loginId = $this->checkTicket($data['ticket'], $request, "/sso/doLoginByTicket");
        if (!empty($loginId)) {
            //session不可用--改用redis缓存
            Cache::put('sso:user:id:' . $loginId, $loginId, config('sso.authCacheTime'));
            Cache::put('sso:user:' . $loginId, $this->getUserInfo($loginId), config('sso.authCacheTime'));
            return Response::success($loginId);
        }
        return Response::fail('无效ticket：' . $data['ticket']);
    }

    /**
     * getInfo接口 （只有登录后才可以调用此接口）
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
     * @throws \Illuminate\Validation\ValidationException
     */
    public function getCurrInfo(Request $request)
    {
        $data = $this->validateData($request, [
            'loginId' => 'required|string',
        ], [
            'loginId' => '登陆者id',
        ]);
        // 如果没有登录，就返回特定信息
        //使用redis作为缓存
        $value = Cache::get('sso:user:id:' . $data['loginId']);
        if (empty($value)) {
            return Response::fail('未登录，请登录后再次访问', 401);
        }
        //使用redis作为缓存---从Session中获取user对象
        $userTemp = Cache::get('sso:user:' . $data['loginId']);
        if (empty($userTemp['username'])) return Response::fail('未登录，请登录后再次访问', 401);

        $className = config('sso.userSyncAppCallback');
        $function = new $className;
        $loginUser = $function->userSyncApp($userTemp);
        list($token, $user, $permissions) = $loginUser;

        return Response::success(compact('token', 'user', 'permissions'));
    }

    /**
     * SSO-Client端：单点注销地址
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Illuminate\Http\Resources\Json\JsonResource|\Laravel\Lumen\Http\Redirector
     * @throws \Illuminate\Validation\ValidationException
     */
    public function logout(Request $request)
    {
        $data = $this->validateData($request, [
            'back' => 'required|string',
            'loginId' => 'required|string',
        ], [
            'back' => '重定向地址',
            'loginId' => '登陆者id',
        ]);

        if (!isset($data['back'])) $data['back'] = null;

        // 如果未登录，则无需注销
        //使用redis作为缓存
        $value = Cache::get('sso:user:id:' . $data['loginId'], null);
        if (empty($value)) {
            //重定向页面
            return redirect($data['back']);
        }
        // 调用 sso-server 认证中心单点注销API
        $timestamp = Carbon::now()->getPreciseTimestamp(3);// 时间戳
        $nonce = $this->getRandomString(20);        // 随机字符串
        $sign = $this->getSign($value, $timestamp, $nonce, config('sso.secretkey'));    // 参数签名

        $url = config('sso.saTokenIP') . config('sso.sloUrl') .
            "?loginId=" . $value .
            "&timestamp=" . $timestamp .
            "&nonce=" . $nonce .
            "&sign=" . $sign;
        $result = $this->request_post($url);
        // 校验响应状态码，200 代表成功
        if ($result['code'] == 200) {
            // 极端场景下，sso-server 中心的单点注销可能并不会通知到此 client 端，所以这里需要再补一刀
            //删除缓存
            Cache::forget('sso:user:id:' . $data['loginId']);
            // 如果指定了 back 地址，则重定向，否则返回 JSON 信息
            //重定向页面
            return redirect($data['back']);
        } else {
            // 将 sso-server 回应的消息作为异常抛出
            return Response::fail($result['msg']);
        }
    }

    /**
     * SSO-Client端：单点注销回调地址
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
     * @throws \Illuminate\Validation\ValidationException
     */
    public function logoutCall(Request $request)
    {
        $data = $this->validateData($request, [
            'loginId' => 'required|string',
            'timestamp' => 'required|string',
            'nonce' => 'required|string',
            'sign' => 'required|string',
        ], [
            'loginId' => '登陆者id',
            'timestamp' => '时间戳',
            'nonce' => '随机字符串',
            'sign' => '签名',
        ]);
        // 校验签名
        $calcSign = $this->getSign($data['loginId'], $data['timestamp'], $data['nonce'], config('sso.secretkey'));
        if ($calcSign != $data['sign']) {
            return Response::fail('无效签名，拒绝应答');
        }
        // 注销这个账号id
        //使用redis作为缓存
        $userId = Cache::get('sso:user:id:' . $data['loginId']);// 账号id
        if ($userId == $data['loginId']) {
            //删除缓存
            Cache::forget('sso:user:id:' . $data['loginId']);
            Cache::forget('sso:user:' . $data['loginId']);
        }
        return Response::success('账号id=' . $data['loginId'] . '注销成功');
    }

    /**
     * 检查认证中心  该用户是否退出登录
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource|void
     * @throws \Illuminate\Validation\ValidationException
     */
    public function checkSaTokenLoginId(Request $request)
    {
        $data = $this->validateData($request, [
            'loginId' => 'string',
        ], [
            'loginId' => '登陆者id',
        ]);

        if (empty($data['loginId'])) return Response::success('', '未登录', 599);
        // 请求
        $url = config('sso.saTokenIP') . config('sso.checkSaToken') .
            '?keyword=' . $data['loginId'] .
            '&pageNo=1' .
            '&pageSize=10';
        $result = $this->request_post($url);
        // 如果返回值的 code 不是200，代表请求失败
        if ($result['code'] == null || $result['code'] != 200) {
            return Response::success('', '用户没有权限(令牌失效、用户名、密码错误、登录过期)', 598);
        } else {
            if (count($result['data']) === 0) return Response::success('', '用户没有权限(令牌失效、用户名、密码错误、登录过期)', 598);
            return Response::success($result['data'][0]['userId']);
        }
    }


    /**
     * 自己修改密码
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updatePW(Request $request)
    {
        $data = $this->validateData($request, [
            'loginId' => 'required|string',
            'password' => 'required|string',
        ], [
            'loginId' => '登陆者id',
            'password' => '密码',
        ]);

        $data = '{"id": ' . $data['loginId'] . ',
                "password" :' . '"' . $data['password'] . '"' . '}';

        $timestamp = Carbon::now()->getPreciseTimestamp(3);// 时间戳
        $nonce = $this->getRandomString(32);        // 随机字符串
        $sign = $this->getSign($data, $timestamp, $nonce, config('sso.secretkey'));    // 参数签名

        $url = config('sso.saTokenIP') . config('sso.updatePWUrl') .
            "?data=" . $data .
            "&timestamp=" . $timestamp .
            "&nonce=" . $nonce .
            "&sign=" . $sign;
        $result = $this->request_post($url);
        // 校验响应状态码，200 代表成功
        if ($result['code'] == 200) {
            return Response::noContent();
        } else {
            // 将 sso-server 回应的消息作为异常抛出
            return Response::fail($result['msg']);
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updateUser(Request $request)
    {
        $data = $this->validateData($request, [
            'username' => 'required|string',
            'password' => 'required|string',
        ], [
            'username' => '账号',
            'password' => '密码',
        ]);
        $data = '{"username": ' . $data['username'] . ',
                "password" :' . '"' . $data['password'] . '"' . '}';

        $timestamp = Carbon::now()->getPreciseTimestamp(3);// 时间戳
        $nonce = $this->getRandomString(32);        // 随机字符串
        $sign = $this->getSign($data, $timestamp, $nonce, config('sso.secretkey'));    // 参数签名

        $url = config('sso.saTokenIP') . config('sso.updateUserUrl') .
            "?data=" . $data .
            "&timestamp=" . $timestamp .
            "&nonce=" . $nonce .
            "&sign=" . $sign;
        $result = $this->request_post($url);
        // 校验响应状态码，200 代表成功
        if ($result['code'] == 200) {
            return Response::noContent();
        } else {
            // 将 sso-server 回应的消息作为异常抛出
            return Response::fail($result['msg']);
        }
    }

    /**
     * 拼接 sso 授权地址
     * @param $clientLoginUrl
     * @return mixed|string
     */
    public function buildServerAuthUrl($clientLoginUrl)
    {
        $serverAuthUrl = config('sso.saTokenIP') . config('sso.authUrl');
        if (config('sso.client')) {
            $serverAuthUrl = $this->joinParam($serverAuthUrl, 'client=' . config('sso.client'));
        }
        $serverAuthUrl = $this->joinParam($serverAuthUrl, 'redirect=' . $clientLoginUrl);
        return $serverAuthUrl;
    }

    /**
     * 在url上拼接上kv参数并返回
     * @param $url
     * @param $paramStr
     * @return mixed|string
     */
    public function joinParam($url, $paramStr = null)
    {
        // 如果参数为空, 直接返回
        if (empty($paramStr)) {
            return $url;
        }
        if (empty($url)) {
            $url = '';
        }
        $index = strpos($url, '?');
        // ? 不存在
        if (!$index) {
            return $url . '?' . $paramStr;
        }
        // ? 是最后一位
        if ($index == strlen($url) - 1) {
            return $url . $paramStr;
        }
        // ? 是其中一位
        if ($index > -1 && $index < strlen($url) - 1) {
            $separatorChar = '&';
            // 如果最后一位是 不是&, 且 parameStr 第一位不是 &, 就增送一个 &

            if (strpos($url, $separatorChar) != strlen($url) - 1 && substr($paramStr, 0) !== $separatorChar) {
                return $url . $separatorChar . $paramStr;
            } else {
                return $url . $paramStr;
            }
        }
        // 正常情况下, 代码不可能执行到此
        return $url;
    }

    /**
     *  校验 ticket，返回 userId
     * @param $ticket
     * @param Request $request
     * @param $currPath
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function checkTicket($ticket, Request $request, $currPath)
    {
        // 校验 ticket 的地址
        $checkUrl = config('sso.saTokenIP') . config('sso.checkTicketUrl') . "?ticket=" . $ticket;
        // 如果锁定 client 的标识
        if (config('sso.client')) {
            $checkUrl = $checkUrl . "&client=" . config('sso.client');
        }
        // 如果打开了单点注销
        if (config('sso.isSlo')) {
            $ssoLogoutCall = str_replace($currPath, '/sso/logoutCall', $request->fullUrl());
            $checkUrl = $checkUrl . "&ssoLogoutCall=" . $ssoLogoutCall;
        }
        // 发起请求
        $result = $this->request_post($checkUrl);
        // 200 代表校验成功
        if ($result['code'] == 200 && $result['data'] != null) {
            // 登录上
            $loginId = $result['data'];
            return $loginId;
        } else {
            // 将 sso-server 回应的消息作为异常抛出
            return Response::fail($result['msg']);
        }
    }

    /**
     * 发出请求，并返回 SaResult 结果
     * @param $url
     * @return mixed
     */
    public function request_post($url)
    {
        $result = Http::post($url);
        // 将JSON数据解码为PHP数组
        $data = json_decode($result, true);
        return $data;
    }

    /**
     * 获取指定用户id的详细资料  (调用此接口的前提是 sso-server 端开放了 /sso/userinfo 路由)
     * @param $loginId
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function getUserInfo($loginId)
    {
        // 组织 url 参数
        $timestamp = Carbon::now()->getPreciseTimestamp(3);
        $nonce = $this->getRandomString(20);        // 随机字符串
        $sign = $this->getSign($loginId, $timestamp, $nonce, config('sso.secretkey'));    // 参数签名
        // 请求
        $url = config('sso.saTokenIP') . config('sso.userinfoUrl') .
            '?loginId=' . $loginId .
            '&timestamp=' . $timestamp .
            '&nonce=' . $nonce .
            '&sign=' . $sign;
        $result = $this->request_post($url);
        // 如果返回值的 code 不是200，代表请求失败
        if ($result['code'] == null || $result['code'] != 200) {
            return Response::fail($result['msg']);
        }
        // 解析出 user
        return $result['data'];
    }

    /**
     * 生成指定长度的随机字符串
     * @param $length
     * @return string
     */
    public function getRandomString($length)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $index = rand(0, strlen($characters) - 1);
            $randomString .= $characters[$index];
        }
        return $randomString;
    }

    /**
     * 根据参数计算签名
     * @param loginId 账号id
     * @param timestamp 当前时间戳，13位
     * @param nonce 随机字符串
     * @param secretkey 账号id
     * @return string
     */
    public function getSign($loginId, $timestamp, $nonce, $secretkey)
    {
        $data = md5('loginId=' . $loginId . '&nonce=' . $nonce . '&timestamp=' . $timestamp . '&key=' . $secretkey);
        return $data;
    }

    /**
     * 验证数据
     * @param $request
     * @param array $rules
     * @param array $customAttributes
     * @param array $otherFields
     * @param array $hiddenFields
     * @param array $messages
     * @return mixed
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validateData($request, array $rules, array $customAttributes = [], array $otherFields = [], array $hiddenFields = [], array $messages = [])
    {
        $this->validate($request, $rules, $messages, $customAttributes);
        $fields = array_keys($rules);
        if (count($otherFields)) $fields = array_merge($fields, $otherFields);
        if (count($hiddenFields)) $fields = array_diff($fields, $hiddenFields);
        return $request->only($fields);
    }
}