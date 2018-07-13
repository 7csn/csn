Csn
===============

Csn是一个免费开源、简单高效、面向对象化的轻量级学习型PHP开发框架，主要特性：

 + 路由控制：类laravel
 + 控制器视图自定义目录层级
 + 数据库多库切换及ORM映射
 + 路由及视图静态化设置
 + 强大的模板：继承、引入、静态化、显示等
 + 灵活配置：不修改可删除键值对
 + 注册自定义配置文件
 + 分布式：redis、memcache
 + 主从结构：mysql、redis

> Csn的运行环境要求PHP5.4以上。

## 目录结构

初始的目录结构如下：

~~~
WEB  项目部署目录
├─application        应用目录
│  ├─configs          配置目录
│  ├─controllers      控制器目录
│  ├─models           模块目录
│  ├─views            视图目录
│  ├─route.php        路由控制文件（自动创建）
│  └─secret.ini       密钥文件（自动创建）
│
├─compile            视图编译PHP目录（自动创建）
│
├─public             公共资源目录（对外访问目录）
│  ├─index.php        入口文件
│  └─.htaccess        APACHE伪静态文件
│
├─csn                框架系统目录
│
├─route              路由缓存HTML目录（自动创建）
├─runtime            运行目录（自动创建）
├─static             视图缓存HTML目录（自动创建）
│
├─vendor             Composer依赖库
│
├─composer.json        composer 定义文件
├─README.md            README 文件
└─tsyx                 命令行入口文件
~~~
