<h1 align="center"> xmnk-sso </h1>

<p align="center"> .</p>

## Installing

* 安装扩展包

```shell
$ composer require zh-mead/xmnk-sso -vvv
```

* 复制配置文件到配置目录下

> 将配置文件复制到config目录下的sso.php下

```php
$ composer require zh-mead/xmnk-sso -vvv
```

* 注册服务

```php
#在bootstrap/app.php文件下添加一下代码
...
$app->register(\ZhMead\XmnkSso\SsoServiceProvider::class);
...
$app->configure('sso');
```