# Csn
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
www  WEB部署目录（或者子目录）
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
├─session            SESSION目录（自动创建）
├─static             视图缓存HTML目录（自动创建）
│
├─vendor             Composer依赖库
│
├─composer.json        composer 定义文件
├─README.md            README 文件
└─tsyx                 命令行入口文件
~~~


## 命名规范

`Csn`遵循PSR-2命名规范和PSR-4自动加载规范，并且注意如下规范：

### 目录和文件

*   目录不强制规范，驼峰和小写+下划线模式均支持；
*   类库、函数文件统一以`.php`为后缀；
*   类的文件名均以命名空间定义，并且命名空间的路径和类库文件所在路径一致；
*   类名和类文件名保持一致，统一采用驼峰法命名（首字母大写）；

### 函数和类、属性命名
*   类的命名采用驼峰法，并且首字母大写，例如 `User`、`UserType`，默认不需要添加后缀，例如`UserController`应该直接命名为`User`；
*   函数的命名使用小写字母和下划线（小写字母开头）的方式，例如 `get_client_ip`；
*   方法的命名使用驼峰法，并且首字母小写，例如 `getUserName`；
*   属性的命名使用驼峰法，并且首字母小写，例如 `tableName`、`instance`；
*   以双下划线“__”打头的函数或方法作为魔法方法，例如 `__call` 和 `__autoload`；

### 常量和配置
*   常量以大写字母和下划线命名，例如 `APP_PATH`和 `THINK_PATH`；
*   配置参数以小写字母和下划线命名，例如 `url_route_on` 和`url_convert`；

### 数据表和字段
*   数据表和字段采用小写加下划线方式命名，并注意字段名不要以下划线开头，例如 `think_user` 表和 `user_name`字段，不建议使用驼峰和中文作为数据表字段命名。

## 参与开发
请参阅 [ThinkPHP5 核心框架包](https://github.com/top-think/framework)。

## 版权信息

Csn遵循Apache2开源协议发布，并提供免费使用。

本项目不含第三方源码。

版权所有Copyright © 2016-2018 by Csn (http://www.7csn.com)

All rights reserved。

ThinkPHP® 商标和著作权所有者为7csn个人。

更多细节参阅 [LICENSE.txt](LICENSE.txt)
