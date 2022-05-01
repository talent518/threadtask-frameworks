# threadtask-frameworks
基于项目threadtask封装的框架。

* 运行先决条件就是必须安装 [threadtask](/talent518/threadtask)

### DEMO使用有用户表
* 用户表: user
```sql
CREATE TABLE `user` (
  `uid` int(11) NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `username` varchar(20) NOT NULL COMMENT '用户名',
  `email` varchar(100) NOT NULL COMMENT '邮箱',
  `password` varchar(32) NOT NULL COMMENT '密码',
  `salt` varchar(8) NOT NULL COMMENT '安全码',
  `registerTime` datetime NOT NULL COMMENT '注册时间',
  `loginTime` datetime DEFAULT NULL COMMENT '最后登录时间',
  `loginTimes` int(11) NOT NULL DEFAULT 0 COMMENT '登录次数',
  PRIMARY KEY (`uid`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8 COMMENT='主用户表';
INSERT INTO `user` VALUES (1,'admin','admin@yeah.net','cb13131a512ff854c8bc0dc0ba04e4db','12345678','2019-10-14 22:13:55','2022-04-27 01:39:49',11),(2,'talent518','talent518@yeah.net','0ee08e4a9e574f4afa0abfb5ca4e47f8','87654321','2019-10-14 22:13:55','2021-03-24 08:37:56',1),(3,'test-853532e8','test-853532e8','66b5a5d70de6e691aa9e011eb40bf62c','853532e8','2019-10-16 20:29:18',NULL,0),(4,'test-d03db269','test-d03db269','093865fe1fc39dedc288275781c12bfe','d03db269','2019-10-16 20:30:10',NULL,0),(5,'test','test@test.con','94e5d07b62a291858b6cdc902c30f924','cf34c642','2021-03-24 06:40:52','2021-03-24 08:13:17',1),(6,'test2','test23@admin.com','178a46704b93cd1a6468fe81fc66ae55','f66966f9','2021-03-24 08:17:16','2021-03-24 08:17:54',1),(7,'admin2','','123','123456','2022-04-27 01:11:48','0000-00-00 00:00:00',0),(8,'admin3','23423@tes.com','123','123456','2022-04-27 01:14:27','0000-00-00 00:00:00',0),(9,'admin33','a23423@tes.com','123','123456','2022-04-27 01:39:49','0000-00-00 00:00:00',0),(10,'t12','234@sd.c','23432','123456','2022-04-27 16:50:46','0000-00-00 00:00:00',0);
```
* clazz
```sql
CREATE TABLE `clazz` (
  `cno` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cname` varchar(100) NOT NULL,
  `cdesc` varchar(300) DEFAULT NULL,
  PRIMARY KEY (`cno`),
  UNIQUE KEY `cname` (`cname`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;
INSERT INTO `clazz` VALUES (1,'PHP高级班','PHP构架的架构与实例讲解'),(2,'PHP基础班','PHP语言的基本语法与函数和类库讲解'),(3,'Java高级班','Java的SpringBoot框架讲解'),(4,'Oracle班','Oracle从入门到精通班'),(5,'Async Prepare','Test Insert');
```

### API文档
* 使用phpdoc生成API文档
```sh
phpdoc -i event.lib.php --ignore-tags=hidden --ignore-tags=author
```
* 启动文档服务器
```sh
php -S 127.0.0.1:8080 -t .phpdoc/build
```
* 浏览器打开 [http://127.0.0.1:8080](http://127.0.0.1:8080) 即可访问

