# threadtask-frameworks
基于项目threadtask封装的框架。

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
