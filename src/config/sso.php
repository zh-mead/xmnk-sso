<?php
return [
    /**
     * 认证中心
     */
    'saTokenIP' => '',

    /**
     * 登录服务所在类
     * eg:
     *  public function userSyncApp($userTemp)
     *  {
     *      # $userTemp为用户登录数据：其中username为唯一账号
     *      # $token:子系统的token
     *      # $admin:子系统的用户
     *      # $permissions：子系统的权限
     *      return [$token, $admin, $permissions];
     *  }
     *
     */
    'userSyncAppCallback' => '',

    /**
     * 接口调用秘钥
     */
    'secretkey' => '',

    /**
     * 请求前缀
     */
    'routePrefix' => 'base/sso',

    /**
     * SSO-Server端 统一认证地址
     */
    'authUrl' => '/sso/auth',

    /**
     * SSO-Server端 ticket校验地址
     */
    'checkTicketUrl' => '/sso/checkTicket',

    /**
     * 打开单点注销功能
     */
    'isSlo' => true,

    /**
     * 单点注销地址
     */
    'sloUrl' => '/sso/signout',

    /**
     * SSO-Server端 查询userinfo地址
     */
    'userinfoUrl' => '/sso/userinfo',


    /**
     * 检查认证中心  该用户是否退出登录
     */
    'checkSaToken' => '/sso/getList',


    /***
     * 自己修改密码
     */
    'updatePWUrl' => '/ssp/listen/user/updatePassword',

    /**
     * 管理员重置密码
     */
    'updateUserUrl' => '/ssp/listen/user/update',

    /**
     * 当前 client 的标识，可为 null
     */
    'client' => 'ssp-client3-nosdk',

    /**
     * 认证缓存时间
     */
    'authCacheTime' => 2419200,
];