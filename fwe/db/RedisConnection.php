<?php
namespace fwe\db;

/**
 * @author abao
 * 
 * @hidden 未分组
 * @method RedisConnection|mixed debugObject($key) 获取有关密钥的调试信息。 <https://redis.io/commands/debug-object>
 * @method RedisConnection|mixed debugSegfault() 执行导致Redis崩溃的无效内存访问。它用于在开发过程中模拟bug。 <https://redis.io/commands/debug-segfault>
 * @method RedisConnection|mixed echo($message) 回显给定的字符串。 <https://redis.io/commands/echo>
 * 
 * @method RedisConnection|mixed info($section = null) 获取有关服务器的信息和统计信息。 <https://redis.io/commands/info>
 * @method RedisConnection|mixed migrate($host, $port, $key, $destinationDb, $timeout, ...$options) 以原子方式将键从Redis实例传输到另一个实例。 <https://redis.io/commands/migrate>
 * 
 * @method RedisConnection|mixed object($subcommand, ...$argumentss) 检查Redis对象的内部。 <https://redis.io/commands/object>
 * 
 * @method RedisConnection|mixed time() 返回当前服务器时间。 <https://redis.io/commands/time>
 * @method RedisConnection|mixed shutdown($saveOption = null) 将数据集同步保存到磁盘，然后关闭服务器。 <https://redis.io/commands/shutdown>
 * @method RedisConnection|mixed slowlog($subcommand, $argument = null) 管理Redis慢速查询日志。 <https://redis.io/commands/slowlog>
 * 
 * @hidden Redis 命令信息
 * @method RedisConnection|mixed command() 获取Redis命令详细信息数组。 <https://redis.io/commands/command>
 * @method RedisConnection|mixed commandCount() 获取Redis命令总数. <https://redis.io/commands/command-count>
 * @method RedisConnection|mixed commandGetkeys() 使用完整的Redis命令提取密钥。 <https://redis.io/commands/command-getkeys>
 * @method RedisConnection|mixed commandInfo(...$commandNames) 获取特定Redis命令详细信息的数组。 <https://redis.io/commands/command-info>
 * 
 * @hidden Redis 持久化数组
 * @method RedisConnection|mixed lastsave() 获取上次成功保存到磁盘的UNIX时间戳。 <https://redis.io/commands/lastsave>
 * @method RedisConnection|mixed save() 将数据集同步保存到磁盘。 <https://redis.io/commands/save>
 * @method RedisConnection|mixed bgrewriteaof() 异步执行一个 AOF（AppendOnly File） 文件重写操作。 <https://redis.io/commands/bgrewriteaof>
 * @method RedisConnection|mixed bgsave() 在后台异步保存当前数据库的数据到磁盘。 <https://redis.io/commands/bgsave>
 * 
 * @hidden Redis 客户端(client)
 * @method RedisConnection|mixed clientKill(...$filters) 终止客户端的连接。 <https://redis.io/commands/client-kill>
 * @method RedisConnection|mixed clientList() 获取客户端连接的列表。 <https://redis.io/commands/client-list>
 * @method RedisConnection|mixed clientGetname() 获取当前连接名称。 <https://redis.io/commands/client-getname>
 * @method RedisConnection|mixed clientPause($timeout) 停止处理来自客户端的命令一段时间。 <https://redis.io/commands/client-pause>
 * @method RedisConnection|mixed clientReply($option) 指示服务器是否回复命令。 <https://redis.io/commands/client-reply>
 * @method RedisConnection|mixed clientSetname($connectionName) 设置当前连接名称。 <https://redis.io/commands/client-setname>
 * 
 * @method RedisConnection|mixed auth($password) 验证密码是否正确。 <https://redis.io/commands/auth>
 * 
 * @method RedisConnection|mixed monitor() 实时侦听服务器接收到的所有请求。 <https://redis.io/commands/monitor>
 * @method RedisConnection|mixed ping($message = null) 查看服务是否运行 <https://redis.io/commands/ping>
 * @method RedisConnection|mixed quit() 关闭连接。 <https://redis.io/commands/quit>
 * 
 * @hidden Redis 集群(cluster)
 * @method RedisConnection|mixed clusterAddslots(...$slots) 为接收节点分配新的哈希槽。 <https://redis.io/commands/cluster-addslots>
 * @method RedisConnection|mixed clusterCountkeysinslot($slot) 返回指定哈希槽中的本地密钥数。 <https://redis.io/commands/cluster-countkeysinslot>
 * @method RedisConnection|mixed clusterDelslots(...$slots) 在接收节点中将哈希槽设置为未绑定。 <https://redis.io/commands/cluster-delslots>
 * @method RedisConnection|mixed clusterFailover($option = null) 强制从设备对其主设备执行手动故障切换。 <https://redis.io/commands/cluster-failover>
 * @method RedisConnection|mixed clusterForget($nodeId) 从节点表中删除节点。<https://redis.io/commands/cluster-forget>
 * @method RedisConnection|mixed clusterGetkeysinslot($slot, $count) 返回指定哈希槽中的本地密钥名称。 <https://redis.io/commands/cluster-getkeysinslot>
 * @method RedisConnection|mixed clusterInfo() 提供有关Redis集群节点状态的信息。 <https://redis.io/commands/cluster-info>
 * @method RedisConnection|mixed clusterKeyslot($key) 返回指定键的哈希槽。 <https://redis.io/commands/cluster-keyslot>
 * @method RedisConnection|mixed clusterMeet($ip, $port) 强制节点集群与另一个节点握手。 <https://redis.io/commands/cluster-meet>
 * @method RedisConnection|mixed clusterNodes() 获取节点的集群配置。 <https://redis.io/commands/cluster-nodes>
 * @method RedisConnection|mixed clusterReplicate($nodeId) 将节点重新配置为指定主节点的从属节点。 <https://redis.io/commands/cluster-replicate>
 * @method RedisConnection|mixed clusterReset($resetType = "SOFT") 重置Redis集群节点。 <https://redis.io/commands/cluster-reset>
 * @method RedisConnection|mixed clusterSaveconfig() 强制节点在磁盘上保存群集状态。 <https://redis.io/commands/cluster-saveconfig>
 * @method RedisConnection|mixed clusterSetslot($slot, $type, $nodeid = null) 将哈希槽绑定到特定节点。 <https://redis.io/commands/cluster-setslot>
 * @method RedisConnection|mixed clusterSlaves($nodeId) 列出指定主节点的从属节点。 <https://redis.io/commands/cluster-slaves>
 * @method RedisConnection|mixed clusterSlots() 获取群集插槽到节点映射的数组。 <https://redis.io/commands/cluster-slots>
 * 
 * @method RedisConnection|mixed readonly() 启用与群集从属节点的连接的读取查询。 <https://redis.io/commands/readonly>
 * @method RedisConnection|mixed readwrite() 禁用对群集从属节点连接的读取查询。 <https://redis.io/commands/readwrite>
 * 
 * @hidden Redis 配置(config)
 * @method RedisConnection|mixed configGet($parameter) 获取配置参数的值。 <https://redis.io/commands/config-get>
 * @method RedisConnection|mixed configRewrite() 用内存配置重写配置文件。 <https://redis.io/commands/config-rewrite>
 * @method RedisConnection|mixed configSet($parameter, $value) 将配置参数设置为给定值。 <https://redis.io/commands/config-set>
 * @method RedisConnection|mixed configResetstat() 重置信息返回的统计信息。 <https://redis.io/commands/config-resetstat>
 * 
 * @method RedisConnection|mixed role() 返回实例在复制上下文中的角色。 <https://redis.io/commands/role>
 * @method RedisConnection|mixed slaveof($host, $port) 使服务器成为另一个实例的从属服务器，或将其升级为主服务器。 <https://redis.io/commands/slaveof>
 * 
 * @method RedisConnection|mixed sync() 用于复制的内部命令。 <https://redis.io/commands/sync>
 * @method RedisConnection|mixed wait($numslaves, $timeout) 等待在当前连接的上下文中发送的所有写入命令的同步复制。 <https://redis.io/commands/wait>
 * 
 * @hidden Redis 数据库
 * @method RedisConnection|mixed swapdb($index, $index) 交换两个Redis数据库。 <https://redis.io/commands/swapdb>
 * @method RedisConnection|mixed flushall($ASYNC = null) 从所有数据库中删除所有键。 <https://redis.io/commands/flushall>
 * 
 * @method RedisConnection|mixed select($index) 切换到指定的数据库 <https://redis.io/commands/select>
 * @method RedisConnection|mixed dbsize() 返回所选数据库中的键数。 <https://redis.io/commands/dbsize>
 * @method RedisConnection|mixed flushdb($ASYNC = null) 从当前数据库中删除所有键。 <https://redis.io/commands/flushdb>
 * 
 * @hidden Redis Lua脚本
 * @method RedisConnection|mixed eval($script, $numkeys, ...$keys, ...$args) 在服务器端执行Lua脚本。 <https://redis.io/commands/eval>
 * @method RedisConnection|mixed evalsha($sha1, $numkeys, ...$keys, ...$args) 在服务器端执行Lua脚本。 <https://redis.io/commands/evalsha>
 * @method RedisConnection|mixed scriptDebug($option) 设置脚本调试模式。 <https://redis.io/commands/script-debug>
 * @method RedisConnection|mixed scriptExists(...$sha1s) 返回有关脚本缓存中是否存在脚本的信息。 <https://redis.io/commands/script-exists>
 * @method RedisConnection|mixed scriptFlush() 从脚本缓存中删除所有脚本。 <https://redis.io/commands/script-flush>
 * @method RedisConnection|mixed scriptKill() 终止当前正在执行的脚本。 <https://redis.io/commands/script-kill>
 * @method RedisConnection|mixed scriptLoad($script) 将指定的Lua脚本加载到脚本缓存中。。 <https://redis.io/commands/script-load>
 * 
 * @hidden Redis 事务(transaction)
 * @method RedisConnection|mixed multi() 标记事务块的开始。 <https://redis.io/commands/multi>
 * @method RedisConnection|mixed discard() 放弃在MULTI执行后发出的所有命令。 <https://redis.io/commands/discard>
 * @method RedisConnection|mixed exec() 执行MULTI命令后发出的所有命令。 <https://redis.io/commands/exec>
 * @method RedisConnection|mixed unwatch() 忘记所有被监视的键。 <https://redis.io/commands/unwatch>
 * @method RedisConnection|mixed watch(...$keys) 观察给定的键以确定MULTI/EXEC块的执行。 <https://redis.io/commands/watch>
 * 
 * @hidden Redis HyperLogLog 命令
 * @method RedisConnection|mixed pfadd($key, ...$elements) 添加指定元素到 HyperLogLog 中。 <https://redis.io/commands/pfadd>
 * @method RedisConnection|mixed pfcount(...$keys) 返回给定 HyperLogLog 的基数估算值。 <https://redis.io/commands/pfcount>
 * @method RedisConnection|mixed pfmerge($destkey, ...$sourcekeys) 将多个 HyperLogLog 合并为一个 HyperLogLog <https://redis.io/commands/pfmerge>
 * 
 * @hidden Redis 发布订阅 命令
 * @method RedisConnection|mixed pubsub($subcommand, ...$arguments) 查看订阅与发布系统状态。 <https://redis.io/commands/pubsub>
 * @method RedisConnection|mixed publish($channel, $message) 向频道发布消息。 <https://redis.io/commands/publish>
 * @method RedisConnection|mixed psubscribe(...$patterns) 侦听发布到与给定模式匹配的通道的消息。 <https://redis.io/commands/psubscribe>
 * @method RedisConnection|mixed punsubscribe(...$patterns) 停止侦听发布到与给定模式匹配的频道的消息。 <https://redis.io/commands/punsubscribe>
 * @method RedisConnection|mixed subscribe(...$channels) 侦听发布到给定频道的消息。 <https://redis.io/commands/subscribe>
 * @method RedisConnection|mixed unsubscribe(...$channels) 停止侦听发送到给定频道的消息。 <https://redis.io/commands/unsubscribe>
 * 
 * @hidden Redis 键(key)
 * @method RedisConnection|mixed randomkey() 从键空间返回一个随机键。 <https://redis.io/commands/randomkey>
 * @method RedisConnection|mixed keys($pattern) 找到与给定模式匹配的所有键。 <https://redis.io/commands/keys>
 * @method RedisConnection|mixed scan($cursor, $MATCH = null, $pattern = null, $COUNT = null, $count = null) 迭代当前数据库中的键。 <https://redis.io/commands/scan>
 * 
 * @method RedisConnection|mixed type($key) 返回键的存储类型。 <https://redis.io/commands/type>
 * @method RedisConnection|mixed exists(...$keys) 确定键是否存在。 <https://redis.io/commands/exists>
 * @method RedisConnection|mixed rename($key, $newkey) 重命名键。 <https://redis.io/commands/rename>
 * @method RedisConnection|mixed renamenx($key, $newkey) 仅当新键不存在时才重命名键。 <https://redis.io/commands/renamenx>
 * @method RedisConnection|mixed unlink(...$keys) 在另一个线程中异步删除键。否则就和DEL一样，但不阻塞。。 <https://redis.io/commands/unlink>
 * @method RedisConnection|mixed del(...$keys) 删除一个或多个键。 <https://redis.io/commands/del>
 * @method RedisConnection|mixed move($key, $db) 将密钥移动到另一个数据库。 <https://redis.io/commands/move>
 * 
 * @method RedisConnection|mixed dump($key) 返回存储在指定键处的值的序列化版本。 <https://redis.io/commands/dump>
 * @method RedisConnection|mixed restore($key, $ttl, $serializedValue, $REPLACE = null) 使用提供的序列化值（以前使用DUMP获取）创建键。 <https://redis.io/commands/restore>
 * 
 * @method RedisConnection|mixed touch(...$keys) 把给定的所有键的最后访问时间设置为当前时间并返回已有键的个数。 <https://redis.io/commands/touch>
 * @method RedisConnection|mixed ttl($key) 返回指定键的剩余生存时间。 <https://redis.io/commands/ttl>
 * @method RedisConnection|mixed expire($key, $seconds) 设置一个键的时间以秒为单位。 <https://redis.io/commands/expire>
 * @method RedisConnection|mixed expireat($key, $timestamp) 将密钥的过期时间设置为UNIX时间戳。 <https://redis.io/commands/expireat>
 * @method RedisConnection|mixed persist($key) 删除键的过期时间。 <https://redis.io/commands/persist>
 * @method RedisConnection|mixed pexpire($key, $milliseconds) 将密钥的生存时间设置为毫秒。 <https://redis.io/commands/pexpire>
 * @method RedisConnection|mixed pexpireat($key, $millisecondsTimestamp) 将密钥的过期时间设置为以毫秒为单位指定的UNIX时间戳。 <https://redis.io/commands/pexpireat>
 * @method RedisConnection|mixed psetex($key, $milliseconds, $value) 设置键的值和过期时间（毫秒）。 <https://redis.io/commands/psetex>
 * @method RedisConnection|mixed pttl($key) 以毫秒为单位返回 key 的剩余的过期时间。 <https://redis.io/commands/pttl>
 * 
 * @hidden Redis 字符串(String)
 * @method RedisConnection|mixed append($key, $value) 为指定的键追加值。 <https://redis.io/commands/append>
 * @method RedisConnection|mixed set($key, $value, ...$options) 设置键的字符串值。 <https://redis.io/commands/set>
 * @method RedisConnection|mixed setex($key, $seconds, $value) 设置键的值和过期时间。 <https://redis.io/commands/setex>
 * @method RedisConnection|mixed setnx($key, $value) 仅当键不存在时才设置键的值。 <https://redis.io/commands/setnx>
 * @method RedisConnection|mixed setrange($key, $offset, $value) 覆盖从指定偏移量开始的键处的字符串部分。 <https://redis.io/commands/setrange>
 * @method RedisConnection|mixed strlen($key) 获取存储在键中的值的长度。 <https://redis.io/commands/strlen>
 * @method RedisConnection|mixed get($key) 获取键的值。 <https://redis.io/commands/get>
 * @method RedisConnection|mixed getrange($key, $start, $end) 获取存储在键中的字符串的子字符串。 <https://redis.io/commands/getrange>
 * @method RedisConnection|mixed getset($key, $value) 设置键的字符串值并返回其旧值。 <https://redis.io/commands/getset>
 * @method RedisConnection|mixed decr($key) 将键的整数值减一。 <https://redis.io/commands/decr>
 * @method RedisConnection|mixed decrby($key, $decrement) 将键的整数值递减给定的数字。 <https://redis.io/commands/decrby>
 * @method RedisConnection|mixed incr($key) 将键的整数值增加一。 <https://redis.io/commands/incr>
 * @method RedisConnection|mixed incrby($key, $increment) 将键的整数值增加给定的量。 <https://redis.io/commands/incrby>
 * @method RedisConnection|mixed incrbyfloat($key, $increment) 将键的浮点值增加给定的量。 <https://redis.io/commands/incrbyfloat>
 * @method RedisConnection|mixed mget(...$keys) 获取所有给定键的值。 <https://redis.io/commands/mget>
 * @method RedisConnection|mixed mset(...$keyValuePairs) 一次设置多个键值对。 <https://redis.io/commands/mset>
 * @method RedisConnection|mixed msetnx(...$keyValuePairs) 仅当没有键存在时，才给键值对设置值。 <https://redis.io/commands/msetnx>
 * 
 * @hidden Redis 位(bit)
 * @method RedisConnection|mixed setbit($key, $offset, $value) 设置或清除键处存储的字符串值中偏移量处的位。 <https://redis.io/commands/setbit>
 * @method RedisConnection|mixed getbit($key, $offset) 返回键处存储的字符串值中偏移量处的位值。 <https://redis.io/commands/getbit>
 * @method RedisConnection|mixed bitcount($key, $start = null, $end = null) 统计一个字符串二进制位为1的数量。 <https://redis.io/commands/bitcount>
 * @method RedisConnection|mixed bitfield($key, ...$operations) 对字符串执行任意位域整数操作。 <https://redis.io/commands/bitfield>
 * @method RedisConnection|mixed bitop($operation, $destkey, ...$keys) 在字符串之间执行位运算。 <https://redis.io/commands/bitop>
 * @method RedisConnection|mixed bitpos($key, $bit, $start = null, $end = null) 查找字符串中的第一个位集或清除。 <https://redis.io/commands/bitpos>
 * 
 * @hidden Redis 地理位置(geo)
 * @method RedisConnection|mixed geoadd($key, $longitude, $latitude, $member, ...$more) 在使用排序集表示的地理空间索引中添加一个或多个地理空间项。 <https://redis.io/commands/geoadd>
 * @method RedisConnection|mixed geohash($key, ...$members) 以标准geohash字符串的形式返回地理空间索引的成员。 <https://redis.io/commands/geohash>
 * @method RedisConnection|mixed geopos($key, ...$members) 返回地理空间索引成员的经度和纬度。 <https://redis.io/commands/geopos>
 * @method RedisConnection|mixed geodist($key, $member1, $member2, $unit = null) 返回地理空间索引的两个成员之间的距离。 <https://redis.io/commands/geodist>
 * @method RedisConnection|mixed georadius($key, $longitude, $latitude, $radius, $metric, ...$options) 查询表示地理空间索引的已排序集，以获取与某个点的给定最大距离匹配的成员。 <https://redis.io/commands/georadius>
 * @method RedisConnection|mixed georadiusbymember($key, $member, $radius, $metric, ...$options) 查询表示地理空间索引的已排序集，以获取与某个成员的给定最大距离匹配的成员。 <https://redis.io/commands/georadiusbymember>
 * 
 * @hidden Redis 排序(sort)
 * @method RedisConnection|mixed sort($key, ...$options) 对列表、集合或已排序集合中的元素进行排序。 <https://redis.io/commands/sort>
 * 
 * @hidden Redis 列表(list)
 * @method RedisConnection|mixed lindex($key, $index) 按索引从列表中获取元素。 <https://redis.io/commands/lindex>
 * @method RedisConnection|mixed linsert($key, $where, $pivot, $value) 在列表中的另一个元素之前或之后插入一个元素。 <https://redis.io/commands/linsert>
 * @method RedisConnection|mixed llen($key) 获取列表中的元素个数。 <https://redis.io/commands/llen>
 * @method RedisConnection|mixed lpop($key) 删除并获取列表中的第一个元素。 <https://redis.io/commands/lpop>
 * @method RedisConnection|mixed lpush($key, ...$values) 将一个或多个值放在列表的前面。 <https://redis.io/commands/lpush>
 * @method RedisConnection|mixed lpushx($key, $value) 仅当列表存在时，才将值前置到列表。 <https://redis.io/commands/lpushx>
 * @method RedisConnection|mixed lrange($key, $start, $stop) 从列表中获取一系列元素。 <https://redis.io/commands/lrange>
 * @method RedisConnection|mixed lrem($key, $count, $value) 从列表中删除元素。 <https://redis.io/commands/lrem>
 * @method RedisConnection|mixed lset($key, $index, $value) 通过索引设置列表中元素的值。 <https://redis.io/commands/lset>
 * @method RedisConnection|mixed ltrim($key, $start, $stop) 将列表修剪到指定的范围。 <https://redis.io/commands/ltrim>
 * @method RedisConnection|mixed rpop($key) 移除并获取列表最后一个元素。 <https://redis.io/commands/rpop>
 * @method RedisConnection|mixed rpoplpush($source, $destination) 移除列表的最后一个元素，并将该元素添加到另一个列表并返回。 <https://redis.io/commands/rpoplpush>
 * @method RedisConnection|mixed rpush($key, ...$values) 在列表中添加一个或多个值。 <https://redis.io/commands/rpush>
 * @method RedisConnection|mixed rpushx($key, $value) 为已存在的列表添加值。 <https://redis.io/commands/rpushx>
 * @method RedisConnection|mixed blpop(...$keys, $timeout) 删除并获取列表中的第一个元素，或阻止直到有一个元素可用。 <https://redis.io/commands/blpop>
 * @method RedisConnection|mixed brpop(...$keys, $timeout) 删除并获取列表中的最后一个元素，或阻止直到一个元素可用。 <https://redis.io/commands/brpop>
 * @method RedisConnection|mixed brpoplpush($source, $destination, $timeout) 从一个列表中弹出一个值，将其推送到另一个列表并返回它；或者阻塞直到一个可用。 <https://redis.io/commands/brpoplpush>
 * 
 * @hidden Redis 哈希表(hash)
 * @method RedisConnection|mixed hscan($key, $cursor, $MATCH = null, $pattern = null, $COUNT = null, $count = null) 增量迭代哈希字段和关联值。 <https://redis.io/commands/hscan>
 * @method RedisConnection|mixed hdel($key, ...$fields) 删除一个或多个哈希表字段。 <https://redis.io/commands/hdel>
 * @method RedisConnection|mixed hexists($key, $field) 确定是否存在哈希字段。 <https://redis.io/commands/hexists>
 * @method RedisConnection|mixed hget($key, $field) 获取哈希字段的值。 <https://redis.io/commands/hget>
 * @method RedisConnection|mixed hgetall($key) 获取散列中的所有字段和值。 <https://redis.io/commands/hgetall>
 * @method RedisConnection|mixed hincrby($key, $field, $increment) 将哈希字段的整数值递增给定的数字。 <https://redis.io/commands/hincrby>
 * @method RedisConnection|mixed hincrbyfloat($key, $field, $increment) 将哈希表中给定键的浮点值按给定的增量递增。 <https://redis.io/commands/hincrbyfloat>
 * @method RedisConnection|mixed hkeys($key) 获取哈希表中的所有键。<https://redis.io/commands/hkeys>
 * @method RedisConnection|mixed hlen($key) 获取哈希表中键的个数。 <https://redis.io/commands/hlen>
 * @method RedisConnection|mixed hmget($key, ...$fields) 获取所有给定哈希表中的多个键获取多值。 <https://redis.io/commands/hmget>
 * @method RedisConnection|mixed hmset($key, $field, $value, ...$more) 为哈希表给定的键设置多个值。 <https://redis.io/commands/hmset>
 * @method RedisConnection|mixed hset($key, $field, $value) 为哈希表中给定的键设置字符串值。 <https://redis.io/commands/hset>
 * @method RedisConnection|mixed hsetnx($key, $field, $value) 为哈希表中给定的键设置值，仅当该键不存在时。 <https://redis.io/commands/hsetnx>
 * @method RedisConnection|mixed hstrlen($key, $field) 获取哈希表中给定键的值长度。 <https://redis.io/commands/hstrlen>
 * @method RedisConnection|mixed hvals($key) 获取哈希表中的所有值。 <https://redis.io/commands/hvals>
 * 
 * @hidden Redis 无序集合(Set)
 * @method RedisConnection|mixed sscan($key, $cursor, $MATCH = null, $pattern = null, $COUNT = null, $count = null) 增量迭代无序集合中的元素 <https://redis.io/commands/sscan>
 * @method RedisConnection|mixed sismember($key, $member) 确定给定值是否是集合的成员。 <https://redis.io/commands/sismember>
 * @method RedisConnection|mixed sadd($key, ...$members) 向集合中添加一个或多个成员。 <https://redis.io/commands/sadd>
 * @method RedisConnection|mixed scard($key) 获取集合中的成员数。 <https://redis.io/commands/scard>
 * @method RedisConnection|mixed smembers($key) 返回集合中的所有成员。 <https://redis.io/commands/smembers>
 * @method RedisConnection|mixed smove($source, $destination, $member) 将成员从一个集合移动到另一个集合。 <https://redis.io/commands/smove>
 * @method RedisConnection|mixed spop($key, $count = null) 从集合中移除并返回一个或多个随机成员。 <https://redis.io/commands/spop>
 * @method RedisConnection|mixed srandmember($key, $count = null) 从一个集合中获取一个或多个随机成员。 <https://redis.io/commands/srandmember>
 * @method RedisConnection|mixed srem($key, ...$members) 从集合中删除一个或多个成员。 <https://redis.io/commands/srem>
 * 
 * @method RedisConnection|mixed sinter(...$keys) 返回给定所有集合的交集 <https://redis.io/commands/sinter>
 * @method RedisConnection|mixed sinterstore($destination, ...$keys) 返回给定所有集合的交集并存储在目标中 <https://redis.io/commands/sinterstore>
 * @method RedisConnection|mixed sunion(...$keys) 返回所有给定集合的并集 <https://redis.io/commands/sunion>
 * @method RedisConnection|mixed sunionstore($destination, ...$keys) 所有给定集合的并集存储在目标集合中 <https://redis.io/commands/sunionstore>
 * @method RedisConnection|mixed sdiff(...$keys) 返回给定所有集合的差集。 <https://redis.io/commands/sdiff>
 * @method RedisConnection|mixed sdiffstore($destination, ...$keys) 返回给定所有集合的差集并存储在目标中。 <https://redis.io/commands/sdiffstore>
 * 
 * @hidden Redis 有序集合(Sorted set)
 * @method RedisConnection|mixed zscan($key, $cursor, $MATCH = null, $pattern = null, $COUNT = null, $count = null) 增量迭代有序集合中的元素（包括元素成员和元素分值） <https://redis.io/commands/zscan>
 * @method RedisConnection|mixed zadd($key, ...$options) 向有序集合添加一个或多个成员，或者更新已存在成员的分数。 <https://redis.io/commands/zadd>
 * @method RedisConnection|mixed zcard($key) 获取有序集合的成员数 <https://redis.io/commands/zcard>
 * @method RedisConnection|mixed zcount($key, $min, $max) 计算在有序集合中指定区间分数的成员数。 <https://redis.io/commands/zcount>
 * @method RedisConnection|mixed zlexcount($key, $min, $max) 在有序集合中计算指定字典区间内成员数量。 <https://redis.io/commands/zlexcount>
 * @method RedisConnection|mixed zscore($key, $member) 返回有序集中，成员的分数值 <https://redis.io/commands/zscore>
 * @method RedisConnection|mixed zrank($key, $member) 返回有序集合中指定成员的索引。 <https://redis.io/commands/zrank>
 * @method RedisConnection|mixed zrevrank($key, $member) 返回有序集合中指定成员的排名，有序集成员按分数值递减(从大到小)排序 <https://redis.io/commands/zrevrank>
 * @method RedisConnection|mixed zincrby($key, $increment, $member) 有序集合中对指定成员的分数加上增量。 <https://redis.io/commands/zincrby>
 * @method RedisConnection|mixed zinterstore($destination, $numkeys, $key, ...$options) 计算给定的一个或多个有序集的交集并将结果集存储在新的目标有序集合中 <https://redis.io/commands/zinterstore>
 * @method RedisConnection|mixed zunionstore($destination, $numkeys, $key, ...$options) 计算给定的一个或多个有序集的并集，并存储在新的目标中 <https://redis.io/commands/zunionstore>
 * @method RedisConnection|mixed zrange($key, $start, $stop, $WITHSCORES = null) 通过索引区间返回有序集合成指定区间内的成员。 <https://redis.io/commands/zrange>
 * @method RedisConnection|mixed zrangebylex($key, $min, $max, $LIMIT = null, $offset = null, $count = null) 通过字典区间返回有序集合的成员。 <https://redis.io/commands/zrangebylex>
 * @method RedisConnection|mixed zrangebyscore($key, $min, $max, ...$options) 通过分数返回有序集合指定区间内的成员。 <https://redis.io/commands/zrangebyscore>
 * @method RedisConnection|mixed zrem($key, ...$members) 移除有序集合中的一个或多个成员 <https://redis.io/commands/zrem>
 * @method RedisConnection|mixed zremrangebylex($key, $min, $max) 移除有序集合中给定的字典区间的所有成员 <https://redis.io/commands/zremrangebylex>
 * @method RedisConnection|mixed zremrangebyrank($key, $start, $stop) 移除有序集合中给定的排名区间的所有成员 <https://redis.io/commands/zremrangebyrank>
 * @method RedisConnection|mixed zremrangebyscore($key, $min, $max) 移除有序集合中给定的分数区间的所有成员 <https://redis.io/commands/zremrangebyscore>
 * @method RedisConnection|mixed zrevrange($key, $start, $stop, $WITHSCORES = null) 返回有序集中指定区间内的成员，通过索引，分数从高到底 <https://redis.io/commands/zrevrange>
 * @method RedisConnection|mixed zrevrangebylex($key, $max, $min, $LIMIT = null, $offset = null, $count = null) 按词典范围返回排序集中的成员范围，按从高到低的字符串顺序排列。 <https://redis.io/commands/zrevrangebylex>
 * @method RedisConnection|mixed zrevrangebyscore($key, $max, $min, $WITHSCORES = null, $LIMIT = null, $offset = null, $count = null) 返回有序集中指定分数区间内的成员，分数从高到低排序 <https://redis.io/commands/zrevrangebyscore>
 * 
 * @hidden Redis 流(Streams)
 * @method RedisConnection|mixed xack($stream, $group, ...$ids) 从流使用者组的挂起条目列表（PEL）中删除一条或多条消息 <https://redis.io/commands/xack>
 * @method RedisConnection|mixed xadd($stream, $id, $field, $value, ...$fieldsValues) 将指定的流条目附加到指定键处的流 <https://redis.io/commands/xadd>
 * @method RedisConnection|mixed xclaim($stream, $group, $consumer, $minIdleTimeMs, $id, ...$options) 更改挂起消息的所有权，以便新所有者是指定为命令参数的使用者 <https://redis.io/commands/xclaim>
 * @method RedisConnection|mixed xdel($stream, ...$ids) 从流中删除指定的条目，并返回删除的条目数 <https://redis.io/commands/xdel>
 * @method RedisConnection|mixed xgroup($subCommand, $stream, $group, ...$options) 管理与流数据结构关联的使用者组 <https://redis.io/commands/xgroup>
 * @method RedisConnection|mixed xinfo($subCommand, $stream, ...$options) 检索有关流和关联的使用者组的不同信息 <https://redis.io/commands/xinfo>
 * @method RedisConnection|mixed xlen($stream) 返回流中的条目数 <https://redis.io/commands/xlen>
 * @method RedisConnection|mixed xpending($stream, $group, ...$options) 通过使用者组从流中获取数据，而不确认这些数据，会产生创建挂起条目的效果 <https://redis.io/commands/xpending>
 * @method RedisConnection|mixed xrange($stream, $start, $end, ...$options) 返回与给定ID范围匹配的流条目 <https://redis.io/commands/xrange>
 * @method RedisConnection|mixed xread(...$options) 从一个或多个流中读取数据，只返回ID大于调用者报告的上一个接收ID的条目 <https://redis.io/commands/xread>
 * @method RedisConnection|mixed xreadgroup($subCommand, $group, $consumer, ...$options) 支持用户组的XREAD命令的特殊版本 <https://redis.io/commands/xreadgroup>
 * @method RedisConnection|mixed xrevrange($stream, $end, $start, ...$options) 与XRANGE完全相同，但显著的区别是以相反的顺序返回条目，并且以相反的顺序获取开始-结束范围 <https://redis.io/commands/xrevrange>
 * @method RedisConnection|mixed xtrim($stream, $strategy, ...$options) 将流修剪为给定数量的项，如果需要，逐出较旧的项（ID较低的项） <https://redis.io/commands/xtrim>
 */
class RedisConnection extends AsyncConnection {

	protected $_host;

	protected $_port;

	protected $_auth;
	
	public $unixSocket;
	public $database = 0;
	public $useSSL = false;
	public $socketClientFlags = STREAM_CLIENT_CONNECT;
	
	public $connectionString;
	public $connectionTimeout;
	public $dataTimeout;
	
	protected $_socket = false;
	protected $_isAsync = false;

	public function __construct(IPool $pool, string $host, int $port, ?string $auth = null) {
		$this->_pool = $pool;

		$this->_host = $host;
		$this->_port = $port;
		$this->_auth = $auth;
	}
	
	public function init() {
		if($this->connectionString !== null) return;
		
		if ($this->unixSocket) {
			$this->connectionString = "unix://{$this->unixSocket}";
		} else {
			$this->connectionString = "tcp://{$this->_host}:{$this->_port}";
		}
	}
	
	public function reset() {
		parent::reset();

		$this->_isAsync = false;
	}
	
	public function close() {
		if($this->_socket) {
			fclose($this->_socket);
			$this->_socket = null;
		}
	}
	
	public function remove() {
		parent::remove();

		$this->close();
	}
	
	public function open() {
		if($this->_socket) return;
		
		$errorNumber = $errorDescription = null;
		$this->_socket = @stream_socket_client(
			$this->connectionString,
			$errorNumber,
			$errorDescription,
			$this->connectionTimeout ?: ini_get('default_socket_timeout'),
			$this->socketClientFlags
		);

		if ($this->_socket) {
			if ($this->dataTimeout !== null) {
				stream_set_timeout($this->_socket, $timeout = (int) $this->dataTimeout, (int) (($this->dataTimeout - $timeout) * 1000000));
			}
			if ($this->useSSL) {
				stream_socket_enable_crypto($this->_socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
			}
			if ($this->_auth !== null) {
				$this->auth($this->_auth);
			}
			if ($this->database !== null) {
				$this->select($this->database);
			}
		} else {
			throw new Exception("Failed to open redis DB connection ($this->connectionString, database = {$this->database}): $errorNumber - $errorDescription", compact('errorDescription', 'errorNumber'));
		}
	}
	
	/**
	 * @return RedisConnection
	 */
	public function beginAsync() {
		if($this->_readEvent || $this->_isAsync) {
			return $this;
		} else {
			$this->_isAsync = true;
			return $this;
		}
	}
	
	protected function getFd() {
		return $this->_socket;
	}
	
	public function goAsync(callable $success, callable $error, float $timeout = -1) {
		if($this->_isAsync) {
			$this->_isAsync = false;
			$this->_syncKey = null;
			$this->_syncCallback = [];
			return parent::goAsync($success, $error, $timeout);
		} else {
			return false;
		}
	}

	protected $_syncKey;
	public function setAsyncKey(string $name) {
		if($this->_isAsync) {
			$this->_syncKey = $name;
		}
		return $this;
	}

	protected $_syncCallback = [];
	public function setAsyncCallback(callable $success, callable $error) {
		if($this->_isAsync) {
			$this->_syncCallback = compact('success', 'error');
		}

		return $this;
	}
	
	public function __call(string $name, array $params) {
		$name = strtoupper(trim(str_replace(['-', '_', '.'], ' ', preg_replace('/(?<!\p{Lu})(\p{Lu})|(\p{Lu})(?=\p{Ll})/u', ' \0', $name))));
		$params = array_merge(preg_split("/\s/u", $name, -1, PREG_SPLIT_NO_EMPTY), $params);
		$command = '*' . count($params) . "\r\n";
		foreach ($params as $arg) {
			$command .= '$' . strlen($arg) . "\r\n" . $arg . "\r\n";
		}

		if($this->_isAsync) {
			$key = $this->_syncKey;
			$callback = $this->_syncCallback;
			$this->_syncKey = null;
			$this->_syncCallback = [];
			$db = $this;
			$this->_events[] = \Fwe::createObject(RedisEvent::class, compact('db', 'name', 'params', 'command', 'key') + $callback);
			return $this;
		} elseif($this->_readEvent) {
			$this->sendCommandInternal($command, $params);
		} else {
			$this->sendCommandInternal($command, $params);
			return $this->multiParseResponse($params);
		}
	}
	
	public function ping(): bool {
		if($this->_socket) {
			try {
				return $this->__call('ping', []);
			} catch(SocketException $e) {
				$this->close();
				try {
					$this->open();
					return true;
				} catch(Exception $e) {
					$this->close();
					return false;
				}
			}
		} else {
			$this->open();
			return true;
		}
	}

	/**
	 * 发送原始命令字符串到Redis服务器
	 * 
	 * @throws SocketException 在连接错误抛出
	 */
	public function sendCommandInternal(string $command, array $params) {
		$written = @fwrite($this->_socket, $command);
		if ($written === false) {
			throw new SocketException("Failed to write to socket.\nRedis command was: " . $command);
		}
		if ($written !== ($len = mb_strlen($command, '8bit'))) {
			throw new SocketException("Failed to write to socket. $written of $len bytes written.\nRedis command was: " . $command);
		}
	}
	
	/**
	 * 读取并分析多个响应结果
	 * 
	 * @param array $params
	 * @return mixed
	 * @throws Exception on error
	 * @throws SocketException
	 */
	public function multiParseResponse($params) {
		if(strpos($params[0], 'SUBSCRIBE') !== false) {
			$count = count($params) - 1;
			if($count <= 1) {
				return $this->parseResponse($params);
			}
			$rets = [];
			for($i=0; $i<$count; $i++) {
				$rets[] = $this->parseResponse($params);
			}
			return $rets;
		} else {
			return $this->parseResponse($params);
		}
	}
	
	public function formatCommandParams(array $params) {
		return implode(' ', array_map(function($param) {
			return ($param === null || $param === '' || preg_match('/[\s\']+/', $param)) ? "'$param'" : $param;
		}, $params));
	}
	
	/**
	 * 读取并分析响应结果
	 * 
	 * @param array $params
	 * @return mixed
	 * @throws Exception on error
	 * @throws SocketException
	 */
	public function parseResponse(array $params) {
		if (($line = fgets($this->_socket)) === false) {
			throw new SocketException("Failed to read from socket.\nRedis command was: " . $this->formatCommandParams($params));
		}
		$type = $line[0];
		$line = mb_substr($line, 1, -2, '8bit');
		switch ($type) {
			case '+': // Status reply
				if ($line === 'OK' || $line === 'PONG') {
					return true;
				}
				
				return $line;
			case '-': // Error reply
				throw new Exception("Redis error: " . $line . "\nRedis command was: " . $this->formatCommandParams($params));
			case ':': // Integer reply
				// no cast to int as it is in the range of a signed 64 bit integer
				return (int) $line;
			case '$': // Bulk replies
				if ($line == '-1') {
					return null;
				}
				$length = (int)$line + 2;
				$data = '';
				while ($length > 0) {
					if (($block = fread($this->_socket, $length)) === false) {
						throw new SocketException("Failed to read from socket.\nRedis command was: " . $this->formatCommandParams($params));
					}
					$data .= $block;
					$length -= strlen($block);
				}
				
				return substr($data, 0, -2);
			case '*': // Multi-bulk replies
				$count = (int) $line;
				$data = [];
				for ($i = 0; $i < $count; $i++) {
					$data[] = $this->parseResponse($params);
				}
				
				return $data;
			default:
				throw new Exception('Received illegal data from redis: ' . $line . "\nRedis command was: " . $this->formatCommandParams($params));
		}
	}
	
	/**
	 * @var \Event
	 */
	protected $_readEvent;
	
	/**
	 * 删除读取事件
	 */
	public function delReadEvent() {
		if($this->_readEvent) {
			$this->_readEvent->del();
			$this->_readEvent = null;
			\Fwe::$app->events--;
		}
	}
	
	/**
	 * 绑定读取事件
	 * 
	 * @param callable $success
	 * @param callable $error
	 * @param float $timeout
	 */
	public function bindReadEvent(callable $success, ?callable $error = null, float $timeout = 10.0) {
		$this->delReadEvent();
		$this->_readEvent = new \Event(\Fwe::$base, $this->getFd(), \Event::READ | \Event::PERSIST, function($fd, int $what) use($success, $error) {
			try {
				if($what === \Event::TIMEOUT) {
					$this->ping();
					return;
				} else {
					do {
						$ret = $success($this->parseResponse(['readEvent']));
						$read = [$this->getFd()];
						$write = $except = null;
					} while($ret !== false && stream_select($read, $write, $except, 0));
				}
			} catch(\Throwable $e) {
				if($error) {
					$ret = $error($e);
				} else {
					$ret = false;
					\Fwe::$app->error($e, 'redis');
				}
			}
			if($ret === false) {
				$this->delReadEvent();
			}
		});
		$this->_readEvent->add($timeout);
		\Fwe::$app->events++;
	}
	
	public function isClosed(): bool {
		return !$this->_socket;
	}
}
