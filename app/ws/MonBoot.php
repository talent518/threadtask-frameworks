<?php
namespace app\ws;

use fwe\base\TsVar;
use fwe\utils\StringHelper;

class MonBoot {
    /**
     * @var TsVar
     */
    protected $_cpu, $_mem, $_loadavg, $_proc, $_disk, $_net;

    public function __construct() {
        $this->_cpu = new TsVar("monitor-cpu");
        $this->_mem = new TsVar("monitor-mem");
        $this->_loadavg = new TsVar("monitor-loadavg");
        $this->_proc = new TsVar("monitor-proc");
        $this->_disk = new TsVar("monitor-disk");
        $this->_net = new TsVar("monitor-net");
        foreach(['user', 'nice', 'system', 'idle', 'iowait', 'irq', 'softirq', 'stolen', 'guest'] as $key) {
            $this->_cpu[$key] = 0.0;
        }
        $this->_cpu['idle'] = 100.0;
        $this->_cpu['times'] = 1;
        $this->getmem();
        $this->getloadavg();
        $this->getprocs();
        $this->getdisks();
        $this->getnets();
    }

    protected $cpu, $all;
    public function init() {
        $this->cpu = $this->getcpu();
        if($this->cpu === false) return;

        $this->all = array_sum($this->cpu);
    }

    public function event() {
        static $times = 1;

        $cpu = $this->cpu;
        $all = $this->all;

        $this->cpu = $this->getcpu();
        if($this->cpu === false) return;

        $this->all = array_sum($this->cpu);

        $all = $this->all - $all;
        if($all <= 0) $all = 1;
        foreach($this->cpu as $key=>$val) {
            $this->_cpu[$key] = round(($val - $cpu[$key]) * 100.0 / $all, 1);
        }
        $this->_cpu['times'] = ++ $times;

        if($this->getmem() === false) return;

        if($this->getloadavg() === false) return;

        $this->getprocs();
        $this->getdisks();
        $this->getnets();
    }

    protected function getcpu() {
        $fp = @fopen('/proc/stat', 'r');
        if(!$fp) return false;

        $n = fscanf($fp, "%s %d %d %d %d %d %d %d %d %d", $cpu, $user, $nice, $system, $idle, $iowait, $irq, $softirq, $stolen, $guest);

        fclose($fp);

        if($n === 10) {
            return compact('user', 'nice', 'system', 'idle', 'iowait', 'irq', 'softirq', 'stolen', 'guest');
        } else {
            return false;
        }
    }

    protected function getmem() {
        static $times = 0;

        $fp = @fopen('/proc/meminfo', 'r');
        if(!$fp) return false;

        $i = 0377;
        while($i > 0 && !feof($fp) && ($line = @fgets($fp)) !== false && sscanf($line, "%[^:]: %d", $key, $val) === 2) {
            // echo "$key: $val\n";
            $val *= 1024;
            if(($i & 01) && $key === "MemTotal") {
                $this->_mem['total'] = $val;
                $i ^= 01;
                continue;
            }
            if(($i & 02) && $key === "MemFree") {
                $this->_mem['free'] = $val;
                $i ^= 02;
                continue;
            }
            if(($i & 04) && $key === "Buffers") {
                $this->_mem['buffers'] = $val;

                $i ^= 04;
                continue;
            }
            if(($i & 010) && $key === "Cached") {
                $this->_mem['cached'] = $val;

                $i ^= 010;
                continue;
            }
            if(($i & 020) && $key === "Mlocked") {
                $this->_mem['locked'] = $val;

                $i ^= 020;
                continue;
            }
            if(($i & 040) && $key === "SwapTotal") {
                $this->_mem['swapTotal'] = $val;
                $i ^= 040;
                continue;
            }
            if(($i & 0100) && $key === "SwapFree") {
                $this->_mem['swapFree'] = $val;
                $i ^= 0100;
                continue;
            }
            if(($i & 0200) && $key === "Shmem") {
                $this->_mem['shared'] = $val;
                $i ^= 0200;
                continue;
            }
        }

        $this->_mem['times'] = ++ $times;

        fclose($fp);

        return $i === 0;
    }

    protected function getloadavg() {
        static $times = 0;

        $fp = @fopen('/proc/loadavg', 'r');
        if(!$fp) return false;

        $n = fscanf($fp, "%f %f %f %d/%d %d", $min1, $min5, $min15, $runs, $procs, $pid);

        fclose($fp);

        if($n === 6) {
            foreach(['min1', 'min5', 'min15', 'runs', 'procs', 'pid'] as $key) {
                $this->_loadavg[$key] = $$key;
            }
            $this->_loadavg['times'] = ++ $times;
            return true;
        } else {
            return false;
        }
    }

    protected function getpids() {
        $ret = @scandir('/proc');
        if($ret === false) {
            return [];
        } else {
            $pids = [];
            foreach($ret as $pid) {
                if(ctype_digit($pid)) {
                    $pids[] = (int) $pid;
                }
            }
            return $pids;
        }
    }

    protected $procs = [];
    protected function getprocs() {
        $pids = $this->getpids();
        rsort($pids, SORT_NUMERIC);
        foreach($pids as $pid) {
            $proc = $this->getproc($pid);
            if($proc === false) {
                unset($this->procs[$pid], $this->_proc[$pid]);
                continue;
            }

            $proc2 = $this->procs[$pid] ?? false;
            $proc['times'] = ($proc2['times'] ?? 0) + 1;
            $this->procs[$pid] = $proc;

            if($proc2) {
                $interval = $proc['time'] - $proc2['time'];
                $ucpu = ($proc['utime'] - $proc2['utime']) / $interval;
                $scpu = ($proc['stime'] - $proc2['stime']) / $interval;
                $proc['read_bytes'] -= $proc2['read_bytes'];
                $proc['write_bytes'] -= $proc2['write_bytes'];
            } else {
                $interval = 0;
                $ucpu = $scpu = 0;
            }

            unset($proc['utime'], $proc['stime'], $proc['cutime'], $proc['cstime'], $proc['time']);
            $proc['ucpu'] = round($ucpu, 1);
            $proc['scpu'] = round($scpu, 1);
            $proc['interval'] = $interval;
            $this->_proc[$pid] = $proc;
        }
        foreach(array_diff(array_keys($this->procs), $pids) as $pid) {
            // echo "Remove: $pid\n";
            unset($this->procs[$pid], $this->_proc[$pid]);
        }
    }

    protected function getproc(int $pid) {
        $ret = [];
        $ret['pid'] = $pid;

        $fp = @fopen("/proc/$pid/stat", 'r');
        if($fp) {
            $n = fscanf($fp, '%*s (%[^)]) %c %d %d %*s %*s %*s %*s %*s %*s %*s %*s %d %d %d %d %*s %*s %d %*s %d %*s %d', $comm, $state, $ppid, $pgrp, $utime, $stime, $cutime, $cstime, $threads, $etime, $rss);
            $ret['time'] = microtime(true);
            fclose($fp);
            if($n === 24 && $pgrp > 0) {
                $fp = @fopen("/proc/uptime", 'r');
                if($fp) {
                    $n = fscanf($fp, "%f %f", $num1, $num2); // num1: 系统启动到现在的时间(以秒为单位), num2: 系统空闲的时间(以秒为单位)
                    fclose($fp);
                    if($n === 2) {
                        $etime = round($num1 - $etime / 100, 2);
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }
                $rss *= 4096;
                foreach(['ppid', 'pgrp', 'comm', 'state', 'utime', 'stime', 'cutime', 'cstime', 'threads', 'etime', 'rss'] as $key) {
                    $ret[$key] = $$key;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }

        $fp = @fopen("/proc/$pid/statm", 'r');
        if($fp) {
            $n = fscanf($fp, '%d %d %d %d %d %d %d', $size, $resident, $share, $text, $lib, $data, $dirty);
            fclose($fp);
            if($n === 7 && $size > 0) {
                foreach(['size', 'resident', 'share', 'text', 'lib', 'data', 'dirty'] as $key) {
                    $ret[$key] = $$key * 4096;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }

        $fp = @fopen("/proc/$pid/status", 'r');
        if($fp) {
            while(!feof($fp) && ($line = @fgets($fp)) !== false) {
                if(sscanf($line, "%[^:]: %d", $key, $val) === 2 && $key === 'RssFile') {
                    $ret['rssFile'] = $val * 1024;
                    $ret['dirty'] = $ret['resident'] - $val * 1024;
                    break;
                }
            }

            fclose($fp);
        } else {
            return false;
        }

        if(!isset($ret['rssFile'])) {
            $ret['dirty'] = $this->getdirtys($pid);
            $ret['rssFile'] = $ret['resident'] - $ret['dirty'];
        }

        $ret['read_bytes'] = $ret['write_bytes'] = 0;
        $fp = @fopen("/proc/$pid/io", 'r');
        if($fp) {
            $i = 3;
            while($i > 0 && !feof($fp) && ($line = @fgets($fp)) !== false && sscanf($line, "%[^:]: %d", $key, $val) === 2) {
                if(($i & 1) && $key === 'read_bytes') {
                    $i ^= 1;
                    $ret['read_bytes'] = $val;
                    continue;
                }
                if(($i & 2) && $key === 'write_bytes') {
                    $i ^= 2;
                    $ret['write_bytes'] = $val;
                    continue;
                }
            }

            fclose($fp);
        }

        $ret['cmdline'] = file_get_contents("/proc/$pid/cmdline");
        if($ret['cmdline'] === false) return false;

        $ret['cmdline'] = implode(' ', array_map('escapeshellcmd', explode("\0", trim($ret['cmdline'], "\0"))));
        $ret['fds'] = $this->getfds($pid);

        return $ret;
    }

    protected function getdirtys(int $pid) {
        $fp = @fopen("/proc/$pid/smaps", 'r');
        if(!$fp) return 0;

        $n = 0;
        while(!feof($fp) && ($line = @fgets($fp)) !== false && sscanf($line, "%[^:]: %d", $key, $val) === 2) {
            $val *= 1024;
            if($key === 'Private_Dirty' || $key === 'Shared_Dirty') {
                $n += $val;
            }
        }

        fclose($fp);

        // echo "dirty($pid): $n\n";

        return $n;
    }

    protected function getfds(int $pid) {
        $fds = @scandir("/proc/$pid/fd");
        $n = 0;
        if($fds) {
            foreach($fds as $fd) {
                if(ctype_digit($fd)) {
                    $n++;
                }
            }
        }
        return $n;
    }

    protected $disks = [], $mounts = [];
    protected function getdisks() {
        $fp = @fopen('/proc/diskstats', 'r');
        if(!$fp) return false;

        if(preg_match_all('/^\/dev\/([^\s]+)\s+([^\s]+)\s+([^\s]+)/m', file_get_contents('/proc/mounts'), $matches)) {
            $this->mounts = [];
            foreach($matches[1] as $i => $k) {
                $path = $matches[2][$i];
                $stat = statfs($path);
                $this->mounts[$k] = [
                    'path' => $path,
                    'type' => $matches[3][$i],
                    // 'loop' => strncmp($k, 'loop', 4) === 0 ? file_get_contents("/sys/block/$k/loop/backing_file") : '',
                    'total' => StringHelper::formatBytes($stat['total'] ?? 0),
                    'avail' => StringHelper::formatBytes($stat['avail'] ?? 0),
                    'free' => StringHelper::formatBytes($stat['free'] ?? 0),
                ];
            }
        }

        $keys = [];
        while(!feof($fp) && ($line = @fgets($fp)) !== false && sscanf($line, "%*s %*s %[^ ] %ld %*s %ld %*s %ld %*s %ld", $key, $rd_ops, $rd_bytes, $wr_ops, $wr_bytes) === 10) {
            $rd_bytes *= 512;
            $wr_bytes *= 512;
            $disk2 = ($this->disks[$key] ?? false);
            $times = ($disk2['times'] ?? 0) + 1;
            $this->disks[$key] = $disk = compact('rd_ops', 'rd_bytes', 'wr_ops', 'wr_bytes', 'times');
            $keys[] = $key;
            if($disk2) {
                unset($disk2['times']);
                foreach($disk2 as $_key => $val) {
                    $disk[$_key] -= $val;
                }
            }
            $mount = $this->mounts[$key] ?? [];
            if(!isset($mount['total'])) {
                if($key) {
                    if(preg_match('/^(sd[a-z]+|mmcblk\d+p)\d+$/', $key, $matches)) {
                        $_key = (string) $matches[1];
                        $_key = $_key[0] === 's' ? $_key : substr($_key, 0, -1);
                        $_key = "$_key/$key";
                    } else {
                        $_key = $key;
                    }
                } else {
                    $_key = $key;
                }
                $mount['total'] = StringHelper::formatBytes(file_get_contents("/sys/block/$_key/size") * 512);
            }
            $this->_disk[$key] = $mount + $disk;
        }

        fclose($fp);

        foreach(array_diff(array_keys($this->disks), $keys) as $key) {
            unset($this->disks[$key], $this->_disk[$key]);
        }

        return true;
    }

    protected $nets = [];
    protected function getnets() {
        $fp = @fopen('/proc/net/dev', 'r');
        if(!$fp) return false;

        @fgets($fp);
        @fgets($fp);

        $keys = [];
        while(!feof($fp) && ($line = @fgets($fp)) !== false && sscanf($line, " %[^:]: %ld %ld %*s %*s %*s %*s %*s %*s %ld %ld", $key, $recv_bytes, $recv_packets, $send_bytes, $send_packets) === 11) {
            $net2 = ($this->nets[$key] ?? false);
            $times = ($net2['times'] ?? 0) + 1;
            $this->nets[$key] = $net = compact('recv_bytes', 'recv_packets', 'send_bytes', 'send_packets', 'times');
            $keys[] = $key;
            if($net2) {
                unset($net2['times']);
                foreach($net2 as $_key => $val) {
                    $net[$_key] -= $val;
                }
            }
            $this->_net[$key] = $net;
        }

        fclose($fp);

        foreach(array_diff(array_keys($this->nets), $keys) as $key) {
            unset($this->nets[$key], $this->_net[$key]);
        }

        return true;
    }

    public function boot() {
        $this->event = new \Event(\Fwe::$base, -1, \Event::TIMEOUT | \Event::PERSIST, [$this, 'event']);
        $this->event->addTimer(1);
    }
}
