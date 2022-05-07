<?php
namespace app\commands;

use fwe\console\Controller;
use fwe\http\Request;
use fwe\http\File;

class HttpController extends Controller {

	/**
	 * GET请求
	 * 
	 * @return boolean
	 */
	public function actionIndex() {
		$req = new Request('http://127.0.0.1:5000/default/info');

		$req->send(function (int $errno, string $error) use ($req) {
			echo "errno: $errno, error: $error\n";
			echo "$req\n";
		});

		return false;
	}
	
	/**
	 * HEAD请求
	 *
	 * @return boolean
	 */
	public function actionHead() {
		$req = new Request('http://127.0.0.1:5000/default/info?isChunk=0', 'HEAD');
		
		$req->send(function (int $errno, string $error) use ($req) {
			echo "errno: $errno, error: $error\n";
			echo "$req\n";
		});
		
		return false;
	}

	/**
	 * 百度搜索
	 * 
	 * @return boolean
	 */
	public function actionBaidu() {
		$req = new Request('https://www.baidu.com/s?wd=threadtask-frameworks&rsv_spt=1&rsv_iqid=0xfcd81d7900019b64&issp=1&f=8&rsv_bp=1&rsv_idx=2&ie=utf-8&rqlang=cn&tn=baiduhome_pg&rsv_enter=1&rsv_dl=tb&oq=threadtask-framework&rsv_btype=t&inputT=704&rsv_t=d8cfDunVtOcpFz91%2F%2BhcOkT3STTnQvgJdXbGaoWX8iY4QAwh2QqBR0wI%2BnG3TAsPyJ%2F%2B&rsv_pq=ffabca2b00039cba&rsv_sug2=0&rsv_sug4=2902');

		$req->send(function (int $errno, string $error) use ($req) {
			echo "errno: $errno, error: $error\n";
			echo "$req\n";
		});

		return false;
	}

	/**
	 * PUT文件上传
	 * 
	 * @return boolean
	 */
	public function actionFile() {
		$req = new Request('http://127.0.0.1:5000/default/info', 'PUT');
		$req->setFile(INFILE);
		$req->send(function (int $errno, string $error) use ($req) {
			echo "errno: $errno, error: $error\n";
			echo "$req\n";
		});

		return false;
	}

	/**
	 * 表单提交
	 * 
	 * @return boolean
	 */
	public function actionForm() {
		$req = new Request('http://127.0.0.1:5000/default/info', 'POST');
		$req->addForm('class', __CLASS__);
		$req->addForm('file', new File(__FILE__));
		$req->addForm('method', __FUNCTION__);
		$req->addForm('name', basename(__FILE__));
		$req->addForm('dir', basename(__DIR__));
		$req->send(function (int $errno, string $error) use ($req) {
			echo "errno: $errno, error: $error\n";
			echo "$req\n";
		});

		return false;
	}

	/**
	 * GET提交DATA
	 * 
	 * @param bool $isJson
	 * @param bool $isXml
	 * @return boolean
	 */
	public function actionData(bool $isJson = false, bool $isXml = false) {
		$req = new Request('http://127.0.0.1:5000/default/info');
		$req->setData($_SERVER);
		if($isJson) {
			$req->setFormat(Request::FORMAT_JSON);
		} elseif($isXml) {
			$req->setFormat(Request::FORMAT_XML);
		}
		$req->send(function (int $errno, string $error) use ($req) {
			echo "errno: $errno, error: $error\n";
			echo "$req\n";
		});

		return false;
	}

	/**
	 * GET响应数据句柄
	 * 
	 * @return boolean
	 */
	public function actionHandler() {
		$req = new Request('http://127.0.0.1:5000/default/info');
		$req->setResponseHandler(function(string $buf, int $n) {
			echo "n: $n, buf: $buf\n";
		});
		$req->send(function (int $errno, string $error) use ($req) {
			echo "errno: $errno, error: $error\n";
		});

		return false;
	}
}
